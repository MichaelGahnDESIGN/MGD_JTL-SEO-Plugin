<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Update;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

final class SafePluginUpdater
{
    private const PLUGIN_ID = 'MGD_SEOoverride_Plugin';
    private const TRUSTED_DOWNLOAD_PREFIX = 'https://github.com/MichaelGahnDESIGN/MGD_JTL-SEO-Plugin/releases/download/';
    private const MAX_ZIP_BYTES = 25_000_000;
    private const MAX_META_BYTES = 262_144;

    /**
     * Lädt Release-Artefakte, prüft SHA256, Manifest und Plugin-ZIP und liefert einen sicheren Plan.
     * Es werden noch keine installierten Dateien verändert.
     *
     * @param array<string,mixed> $release
     * @return array<string,mixed>
     */
    public function prepare(array $release, string $currentVersion): array
    {
        foreach (['download_url', 'sha256_url', 'manifest_url', 'latest'] as $required) {
            if (!isset($release[$required]) || !is_string($release[$required]) || trim($release[$required]) === '') {
                throw new RuntimeException('Das GitHub-Release enthält nicht alle signierten Update-Artefakte. Fehlend: ' . $required);
            }
        }
        $latest = (string)$release['latest'];
        if (preg_match('/^\d+\.\d+\.\d+$/D', $latest) !== 1 || !version_compare($latest, $currentVersion, '>')) {
            throw new RuntimeException('Das Release ist keine neuere stabile Version.');
        }
        $zipUrl = (string)$release['download_url'];
        $shaUrl = (string)$release['sha256_url'];
        $manifestUrl = (string)$release['manifest_url'];
        $this->assertTrustedReleaseUrl($zipUrl, $latest, '.zip');
        $this->assertTrustedReleaseUrl($shaUrl, $latest, '.zip.sha256');
        $this->assertTrustedReleaseUrl($manifestUrl, $latest, '.manifest.json');

        $work = $this->makeTempDirectory('mgd-seo-update-');
        $zipFile = $work . DIRECTORY_SEPARATOR . 'package.zip';
        try {
            $shaText = $this->downloadText($shaUrl);
            $manifestText = $this->downloadText($manifestUrl);
            $this->downloadFile($zipUrl, $zipFile, self::MAX_ZIP_BYTES);
            $expectedSha = $this->parseSha256($shaText);
            $actualSha = hash_file('sha256', $zipFile);
            if (!is_string($actualSha) || !hash_equals($expectedSha, strtolower($actualSha))) {
                throw new RuntimeException('Die SHA256-Prüfsumme der Plugin-ZIP stimmt nicht mit dem Release überein.');
            }
            $releaseDigest = $release['asset_digest'] ?? null;
            if (is_string($releaseDigest) && preg_match('/^[a-f0-9]{64}$/D', $releaseDigest) === 1 && !hash_equals($releaseDigest, strtolower($actualSha))) {
                throw new RuntimeException('GitHubs Asset-Digest widerspricht der Release-Prüfsumme.');
            }
            $manifest = $this->parseManifest($manifestText, $latest, $expectedSha);
            $zipInfo = $this->validateZip($zipFile, $latest);
            return [
                'ok' => true,
                'version' => $latest,
                'sha256' => $actualSha,
                'zip_file' => $zipFile,
                'work_dir' => $work,
                'manifest' => $manifest,
                'zip' => $zipInfo,
                'requires_jtl_update' => ($manifest['requires_jtl_update'] ?? true) === true,
                'self_update_safe' => ($manifest['self_update_safe'] ?? false) === true,
                'message' => (($manifest['requires_jtl_update'] ?? true) === true)
                    ? 'Dieses Release verändert JTL-Plugin-Metadaten oder Migrationen und muss über den nativen JTL-Plugin-Manager abgeschlossen werden.'
                    : 'Paket, Prüfsumme und Manifest sind verifiziert.',
            ];
        } catch (\Throwable $e) {
            $this->deleteTree($work);
            throw $e;
        }
    }

