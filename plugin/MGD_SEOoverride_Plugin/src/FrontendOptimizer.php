<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src;

use JTL\Shop;

final class FrontendOptimizer
{
    private const DELAY_BLOCKLIST = [
        'paypal', 'payment', 'klarna', 'amazon-pay', 'stripe', 'mollie', 'checkout',
        'warenkorb', 'basket', 'consent', 'cookie', 'captcha', 'recaptcha',
    ];

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
        $this->injectCriticalCss();

        if (($this->options['inline_small_css'] ?? false) === true) {
            $this->inlineSmallLocalStylesheets();
        }

        $this->deferSelectedStylesheets();

        if (($this->options['async_image_decode'] ?? false) === true) {
            $this->addAsyncImageDecoding();
        }
        if (($this->options['lazy_images'] ?? false) === true) {
            $this->addConservativeLazyLoading();
        }

        $this->deferSelectedScripts();
        $this->delaySelectedThirdPartyScripts();
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

    private function injectCriticalCss(): void
    {
        $css = trim((string)($this->options['critical_css'] ?? ''));
        if ($css === '' || strlen($css) > 65_536 || stripos($css, '</style') !== false) {
            return;
        }
        \pq('head')->append("\n<style data-mgd-jtl-seo=\"critical-css\">" . $css . "</style>\n");
    }

    private function inlineSmallLocalStylesheets(): void
    {
        $maxBytes = max(512, min(16384, (int)($this->options['inline_css_max_bytes'] ?? 8192)));
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

    private function deferSelectedStylesheets(): void
    {
        $criticalCss = trim((string)($this->options['critical_css'] ?? ''));
        $patterns = $this->splitLines((string)($this->options['async_css_patterns'] ?? ''));
        if ($criticalCss === '' || $patterns === []) {
            return;
        }

        foreach (\pq('head link') as $node) {
            $link = \pq($node);
            $rel = strtolower(trim((string)$link->attr('rel')));
            $href = trim((string)$link->attr('href'));
            $media = strtolower(trim((string)$link->attr('media')));
            if (!in_array('stylesheet', preg_split('/\s+/', $rel) ?: [], true)
                || $href === ''
                || !$this->matchesAny($href, $patterns)
                || ($media !== '' && $media !== 'all')
                || $this->localFileForUrl($href) === null
            ) {
                continue;
            }

            $escaped = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
            $fallback = '<noscript data-mgd-jtl-seo="css-fallback"><link rel="stylesheet" href="' . $escaped . '"></noscript>';
            $link->attr('rel', 'preload');
            $link->attr('as', 'style');
            $link->attr('onload', "this.onload=null;this.rel='stylesheet'");
            $link->attr('data-mgd-jtl-seo', 'async-css');
            $link->after($fallback);
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

    private function delaySelectedThirdPartyScripts(): void
    {
        $mode = strtolower(trim((string)($this->options['third_party_delay_mode'] ?? 'off')));
        $patterns = $this->splitLines((string)($this->options['third_party_delay_patterns'] ?? ''));
        if (!in_array($mode, ['idle', 'interaction', 'interaction_or_idle'], true) || $patterns === []) {
            return;
        }

        $delayed = 0;
        foreach (\pq('script[src]') as $node) {
            $script = \pq($node);
            $src = trim((string)$script->attr('src'));
            if ($src === '' || !$this->matchesAny($src, $patterns) || !$this->isThirdPartyUrl($src) || $this->isDelayBlocked($src)) {
                continue;
            }

            $originalType = trim((string)$script->attr('type'));
            $script->attr('data-mgd-src', $src);
            $script->attr('data-mgd-original-type', $originalType);
            $script->removeAttr('src');
            $script->attr('type', 'text/mgd-delayed');
            $script->attr('data-mgd-jtl-seo', 'third-party-delayed');
            ++$delayed;
        }

        if ($delayed === 0) {
            return;
        }

        $delayMs = max(500, min(15_000, (int)($this->options['third_party_idle_delay_ms'] ?? 3500)));
        $modeJson = json_encode($mode, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $delayJson = json_encode($delayMs);
        if (!is_string($modeJson) || !is_string($delayJson)) {
            return;
        }

        $loader = <<<'JS'
(function(){
'use strict';
var mode=__MODE__,wait=__WAIT__,started=false;
function start(){
 if(started){return;} started=true;
 var nodes=Array.prototype.slice.call(document.querySelectorAll('script[type="text/mgd-delayed"][data-mgd-src]'));
 function next(i){
  if(i>=nodes.length){return;}
  var p=nodes[i],s=document.createElement('script'),src=p.getAttribute('data-mgd-src'),originalType=p.getAttribute('data-mgd-original-type')||'';
  Array.prototype.slice.call(p.attributes).forEach(function(a){
   if(a.name==='type'||a.name==='data-mgd-src'||a.name==='data-mgd-original-type'||a.name==='data-mgd-jtl-seo'){return;}
   s.setAttribute(a.name,a.value);
  });
  if(originalType){s.setAttribute('type',originalType);} else {s.removeAttribute('type');}
  s.src=src; s.setAttribute('data-mgd-jtl-seo','third-party-loaded');
  s.onload=s.onerror=function(){if(p.parentNode){p.parentNode.removeChild(p);} next(i+1);};
  p.parentNode.insertBefore(s,p);
 }
 next(0);
}
function onInteraction(){start();}
if(mode==='idle'||mode==='interaction_or_idle'){
 if('requestIdleCallback' in window){window.requestIdleCallback(start,{timeout:wait});}else{window.setTimeout(start,wait);}
}
if(mode==='interaction'||mode==='interaction_or_idle'){
 ['pointerdown','touchstart','keydown'].forEach(function(e){window.addEventListener(e,onInteraction,{once:true,passive:true});});
}
})();
JS;
        $loader = str_replace(['__MODE__', '__WAIT__'], [$modeJson, $delayJson], $loader);
        \pq('body')->append("\n<script data-mgd-jtl-seo=\"third-party-loader\">" . $loader . "</script>\n");
    }

    private function isDelayBlocked(string $src): bool
    {
        $lower = strtolower($src);
        foreach (self::DELAY_BLOCKLIST as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function isThirdPartyUrl(string $url): bool
    {
        $absolute = $this->absoluteUrl($url);
        $host = strtolower((string)parse_url($absolute, PHP_URL_HOST));
        $shopHost = strtolower((string)parse_url(Shop::getURL(), PHP_URL_HOST));
        return $host !== '' && $shopHost !== '' && $host !== $shopHost;
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
        $parts = array_map('trim', preg_split('/[\r\n,;]+/', $value) ?: []);
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
