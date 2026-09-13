<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Audit;

use Plugin\MGD_SEOoverride_Plugin\Src\Http\SafeHttpClient;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\UrlGuard;

final class CssAudit
{
    public function __construct(private readonly SafeHttpClient $http) {}

    /** @param array<string,mixed> $page @return array<string,mixed> */
    public function analyze(array $page, int $probeLimit = 25): array
    {
        $stylesheets = is_array($page['stylesheets'] ?? null) ? $page['stylesheets'] : [];
        $rows = [];
        $issues = [];
        $blocking = 0;
        $knownBytes = 0;
        $large = 0;
        $shortCache = 0;
        $probed = 0;

        foreach ($stylesheets as $stylesheet) {
            if (!is_array($stylesheet)) {
                continue;
            }
            $href = trim((string)($stylesheet['href'] ?? ''));
            if ($href === '') {
                continue;
            }
            $media = strtolower(trim((string)($stylesheet['media'] ?? '')));
            $sameHost = UrlGuard::isSameShopHost($href);
            $isBlocking = $media === '' || $media === 'all' || $media === 'screen';
            if ($isBlocking) {
                ++$blocking;
            }

            $bytes = null;
            $cache = '';
            $status = null;
            if ($sameHost && $probed < $probeLimit) {
                ++$probed;
                try {
                    $meta = $this->http->head($href, true, 5, 2);
                    $status = $meta['status'];
                    $length = trim((string)($meta['headers']['content-length'] ?? ''));
                    if ($length !== '' && ctype_digit($length)) {
                        $bytes = (int)$length;
                        $knownBytes += $bytes;
                        if ($bytes > 50_000) {
                            ++$large;
                        }
                    }
                    $cache = (string)($meta['headers']['cache-control'] ?? '');
                    $maxAge = $this->maxAge($cache);
                    if ($maxAge !== null && $maxAge < 604_800) {
                        ++$shortCache;
                    }
                } catch (\Throwable) {
                    // Metadaten sind optional.
                }
            }

            $rows[] = [
                'href' => $href,
                'same_host' => $sameHost,
                'media' => $media,
                'blocking' => $isBlocking,
                'bytes' => $bytes,
                'cache_control' => $cache,
                'status' => $status,
            ];
        }

        if ($blocking > 0) {
            $issues[] = $this->issue('info', 'Renderblockierende Stylesheets', $blocking . ' Stylesheet(s) werden im normalen Renderpfad geladen. Kleine lokale Dateien können inline eingebettet werden; große Hauptstylesheets sollten nur mit vorhandenem Critical CSS nachgeladen werden.');
        }
        if ($large > 0) {
            $issues[] = $this->issue('warning', 'Große CSS-Dateien', $large . ' lokales Stylesheet(s) überschreiten 50 KB. Lighthouse kann darin viel ungenutztes CSS melden, obwohl Regeln später für Navigation, Modals, Varianten oder Checkout benötigt werden.');
        }
        if ($shortCache > 0) {
            $issues[] = $this->issue('info', 'Kurze CSS-Cachezeit', $shortCache . ' Stylesheet(s) besitzen weniger als sieben Tage max-age. Bei versionierten Assets sind längere Browser-Caches möglich.');
        }

        return [
            'rows' => $rows,
            'issues' => $issues,
            'blocking_count' => $blocking,
            'known_bytes' => $knownBytes,
            'large_count' => $large,
            'short_cache_count' => $shortCache,
        ];
    }

    private function maxAge(string $cacheControl): ?int
    {
        return preg_match('/(?:^|,)\s*max-age\s*=\s*(\d+)/i', $cacheControl, $match) === 1
            ? (int)$match[1]
            : null;
    }

    /** @return array{severity:string,title:string,message:string} */
    private function issue(string $severity, string $title, string $message): array
    {
        return ['severity' => $severity, 'title' => $title, 'message' => $message];
    }
}
