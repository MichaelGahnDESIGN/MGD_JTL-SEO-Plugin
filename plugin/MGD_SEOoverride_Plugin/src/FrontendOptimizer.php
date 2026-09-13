<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src;

use JTL\Shop;

final class FrontendOptimizer
{
    private array $options;
    private bool $preloadHeaderSent = false;

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    public function optimize(): void
    {
        if (($this->options['lcp_preload'] ?? false) === true && Shop::getPageType() === \PAGE_STARTSEITE) {
            $this->optimizeHomeLcp();
        }
        $this->addPreconnectHints();
        if (($this->options['inline_small_css'] ?? false) === true) {
            $this->inlineSmallLocalStylesheets();
        }
        if (($this->options['async_image_decode'] ?? false) === true) {
            $this->addAsyncImageDecoding();
        }
        if (($this->options['lazy_images'] ?? false) === true) {
            $this->addConservativeLazyLoading();
        }
        $this->deferSelectedScripts();
    }

    private function optimizeHomeLcp(): void
    {
        $candidate = $this->findLcpCandidate();
        if ($candidate === null || !$this->isSafeResourceUrl($candidate['url'])) {
            return;
        }
        if ($candidate['node'] !== null) {
            $image = \pq($candidate['node']);
            $image->attr('fetchpriority', 'high');
            $image->attr('loading', 'eager');
            if ($image->attr('decoding') === null) {
                $image->attr('decoding', 'async');
            }
        }
        $this->appendImagePreload($candidate['url']);
        $this->sendImagePreloadHeader($candidate['url']);
    }

    private function findLcpCandidate(): ?array
    {
        $configured = trim((string)($this->options['lcp_image_url'] ?? ''));
        if ($configured !== '') {
            return ['url' => $configured, 'node' => null];
        }

        foreach (['.opc-Container', '[style*="background-image"]'] as $selector) {
            foreach (\pq($selector) as $node) {
                $style = (string)\pq($node)->attr('style');
                $url = $this->extractBackgroundImageUrl($style);
                if ($url !== null && $this->isSafeResourceUrl($url)) {
                    return ['url' => $url, 'node' => null];
                }
            }
        }

        foreach (['main img', '#content img'] as $selector) {
            foreach (\pq($selector) as $node) {
                $image = \pq($node);
                $src = trim((string)$image->attr('src'));
                if ($src === '') {
                    $src = $this->firstSrcsetUrl((string)$image->attr('srcset'));
                }
                if ($src !== '' && $this->isSafeResourceUrl($src)) {
                    return ['url' => $src, 'node' => $node];
                }
            }
        }
        return null;
    }