    /**
     * Wendet ausschließlich ausdrücklich als selbstaktualisierbar markierte Hotfix-Pakete an.
     * Normale Versionsupdates bleiben im JTL-Lifecycle, damit info.xml, Einstellungen und Migrationen korrekt verarbeitet werden.
     *
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    public function applyVerifiedHotfix(array $plan): array
    {
        if (($plan['ok'] ?? false) !== true || ($plan['requires_jtl_update'] ?? true) === true || ($plan['self_update_safe'] ?? false) !== true) {
            throw new RuntimeException('Dieses Release darf nicht außerhalb des nativen JTL-Update-Lifecycles installiert werden.');
        }
        $manifest = is_array($plan['manifest'] ?? null) ? $plan['manifest'] : [];
        $installedMetaVersion = $this->installedInfoVersion();
        $targetVersion = (string)($manifest['version'] ?? '');
        if ($targetVersion !== $installedMetaVersion) {
            throw new RuntimeException('Ein Self-Update darf die JTL-Plugin-Version nicht verändern. Für Versionssprünge ist der JTL-Plugin-Manager erforderlich.');
        }
        $zipFile = (string)($plan['zip_file'] ?? '');
        $workDir = (string)($plan['work_dir'] ?? '');
        if ($zipFile === '' || $workDir === '' || !is_file($zipFile)) {
            throw new RuntimeException('Das verifizierte Paket ist nicht mehr vorhanden.');
        }

        $pluginRoot = dirname(__DIR__, 2);
        $pluginParent = dirname($pluginRoot);
        if (!is_dir($pluginRoot) || !is_writable($pluginParent)) {
            throw new RuntimeException('Das Plugin-Verzeichnis ist nicht sicher schreibbar.');
        }
        $suffix = gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
        $stage = $pluginParent . DIRECTORY_SEPARATOR . '.mgd-seo-stage-' . $suffix;
        $backup = $pluginParent . DIRECTORY_SEPARATOR . '.mgd-seo-backup-' . $suffix;
        if (!mkdir($stage, 0755, true) && !is_dir($stage)) {
            throw new RuntimeException('Staging-Verzeichnis konnte nicht angelegt werden.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== true) {
                throw new RuntimeException('Verifiziertes ZIP konnte nicht erneut geöffnet werden.');
            }
            if (!$zip->extractTo($stage)) {
                $zip->close();
                throw new RuntimeException('Verifiziertes ZIP konnte nicht in das Staging-Verzeichnis entpackt werden.');
            }
            $zip->close();
            $stagedRoot = $stage . DIRECTORY_SEPARATOR . self::PLUGIN_ID;
            $this->validateExtractedPlugin($stagedRoot, $targetVersion);

            if (!rename($pluginRoot, $backup)) {
                throw new RuntimeException('Bestehendes Plugin konnte nicht atomar gesichert werden.');
            }
            $activated = false;
            try {
                if (!rename($stagedRoot, $pluginRoot)) {
                    throw new RuntimeException('Neue Plugin-Dateien konnten nicht atomar aktiviert werden.');
                }
                $activated = true;
            } catch (\Throwable $activationError) {
                if (!is_dir($pluginRoot) && is_dir($backup)) {
                    @rename($backup, $pluginRoot);
                }
                throw $activationError;
            }
            if (!$activated || !is_file($pluginRoot . DIRECTORY_SEPARATOR . 'info.xml')) {
                if (is_dir($pluginRoot)) {
                    $this->deleteTree($pluginRoot);
                }
                if (is_dir($backup)) {
                    @rename($backup, $pluginRoot);
                }
                throw new RuntimeException('Postcondition des atomaren Updates ist fehlgeschlagen; Rollback wurde versucht.');
            }
            $this->deleteTree($stage);
            $this->deleteTree($workDir);
            return [
                'ok' => true,
                'backup' => $backup,
                'message' => 'Verifizierter Hotfix wurde atomar eingespielt. Das Backup bleibt für ein manuelles Rollback erhalten.',
            ];
        } catch (\Throwable $e) {
            if (is_dir($stage)) {
                $this->deleteTree($stage);
            }
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    private function parseManifest(string $json, string $version, string $sha): array
    {
        try {
            $manifest = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Release-Manifest ist ungültig: ' . $e->getMessage());
        }
        if (!is_array($manifest)) {
            throw new RuntimeException('Release-Manifest ist kein JSON-Objekt.');
        }
        if (($manifest['plugin_id'] ?? null) !== self::PLUGIN_ID || ($manifest['version'] ?? null) !== $version) {
            throw new RuntimeException('Release-Manifest gehört nicht zu diesem Plugin oder dieser Version.');
        }
        $manifestSha = strtolower((string)($manifest['sha256'] ?? ''));
        if (preg_match('/^[a-f0-9]{64}$/D', $manifestSha) !== 1 || !hash_equals($sha, $manifestSha)) {
            throw new RuntimeException('Release-Manifest enthält keine passende SHA256-Prüfsumme.');
        }
        $minShop = (string)($manifest['min_shop_version'] ?? '5.7.0');
        if (preg_match('/^\d+\.\d+\.\d+$/D', $minShop) !== 1) {
            throw new RuntimeException('Release-Manifest enthält eine ungültige Mindest-Shopversion.');
        }
        $shopVersion = defined('APPLICATION_VERSION') ? (string)APPLICATION_VERSION : '';
        if ($shopVersion !== '' && preg_match('/^\d+\.\d+\.\d+(?:[-+].*)?$/D', $shopVersion) === 1 && version_compare($shopVersion, $minShop, '<')) {
            throw new RuntimeException('Dieses Release benötigt mindestens JTL-Shop ' . $minShop . '; installiert ist ' . $shopVersion . '.');
        }
        $manifest['requires_jtl_update'] = ($manifest['requires_jtl_update'] ?? true) === true;
        $manifest['self_update_safe'] = ($manifest['self_update_safe'] ?? false) === true;
        return $manifest;
    }

    /** @return array<string,mixed> */
    private function validateZip(string $file, string $version): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP-ZipArchive ist für die sichere Paketprüfung nicht verfügbar.');
        }
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) {
            throw new RuntimeException('Plugin-ZIP konnte nicht geöffnet werden.');
        }
        $files = 0;
        $uncompressed = 0;
        $hasInfo = false;
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $stat = $zip->statIndex($i);
            if (!is_array($stat) || !isset($stat['name'])) {
                $zip->close();
                throw new RuntimeException('ZIP enthält einen nicht lesbaren Eintrag.');
            }
            $name = str_replace('\\', '/', (string)$stat['name']);
            if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('~(^|/)\.\.(/|$)~', $name) === 1 || !str_starts_with($name, self::PLUGIN_ID . '/')) {
                $zip->close();
                throw new RuntimeException('ZIP enthält einen unsicheren oder fremden Pfad: ' . $name);
            }
            if (method_exists($zip, 'getExternalAttributesIndex')) {
                $opsys = 0;
                $attributes = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
                    $mode = ($attributes >> 16) & 0xF000;
                    if ($mode === 0xA000) {
                        $zip->close();
                        throw new RuntimeException('Symbolische Links sind im Release-ZIP nicht erlaubt.');
                    }
                }
            }
            ++$files;
            $uncompressed += (int)($stat['size'] ?? 0);
            if ($uncompressed > 80_000_000 || $files > 5000) {
                $zip->close();
                throw new RuntimeException('ZIP überschreitet die Sicherheitsgrenzen für Dateien oder entpackte Größe.');
            }
            if ($name === self::PLUGIN_ID . '/info.xml') {
                $xml = $zip->getFromIndex($i);
                if (!is_string($xml) || !$this->validateInfoXmlString($xml, $version)) {
                    $zip->close();
                    throw new RuntimeException('info.xml im Release passt nicht zu Plugin-ID oder Releaseversion.');
                }
                $hasInfo = true;
            }
        }
        $zip->close();
        if (!$hasInfo) {
            throw new RuntimeException('Release-ZIP enthält keine gültige info.xml.');
        }
        return ['files' => $files, 'uncompressed_bytes' => $uncompressed];
    }

    private function validateExtractedPlugin(string $root, string $version): void
    {
        $info = $root . DIRECTORY_SEPARATOR . 'info.xml';
        if (!is_dir($root) || !is_file($info)) {
            throw new RuntimeException('Entpacktes Plugin besitzt nicht die erwartete Struktur.');
        }
        $xml = @file_get_contents($info);
        if (!is_string($xml) || !$this->validateInfoXmlString($xml, $version)) {
            throw new RuntimeException('Entpacktes Plugin besitzt eine unpassende info.xml.');
        }
    }

    private function validateInfoXmlString(string $xml, string $version): bool
    {
        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $parsed instanceof SimpleXMLElement
            && trim((string)$parsed->PluginID) === self::PLUGIN_ID
            && trim((string)$parsed->Version) === $version;
    }

    private function installedInfoVersion(): string
    {
        $file = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'info.xml';
        $xml = @file_get_contents($file);
        if (!is_string($xml)) {
            throw new RuntimeException('Installierte info.xml ist nicht lesbar.');
        }
        $previous = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$parsed instanceof SimpleXMLElement) {
            throw new RuntimeException('Installierte info.xml ist ungültig.');
        }
        return trim((string)$parsed->Version);
    }

    private function assertTrustedReleaseUrl(string $url, string $version, string $suffix): void
    {
        $expectedPrefix = self::TRUSTED_DOWNLOAD_PREFIX . 'v' . $version . '/MGD_JTL_SEO-' . $version;
        if (!str_starts_with($url, $expectedPrefix) || !str_ends_with($url, $suffix)) {
            throw new RuntimeException('Release-URL liegt nicht im erwarteten GitHub-Pfad.');
        }
    }

    private function parseSha256(string $text): string
    {
        if (preg_match('/(?:^|\s)([a-fA-F0-9]{64})(?:\s|$)/', $text, $match) !== 1) {
            throw new RuntimeException('SHA256-Datei enthält keine gültige Prüfsumme.');
        }
        return strtolower($match[1]);
    }

    private function downloadText(string $url): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'mgd-seo-meta-');
        if (!is_string($temp)) {
            throw new RuntimeException('Temporäre Metadatei konnte nicht angelegt werden.');
        }
        try {
            $this->downloadFile($url, $temp, self::MAX_META_BYTES);
            $data = file_get_contents($temp);
            if (!is_string($data)) {
                throw new RuntimeException('Release-Metadatei konnte nicht gelesen werden.');
            }
            return $data;
        } finally {
            @unlink($temp);
        }
    }

    private function downloadFile(string $url, string $target, int $maxBytes): void
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP-cURL ist für sichere Updates erforderlich.');
        }
        $handle = fopen($target, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Temporäre Update-Datei konnte nicht geöffnet werden.');
        }
        $written = 0;
        $tooLarge = false;
        $ch = curl_init($url);
        if ($ch === false) {
            fclose($handle);
            throw new RuntimeException('Download konnte nicht initialisiert werden.');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'MGD-JTL-SEO-Updater/2.0',
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($handle, &$written, &$tooLarge, $maxBytes): int {
                $length = strlen($chunk);
                if ($written + $length > $maxBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $result = fwrite($handle, $chunk);
                if ($result === false) {
                    return 0;
                }
                $written += $result;
                return $result;
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effective = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($handle);
        if ($tooLarge) {
            @unlink($target);
            throw new RuntimeException('Release-Download überschreitet das Größenlimit.');
        }
        if ($ok === false || $status !== 200 || !$this->trustedEffectiveHost($effective)) {
            @unlink($target);
            throw new RuntimeException('Sicherer GitHub-Download fehlgeschlagen: ' . ($error !== '' ? $error : 'HTTP ' . $status));
        }
    }

    private function trustedEffectiveHost(string $url): bool
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($host === 'github.com') {
            return true;
        }
        return str_ends_with($host, '.githubusercontent.com')
            || $host === 'objects.githubusercontent.com'
            || $host === 'release-assets.githubusercontent.com';
    }

    private function makeTempDirectory(string $prefix): string
    {
        $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        for ($i = 0; $i < 5; ++$i) {
            $dir = $base . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8));
            if (@mkdir($dir, 0700)) {
                return $dir;
            }
        }
        throw new RuntimeException('Sicheres temporäres Update-Verzeichnis konnte nicht angelegt werden.');
    }

    private function deleteTree(string $path): void
    {
        if ($path === '' || !file_exists($path)) {
            return;
        }
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $this->deleteTree($path . DIRECTORY_SEPARATOR . $item);
        }
        @rmdir($path);
    }
}
