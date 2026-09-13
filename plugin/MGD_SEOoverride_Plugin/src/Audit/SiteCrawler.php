<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Audit;

use Plugin\MGD_SEOoverride_Plugin\Src\Http\SafeHttpClient;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\UrlGuard;

final class SiteCrawler
{
    public function __construct(
        private readonly SafeHttpClient $http,
        private readonly HtmlAnalyzer $analyzer,
    ) {}

    /** @return array<string,mixed> */
    public function crawl(int $maxPages = 50, int $maxDepth = 4): array
    {
        $maxPages = max(5, min(250, $maxPages));
        $maxDepth = max(1, min(8, $maxDepth));
        @set_time_limit(max(60, min(300, $maxPages * 3)));

        $root = UrlGuard::withoutQuery(UrlGuard::shopOrigin() . '/');
        $queue = [[$root, 0]];
        $queued = [$root => true];
        $visited = [];
        $discovered = [$root => true];
        $inlinks = [$root => 0];
        $pages = [];
        $broken = [];
        $redirectChains = [];
        $errors = [];
        $externalOrigins = [];

        while ($queue !== [] && count($visited) < $maxPages) {
            [$url, $depth] = array_shift($queue);
            if (!is_string($url) || isset($visited[$url])) {
                continue;
            }
            $visited[$url] = true;
            try {
                $response = $this->http->get($url, true, 6, SafeHttpClient::MAX_HTML_BYTES, 5);
                $contentType = strtolower((string)($response['headers']['content-type'] ?? ''));
                $isHtml = $contentType === '' || str_contains($contentType, 'text/html') || str_contains($contentType, 'application/xhtml');
                $page = $isHtml ? $this->analyzer->analyze($response['body'], $response['url']) : [];
                $pages[$url] = [
                    'url' => $url,
                    'final_url' => $response['url'],
                    'status' => $response['status'],
                    'depth' => $depth,
                    'inlinks' => $inlinks[$url] ?? 0,
                    'title' => (string)($page['title'] ?? ''),
                    'description' => (string)($page['description'] ?? ''),
                    'canonical' => (string)($page['canonical'] ?? ''),
                    'robots' => (string)($page['robots'] ?? ''),
                    'h1_count' => count($page['h1'] ?? []),
                    'redirect_count' => count($response['redirects']),
                    'ttfb_ms' => (int)round($response['ttfb'] * 1000),
                    'content_type' => $contentType,
                ];
                if ($response['status'] >= 400) {
                    $broken[] = ['url' => $url, 'status' => $response['status'], 'referrers' => []];
                }
                if ($response['redirects'] !== []) {
                    $redirectChains[] = ['url' => $url, 'chain' => $response['redirects'], 'final_url' => $response['url'], 'final_status' => $response['status']];
                }
                if (!$isHtml || $depth >= $maxDepth || $response['status'] >= 400) {
                    continue;
                }
                foreach ($page['links'] ?? [] as $link) {
                    if (!is_array($link)) {
                        continue;
                    }
                    $href = (string)($link['href'] ?? '');
                    if ($href === '') {
                        continue;
                    }
                    if (!UrlGuard::isSameShopHost($href)) {
                        $host = strtolower((string)parse_url($href, PHP_URL_HOST));
                        if ($host !== '') {
                            $externalOrigins[$host] = true;
                        }
                        continue;
                    }
                    $target = UrlGuard::withoutQuery($href);
                    if (!$this->isCrawlable($target)) {
                        continue;
                    }
                    $discovered[$target] = true;
                    $inlinks[$target] = ($inlinks[$target] ?? 0) + 1;
                    if (($link['nofollow'] ?? false) === true || isset($visited[$target]) || isset($queued[$target])) {
                        continue;
                    }
                    $queued[$target] = true;
                    $queue[] = [$target, $depth + 1];
                }
            } catch (\Throwable $e) {
                $pages[$url] = [
                    'url' => $url, 'final_url' => $url, 'status' => 0, 'depth' => $depth,
                    'inlinks' => $inlinks[$url] ?? 0, 'title' => '', 'description' => '', 'canonical' => '',
                    'robots' => '', 'h1_count' => 0, 'redirect_count' => 0, 'ttfb_ms' => 0, 'content_type' => '',
                ];
                $errors[] = $url . ': ' . $e->getMessage();
            }
        }

        foreach ($pages as $url => &$row) {
            $row['inlinks'] = $inlinks[$url] ?? 0;
        }
        unset($row);

        $brokenByUrl = [];
        foreach ($pages as $row) {
            if ((int)$row['status'] >= 400 || (int)$row['status'] === 0) {
                $brokenByUrl[(string)$row['url']] = ['url' => (string)$row['url'], 'status' => (int)$row['status'], 'referrers' => []];
            }
        }
        if ($brokenByUrl !== []) {
            foreach ($pages as $sourceUrl => $row) {
                if ((int)$row['status'] !== 200) {
                    continue;
                }
                try {
                    $response = $this->http->get((string)$sourceUrl, true, 5, SafeHttpClient::MAX_HTML_BYTES, 2);
                    $page = $this->analyzer->analyze($response['body'], $response['url']);
                    foreach ($page['links'] ?? [] as $link) {
                        if (!is_array($link)) {
                            continue;
                        }
                        $target = UrlGuard::withoutQuery((string)($link['href'] ?? ''));
                        if (isset($brokenByUrl[$target]) && count($brokenByUrl[$target]['referrers']) < 10) {
                            $brokenByUrl[$target]['referrers'][] = (string)$sourceUrl;
                        }
                    }
                } catch (\Throwable) {
                    // Referrer-Ermittlung ist optional.
                }
            }
        }
        $broken = array_values($brokenByUrl);

        $sitemap = (new SitemapReader($this->http))->read(2500);
        $orphans = [];
        foreach ($sitemap['urls'] as $sitemapUrl) {
            $normalized = UrlGuard::withoutQuery($sitemapUrl);
            if (!isset($discovered[$normalized]) && count($orphans) < 200) {
                $orphans[] = $normalized;
            }
        }

        $deep = [];
        $noInlinks = [];
        $canonicalGroups = [];
        $duplicateTitles = [];
        foreach ($pages as $row) {
            if ((int)$row['depth'] > 3) {
                $deep[] = $row;
            }
            if ((int)$row['inlinks'] === 0 && (string)$row['url'] !== $root) {
                $noInlinks[] = $row;
            }
            $canonical = trim((string)$row['canonical']);
            if ($canonical !== '' && UrlGuard::isSameShopHost($canonical) && UrlGuard::withoutQuery($canonical) !== (string)$row['url']) {
                $key = UrlGuard::withoutQuery($canonical);
                $canonicalGroups[$key] ??= [];
                $canonicalGroups[$key][] = (string)$row['url'];
            }
            $title = mb_strtolower(trim((string)$row['title']));
            if ($title !== '') {
                $duplicateTitles[$title] ??= [];
                $duplicateTitles[$title][] = (string)$row['url'];
            }
        }
        $duplicateTitles = array_filter($duplicateTitles, static fn(array $urls): bool => count($urls) > 1);

        return [
            'root' => $root,
            'max_pages' => $maxPages,
            'max_depth' => $maxDepth,
            'pages' => array_values($pages),
            'visited_count' => count($pages),
            'discovered_count' => count($discovered),
            'queue_remaining' => count($queue),
            'broken' => $broken,
            'redirect_chains' => $redirectChains,
            'deep_pages' => $deep,
            'no_inlinks' => $noInlinks,
            'orphan_candidates' => $orphans,
            'canonical_groups' => $canonicalGroups,
            'duplicate_titles' => $duplicateTitles,
            'sitemap' => $sitemap,
            'external_origins' => array_keys($externalOrigins),
            'errors' => $errors,
            'truncated' => count($pages) >= $maxPages || $queue !== [],
        ];
    }

    private function isCrawlable(string $url): bool
    {
        $path = strtolower((string)parse_url($url, PHP_URL_PATH));
        if ($path === '' || str_starts_with($path, '/admin') || str_starts_with($path, '/plugins/') || str_starts_with($path, '/includes/')) {
            return false;
        }
        $extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
        if ($extension !== '' && !in_array($extension, ['html', 'htm', 'php'], true)) {
            return false;
        }
        foreach (['/warenkorb', '/bestellvorgang', '/registrieren', '/pass.php', '/io.php', '/newsletter.php'] as $blocked) {
            if (str_contains($path, $blocked)) {
                return false;
            }
        }
        return true;
    }
}
