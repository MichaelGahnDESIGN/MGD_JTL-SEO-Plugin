<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Audit;

use JTL\Shop;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\SafeHttpClient;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\UrlGuard;

final class ServerDiagnostics
{
    public function __construct(private readonly SafeHttpClient $http) {}

    /** @param array<string,mixed> $page @return array<string,mixed> */
    public function analyze(array $page): array
    {
        $origin = UrlGuard::shopOrigin();
        $response = $this->http->get($origin . '/', true, 10);
        $headers = $response['headers'];
        $issues = [];
        $ttfbMs = (int)round($response['ttfb'] * 1000);
        if ($ttfbMs > 1800) {
            $issues[] = $this->issue('error', 'Hohe Server-Antwortzeit', 'Die gemessene TTFB lag bei etwa ' . $ttfbMs . ' ms. Das kann LCP und FCP deutlich verschlechtern.');
        } elseif ($ttfbMs > 800) {
            $issues[] = $this->issue('warning', 'Server-Antwortzeit prüfen', 'Die gemessene TTFB lag bei etwa ' . $ttfbMs . ' ms.');
        }

        $encoding = strtolower((string)($headers['content-encoding'] ?? ''));
        if ($encoding === '' && strlen($response['body']) > 10_000) {
            $issues[] = $this->issue('warning', 'HTML ohne erkennbare Kompression', 'Für die HTML-Antwort wurde kein Content-Encoding-Header erkannt. Prüfe Brotli oder Gzip am Webserver.');
        }
        if ($response['http_version'] === 'HTTP/1.0' || $response['http_version'] === 'HTTP/1.1') {
            $issues[] = $this->issue('info', 'HTTP-Protokoll', 'Der Prüflauf nutzte ' . $response['http_version'] . '. HTTP/2 oder HTTP/3 kann parallele Asset-Anfragen effizienter transportieren.');
        }

        $assets = [];
        foreach ($page['stylesheets'] ?? [] as $asset) {
            if (is_array($asset) && isset($asset['href'])) {
                $assets[] = (string)$asset['href'];
            }
        }
        foreach ($page['scripts'] ?? [] as $asset) {
            if (is_array($asset) && isset($asset['src'])) {
                $assets[] = (string)$asset['src'];
            }
        }
        foreach ($page['images'] ?? [] as $asset) {
            if (is_array($asset) && isset($asset['src'])) {
                $assets[] = (string)$asset['src'];
            }
        }
        foreach ($page['background_images'] ?? [] as $asset) {
            if (is_array($asset) && isset($asset['src'])) {
                $assets[] = (string)$asset['src'];
            }
        }
        $assets = array_values(array_unique(array_filter($assets)));

        $thirdParty = [];
        $staticSamples = [];
        foreach ($assets as $assetUrl) {
            $host = strtolower((string)parse_url($assetUrl, PHP_URL_HOST));
            if ($host === '') {
                continue;
            }
            if (!UrlGuard::isSameShopHost($assetUrl)) {
                $scheme = strtolower((string)parse_url($assetUrl, PHP_URL_SCHEME));
                $port = parse_url($assetUrl, PHP_URL_PORT);
                $thirdParty[($scheme === 'http' ? 'http' : 'https') . '://' . $host . ($port !== null ? ':' . (int)$port : '')] = true;
                continue;
            }
            if (count($staticSamples) >= 24) {
                continue;
            }
            try {
                $assetResponse = $this->http->head($assetUrl, true, 5, 2);
                $cacheControl = (string)($assetResponse['headers']['cache-control'] ?? '');
                $expires = (string)($assetResponse['headers']['expires'] ?? '');
                $maxAge = $this->maxAge($cacheControl);
                $staticSamples[] = [
                    'url' => $assetUrl,
                    'status' => $assetResponse['status'],
                    'cache_control' => $cacheControl,
                    'expires' => $expires,
                    'max_age' => $maxAge,
                    'content_type' => (string)($assetResponse['headers']['content-type'] ?? ''),
                    'content_length' => (string)($assetResponse['headers']['content-length'] ?? ''),
                ];
            } catch (\Throwable) {
                // Einzelne Asset-Fehler sollen die Gesamtdiagnose nicht abbrechen.
            }
        }

        $shortCache = 0;
        $noCacheHeader = 0;
        foreach ($staticSamples as $sample) {
            $maxAge = $sample['max_age'];
            if ($maxAge === null) {
                ++$noCacheHeader;
            } elseif ($maxAge < 604800) {
                ++$shortCache;
            }
        }
        if ($shortCache > 0) {
            $issues[] = $this->issue('warning', 'Kurze Browser-Cachezeiten', $shortCache . ' der geprüften statischen Ressourcen haben max-age unter sieben Tagen. Versionierte Assets können meist deutlich länger gecacht werden.');
        }
        if ($noCacheHeader > 0) {
            $issues[] = $this->issue('info', 'Cache-Control fehlt', $noCacheHeader . ' der geprüften statischen Ressourcen hatten keinen auswertbaren max-age-Wert.');
        }
        if (count($thirdParty) > 4) {
            $issues[] = $this->issue('warning', 'Viele Drittanbieter-Ursprünge', count($thirdParty) . ' externe Origins wurden in frühen Assets erkannt. Jeder zusätzliche Anbieter kann DNS, TLS, Datenschutz und Main-Thread-Kosten erhöhen.');
        }

        $cache = Shop::Container()->getCache();
        $cacheClass = get_class($cache);
        $cacheMethod = '';
        foreach (['getMethod', 'getCacheMethod', 'getType'] as $method) {
            if (!method_exists($cache, $method)) {
                continue;
            }
            try {
                $value = $cache->{$method}();
                if (is_scalar($value)) {
                    $cacheMethod = (string)$value;
                    break;
                }
                if (is_object($value)) {
                    $cacheMethod = get_class($value);
                    break;
                }
            } catch (\Throwable) {
                // Nur Diagnose, keine harte Abhängigkeit von internen APIs.
            }
        }

        $opcacheEnabled = false;
        if (function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            $opcacheEnabled = is_array($status) && (($status['opcache_enabled'] ?? false) === true);
        }

        return [
            'status' => $response['status'],
            'ttfb_ms' => $ttfbMs,
            'total_ms' => (int)round($response['total_time'] * 1000),
            'http_version' => $response['http_version'],
            'content_encoding' => $encoding !== '' ? $encoding : 'keins erkannt',
            'server' => (string)($headers['server'] ?? ''),
            'cache_control_html' => (string)($headers['cache-control'] ?? ''),
            'php_version' => PHP_VERSION,
            'shop_version' => defined('APPLICATION_VERSION') ? (string)APPLICATION_VERSION : 'unbekannt',
            'cache_class' => $cacheClass,
            'cache_method' => $cacheMethod,
            'opcache_enabled' => $opcacheEnabled,
            'memory_limit' => (string)ini_get('memory_limit'),
            'static_samples' => $staticSamples,
            'third_party_origins' => array_keys($thirdParty),
            'issues' => $issues,
        ];
    }

    private function maxAge(string $cacheControl): ?int
    {
        if (preg_match('/(?:^|,)\s*(?:s-maxage|max-age)\s*=\s*(\d+)/i', $cacheControl, $match) !== 1) {
            return null;
        }
        return min(PHP_INT_MAX, (int)$match[1]);
    }

    /** @return array{severity:string,title:string,message:string} */
    private function issue(string $severity, string $title, string $message): array
    {
        return ['severity' => $severity, 'title' => $title, 'message' => $message];
    }
}
