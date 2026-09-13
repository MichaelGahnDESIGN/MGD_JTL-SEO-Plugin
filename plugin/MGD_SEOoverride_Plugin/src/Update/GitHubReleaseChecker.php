<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Update;

final class GitHubReleaseChecker
{
    private const ENDPOINT = 'https://api.github.com/repos/MichaelGahnDESIGN/MGD_JTL-SEO-Plugin/releases/latest';
    private const RELEASE_PREFIX = 'https://github.com/MichaelGahnDESIGN/MGD_JTL-SEO-Plugin/releases/tag/';
    private const DOWNLOAD_PREFIX = 'https://github.com/MichaelGahnDESIGN/MGD_JTL-SEO-Plugin/releases/download/';
    private const CACHE_SECONDS = 43_200;
    private const MAX_BYTES = 262_144;

    /** @return array<string,mixed> */
    public function check(string $currentVersion, bool $enabled = true, bool $force = false): array
    {
        $empty = [
            'ok' => false,
            'update' => false,
            'current' => $currentVersion,
            'latest' => null,
            'release_url' => null,
            'download_url' => null,
            'sha256_url' => null,
            'manifest_url' => null,
            'asset_digest' => null,
            'published_at' => null,
            'error' => null,
        ];
        if (!$enabled) {
            $empty['error'] = 'Updateprüfung ist deaktiviert.';
            return $empty;
        }
        if (!preg_match('/^\d+\.\d+\.\d+$/D', $currentVersion)) {
            $empty['error'] = 'Ungültige lokale Versionsnummer.';
            return $empty;
        }

        $cacheFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'mgd-jtl-seo-' . md5((string)(defined('PFAD_ROOT') ? PFAD_ROOT : __DIR__)) . '.json';
        if (!$force) {
            $cached = $this->readCache($cacheFile);
            if ($cached !== null && time() - (int)($cached['checked_at'] ?? 0) < self::CACHE_SECONDS) {
                return $this->resultFromRelease($currentVersion, $cached['release'] ?? null);
            }
        }

        $release = $this->fetchRelease();
        @file_put_contents(
            $cacheFile,
            json_encode(['checked_at' => time(), 'release' => $release], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
            LOCK_EX,
        );
        if ($release === null) {
            $empty['error'] = 'GitHub konnte momentan nicht sicher geprüft werden.';
            return $empty;
        }
        return $this->resultFromRelease($currentVersion, $release);
    }

    /** @return array<string,mixed>|null */
    private function fetchRelease(): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $body = '';
        $tooLarge = false;
        $ch = curl_init(self::ENDPOINT);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json', 'User-Agent: MGD-JTL-SEO/2.0'],
            CURLOPT_HEADER => false,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
                if (strlen($body) + strlen($chunk) > self::MAX_BYTES) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($ok === false || $tooLarge || $status !== 200 || $body === '') {
            return null;
        }
        try {
            $data = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($data) || ($data['draft'] ?? true) !== false || ($data['prerelease'] ?? true) !== false) {
            return null;
        }
        $tag = $data['tag_name'] ?? null;
        $htmlUrl = $data['html_url'] ?? null;
        if (!is_string($tag) || preg_match('/^v\d+\.\d+\.\d+$/D', $tag) !== 1 || !is_string($htmlUrl) || $htmlUrl !== self::RELEASE_PREFIX . $tag) {
            return null;
        }

        $version = substr($tag, 1);
        $zipName = 'MGD_JTL_SEO-' . $version . '.zip';
        $shaName = $zipName . '.sha256';
        $manifestName = 'MGD_JTL_SEO-' . $version . '.manifest.json';
        $download = null;
        $sha256 = null;
        $manifest = null;
        $digest = null;
        foreach (($data['assets'] ?? []) as $asset) {
            if (!is_array($asset)) {
                continue;
            }
            $name = (string)($asset['name'] ?? '');
            $url = (string)($asset['browser_download_url'] ?? '');
            if ($url === '' || !str_starts_with($url, self::DOWNLOAD_PREFIX . $tag . '/')) {
                continue;
            }
            if ($name === $zipName) {
                $download = $url;
                $rawDigest = $asset['digest'] ?? null;
                if (is_string($rawDigest) && preg_match('/^sha256:[a-f0-9]{64}$/D', $rawDigest) === 1) {
                    $digest = substr($rawDigest, 7);
                }
            } elseif ($name === $shaName) {
                $sha256 = $url;
            } elseif ($name === $manifestName) {
                $manifest = $url;
            }
        }
        return [
            'tag' => $tag,
            'url' => $htmlUrl,
            'download' => $download,
            'sha256' => $sha256,
            'manifest' => $manifest,
            'asset_digest' => $digest,
            'published_at' => is_string($data['published_at'] ?? null) ? $data['published_at'] : null,
        ];
    }

    /** @return array<string,mixed> */
    private function resultFromRelease(string $currentVersion, mixed $release): array
    {
        if (!is_array($release) || !isset($release['tag'], $release['url'])) {
            return [
                'ok' => false, 'update' => false, 'current' => $currentVersion, 'latest' => null,
                'release_url' => null, 'download_url' => null, 'sha256_url' => null,
                'manifest_url' => null, 'asset_digest' => null, 'published_at' => null,
                'error' => 'Keine gültigen Release-Daten verfügbar.',
            ];
        }
        $latest = ltrim((string)$release['tag'], 'v');
        if (preg_match('/^\d+\.\d+\.\d+$/D', $latest) !== 1) {
            return [
                'ok' => false, 'update' => false, 'current' => $currentVersion, 'latest' => null,
                'release_url' => null, 'download_url' => null, 'sha256_url' => null,
                'manifest_url' => null, 'asset_digest' => null, 'published_at' => null,
                'error' => 'GitHub lieferte eine ungültige Versionsnummer.',
            ];
        }
        return [
            'ok' => true,
            'update' => version_compare($latest, $currentVersion, '>'),
            'current' => $currentVersion,
            'latest' => $latest,
            'release_url' => (string)$release['url'],
            'download_url' => isset($release['download']) && is_string($release['download']) ? $release['download'] : null,
            'sha256_url' => isset($release['sha256']) && is_string($release['sha256']) ? $release['sha256'] : null,
            'manifest_url' => isset($release['manifest']) && is_string($release['manifest']) ? $release['manifest'] : null,
            'asset_digest' => isset($release['asset_digest']) && is_string($release['asset_digest']) ? $release['asset_digest'] : null,
            'published_at' => isset($release['published_at']) && is_string($release['published_at']) ? $release['published_at'] : null,
            'error' => null,
        ];
    }

    /** @return array<string,mixed>|null */
    private function readCache(string $file): ?array
    {
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || strlen($raw) > self::MAX_BYTES) {
            return null;
        }
        try {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return is_array($data) ? $data : null;
    }
}