    private function extractBackgroundImageUrl(string $style): ?string
    {
        if ($style === '') {
            return null;
        }
        $style = html_entity_decode($style, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (!preg_match('/background-image\s*:\s*url\(\s*([\'\"]?)(.*?)\1\s*\)/i', $style, $match)) {
            return null;
        }
        $url = trim((string)($match[2] ?? ''));
        return $url !== '' ? $url : null;
    }

    private function firstSrcsetUrl(string $srcset): string
    {
        $first = trim(explode(',', $srcset)[0] ?? '');
        return $first === '' ? '' : trim(preg_split('/\s+/', $first)[0] ?? '');
    }

    private function appendImagePreload(string $url): void
    {
        if ($this->hasPreload($url)) {
            return;
        }
        $type = $this->imageMimeType($url);
        $html = '<link rel="preload" as="image" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" fetchpriority="high"';
        if ($type !== null) {
            $html .= ' type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '"';
        }
        $html .= ' data-mgd-jtl-seo="lcp-preload">';
        \pq('head')->append("\n" . $html . "\n");
    }

    private function hasPreload(string $url): bool
    {
        $target = $this->absoluteUrl($url);
        foreach (\pq('head link[rel="preload"]') as $node) {
            $href = trim((string)\pq($node)->attr('href'));
            if ($href !== '' && $this->absoluteUrl($href) === $target) {
                return true;
            }
        }
        return false;
    }

    private function sendImagePreloadHeader(string $url): void
    {
        if ($this->preloadHeaderSent || headers_sent()) {
            return;
        }
        $absolute = $this->absoluteUrl($url);
        if (!$this->isSafeResourceUrl($absolute) || strpbrk($absolute, "\r\n") !== false) {
            return;
        }
        header('Link: <' . $absolute . '>; rel=preload; as=image', false);
        $this->preloadHeaderSent = true;
    }

    private function addPreconnectHints(): void
    {
        foreach ($this->splitLines((string)($this->options['preconnect_origins'] ?? '')) as $origin) {
            $origin = rtrim($origin, '/');
            if (!$this->isAbsoluteHttpUrl($origin) || $this->headHasHref('preconnect', $origin)) {
                continue;
            }
            \pq('head')->append("\n<link rel=\"preconnect\" href=\"" . htmlspecialchars($origin, ENT_QUOTES, 'UTF-8') . "\" crossorigin data-mgd-jtl-seo=\"preconnect\">\n");
        }
    }

    private function headHasHref(string $rel, string $href): bool
    {
        foreach (\pq('head link') as $node) {
            $link = \pq($node);
            if (strtolower(trim((string)$link->attr('rel'))) === strtolower($rel)
                && rtrim(trim((string)$link->attr('href')), '/') === rtrim($href, '/')) {
                return true;
            }
        }
        return false;
    }

    private function inlineSmallLocalStylesheets(): void
    {
        $maxBytes = max(512, min(16384, (int)($this->options['inline_css_max_bytes'] ?? 4096)));
        foreach (\pq('head link') as $node) {
            $link = \pq($node);
            $rel = strtolower(trim((string)$link->attr('rel')));
            if (!in_array('stylesheet', preg_split('/\s+/', $rel) ?: [], true)) {
                continue;
            }
            if ($link->attr('integrity') !== null || $link->attr('crossorigin') !== null || $link->attr('disabled') !== null) {
                continue;
            }
            $path = $this->localFileForUrl(trim((string)$link->attr('href')));
            if ($path === null || !is_readable($path)) {
                continue;
            }
            $size = @filesize($path);
            if ($size === false || $size < 1 || $size > $maxBytes) {
                continue;
            }
            $css = @file_get_contents($path);
            if ($css === false || $css === '' || stripos($css, 'url(') !== false || stripos($css, '@import') !== false || stripos($css, '</style') !== false) {
                continue;
            }
            $media = trim((string)$link->attr('media'));
            $style = '<style data-mgd-jtl-seo="inline-css" data-source="' . htmlspecialchars(basename($path), ENT_QUOTES, 'UTF-8') . '"';
            if ($media !== '') {
                $style .= ' media="' . htmlspecialchars($media, ENT_QUOTES, 'UTF-8') . '"';
            }
            $style .= '>' . $css . '</style>';
            $link->replaceWith($style);
        }
    }

    private function addAsyncImageDecoding(): void
    {
        foreach (\pq('img') as $node) {
            $image = \pq($node);
            if ($image->attr('decoding') === null) {
                $image->attr('decoding', 'async');
            }
        }
    }

    private function addConservativeLazyLoading(): void
    {
        $skip = max(1, min(12, (int)($this->options['lazy_skip_images'] ?? 4)));
        $index = 0;
        foreach (\pq('main img, #content img') as $node) {
            ++$index;
            if ($index <= $skip) {
                continue;
            }
            $image = \pq($node);
            if ($image->attr('loading') === null) {
                $image->attr('loading', 'lazy');
            }
            if ($image->attr('fetchpriority') === null) {
                $image->attr('fetchpriority', 'low');
            }
        }
    }

    private function deferSelectedScripts(): void
    {
        $patterns = $this->splitLines((string)($this->options['defer_js_patterns'] ?? ''));
        if ($patterns === []) {
            return;
        }
        foreach (\pq('script[src]') as $node) {
            $script = \pq($node);
            $src = trim((string)$script->attr('src'));
            if ($src === '' || !$this->matchesAny($src, $patterns)) {
                continue;
            }
            $type = strtolower(trim((string)$script->attr('type')));
            if ($type === 'module' || $script->attr('async') !== null || $script->attr('defer') !== null) {
                continue;
            }
            $script->attr('defer', 'defer');
            $script->attr('data-mgd-jtl-seo', 'defer');
        }
    }

    private function matchesAny(string $value, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern !== '' && stripos($value, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    private function splitLines(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }
        $parts = array_map('trim', preg_split('/[\r\n,]+/', $value) ?: []);
        return array_values(array_unique(array_filter($parts, static fn(string $item): bool => $item !== '')));
    }

    private function localFileForUrl(string $url): ?string
    {
        if ($url === '' || !$this->isSafeResourceUrl($url)) {
            return null;
        }
        $absolute = $this->absoluteUrl($url);
        if (strtolower((string)parse_url($absolute, PHP_URL_HOST)) !== strtolower((string)parse_url(Shop::getURL(), PHP_URL_HOST))) {
            return null;
        }
        $urlPath = rawurldecode((string)parse_url($absolute, PHP_URL_PATH));
        $shopBasePath = rtrim((string)parse_url(Shop::getURL(), PHP_URL_PATH), '/');
        if ($shopBasePath !== '' && strpos($urlPath, $shopBasePath . '/') === 0) {
            $urlPath = substr($urlPath, strlen($shopBasePath));
        }
        $root = realpath(PFAD_ROOT);
        $candidate = $root === false ? false : realpath(PFAD_ROOT . ltrim($urlPath, '/'));
        if ($root === false || $candidate === false) {
            return null;
        }
        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return strncmp($candidate, $prefix, strlen($prefix)) === 0 ? $candidate : null;
    }

    private function isSafeResourceUrl(string $url): bool
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || strpbrk($url, "\r\n") !== false) {
            return false;
        }
        $lower = strtolower($url);
        foreach (['javascript:', 'data:', 'blob:', 'file:'] as $blocked) {
            if (strpos($lower, $blocked) === 0) {
                return false;
            }
        }
        return strpos($url, '://') === false || $this->isAbsoluteHttpUrl($url);
    }

    private function isAbsoluteHttpUrl(string $url): bool
    {
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return ($scheme === 'http' || $scheme === 'https') && (string)parse_url($url, PHP_URL_HOST) !== '';
    }

    private function absoluteUrl(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($this->isAbsoluteHttpUrl($url)) {
            return $url;
        }
        $shopUrl = rtrim(Shop::getURL(), '/');
        $scheme = (string)parse_url($shopUrl, PHP_URL_SCHEME) ?: 'https';
        if (strpos($url, '//') === 0) {
            return $scheme . ':' . $url;
        }
        if (strpos($url, '/') === 0) {
            $host = (string)parse_url($shopUrl, PHP_URL_HOST);
            $port = parse_url($shopUrl, PHP_URL_PORT);
            return $scheme . '://' . $host . ($port !== null ? ':' . (int)$port : '') . $url;
        }
        return $shopUrl . '/' . ltrim($url, '/');
    }

    private function imageMimeType(string $url): ?string
    {
        $ext = strtolower((string)pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return match ($ext) {
            'avif' => 'image/avif', 'webp' => 'image/webp', 'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', default => null,
        };
    }
}
