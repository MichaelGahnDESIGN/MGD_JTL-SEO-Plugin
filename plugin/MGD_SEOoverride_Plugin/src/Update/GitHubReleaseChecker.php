<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Update;

final class GitHubReleaseChecker
{
    private const ENDPOINT = 'https://api.github.com/repos/MichaelGahnDESIGN/MGD_JTL-SEO-Plugin/releases/latest';
    private const RELEASE_PREFIX = 'https://github.com/MichaelGahnDESIGN/MGD_JTL-SEO-Plugin/releases/tag/';
    private const DOWNLOAD_PREFIX = 'https://github.com/MichaelGahnDESIGN/MGD_JTL-SEO-Plugin/releases/download/';
    private const CACHE_SECONDS = 43200;
    private const MAX_BYTES = 131072;

    public function check(string $currentVersion, bool $enabled = true, bool $force = false): array
    {
        $empty = ['ok' => false, 'update' => false, 'current' => $currentVersion, 'latest' => null, 'release_url' => null, 'download_url' => null, 'error' => null];
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
        @file_put_contents($cacheFile, json_encode(['checked_at' => time(), 'release' => $release], JSON_UNESCAPED_SLASHES), LOCK_EX);
        if ($release === null) {
            $empty['error'] = 'GitHub konnte momentan nicht sicher geprüft werden.';
            return $empty;
        }
        return $this->resultFromRelease($currentVersion, $release);
    }

    private function fetchRelease(): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init(self::ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json', 'User-Agent: MGD-JTL-SEO/1.1.0'],
            CURLOPT_HEADER => false,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($body) || $status !== 200 || strlen($body) > self::MAX_BYTES) {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || ($data['draft'] ?? true) !== false || ($data['prerelease'] ?? true) !== false) {
            return null;
        }
        $tag = $data['tag_name'] ?? null;
        $htmlUrl = $data['html_url'] ?? null;
        if (!is_string($tag) || preg_match('/^v\d+\.\d+\.\d+$/D', $tag) !== 1 || !is_string($htmlUrl) || $htmlUrl !== self::RELEASE_PREFIX . $tag) {
            return null;
        }
        $download = null;
        foreach (($data['assets'] ?? []) as $asset) {
            if (!is_array($asset)) {
                continue;
            }
            $name = (string)($asset['name'] ?? '');
            $url = (string)($asset['browser_download_url'] ?? '');
            if (preg_match('/^MGD_JTL_SEO-\d+\.\d+\.\d+\.zip$/D', $name) === 1 && str_starts_with($url, self::DOWNLOAD_PREFIX . $tag . '/')) {
                $download = $url;
                break;
            }
        }
        return ['tag' => $tag, 'url' => $htmlUrl, 'download' => $download];
    }

    private function resultFromRelease(string $currentVersion, mixed $release): array
    {
        if (!is_array($release) || !isset($release['tag'], $release['url'])) {
            return ['ok' => false, 'update' => false, 'current' => $currentVersion, 'latest' => null, 'release_url' => null, 'download_url' => null, 'error' => 'Keine gültigen Release-Daten verfügbar.'];
        }
        $latest = ltrim((string)$release['tag'], 'v');
        return [
            'ok' => true,
            'update' => version_compare($latest, $currentVersion, '>'),
            'current' => $currentVersion,
            'latest' => $latest,
            'release_url' => (string)$release['url'],
            'download_url' => isset($release['download']) && is_string($release['download']) ? $release['download'] : null,
            'error' => null,
        ];
    }

    private function readCache(string $file): ?array
    {
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($data) ? $data : null;
    }
}
