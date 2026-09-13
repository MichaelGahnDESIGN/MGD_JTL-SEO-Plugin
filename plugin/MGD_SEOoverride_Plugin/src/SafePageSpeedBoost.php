<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src;

use JTL\Shop;

/**
 * Conservative, template-agnostic PageSpeed improvements.
 *
 * The class intentionally avoids moving core JTL scripts or rewriting large
 * theme stylesheets. It focuses on low-risk hints that improve LCP/CLS and
 * reduce unnecessary image work below the fold.
 */
final class SafePageSpeedBoost
{
    private array $options;
    private bool $preloadHeaderSent = false;

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    public function optimize(): void
    {
        if (($this->options['enabled'] ?? false) !== true) {
            return;
        }

        if (Shop::getPageType() === \PAGE_STARTSEITE) {
            $this->enhanceHomeLcpDiscovery();
        }

        $this->addIntrinsicImageDimensions();
        $this->optimizeBelowFoldImages();
    }

    private function enhanceHomeLcpDiscovery(): void
    {
        $candidate = $this->findOpcImageCandidate();
        if ($candidate === null) {
            return;
        }

        $absolute = $this->absoluteUrl($candidate);
        if (!$this->isSafeResourceUrl($absolute) || !$this->isImageUrl($absolute)) {
            return;
        }

        if (!$this->hasImagePreload($absolute)) {
            $mime = $this->imageMimeType($absolute);
            $html = '<link rel="preload" as="image" href="'
                . htmlspecialchars($absolute, ENT_QUOTES, 'UTF-8')
                . '" fetchpriority="high"';
            if ($mime !== null) {
                $html .= ' type="' . htmlspecialchars($mime, ENT_QUOTES, 'UTF-8') . '"';
            }
            $html .= ' data-mgd-jtl-seo="safe-lcp-preload">';
            \pq('head')->prepend("\n" . $html . "\n");
        }

        if (!$this->preloadHeaderSent && !headers_sent() && strpbrk($absolute, "\r\n") === false) {
            header('Link: <' . $absolute . '>; rel=preload; as=image; fetchpriority=high', false);
            $this->preloadHeaderSent = true;
        }
    }

    private function findOpcImageCandidate(): ?string
    {
        foreach (\pq('.opc-Container') as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $preferred = ['style', 'data-bg', 'data-background', 'data-background-image', 'data-src', 'data-image'];
            foreach ($preferred as $attribute) {
                $value = trim((string)$node->getAttribute($attribute));
                $url = $this->extractImageUrlFromText($value);
                if ($url !== null) {
                    return $url;
                }
            }

            if ($node->hasAttributes()) {
                foreach ($node->attributes as $attribute) {
                    $url = $this->extractImageUrlFromText((string)$attribute->nodeValue);
                    if ($url !== null) {
                        return $url;
                    }
                }
            }
        }

        foreach (\pq('style') as $styleNode) {
            $css = (string)($styleNode->textContent ?? '');
            if ($css === '' || stripos($css, 'opc-Container') === false) {
                continue;
            }

            if (preg_match_all('/[^{}]*opc-Container[^{}]*\{[^{}]*\}/is', $css, $rules) !== false) {
                foreach ($rules[0] ?? [] as $rule) {
                    $url = $this->extractImageUrlFromText((string)$rule);
                    if ($url !== null) {
                        return $url;
                    }
                }
            }
        }

        return null;
    }

    private function extractImageUrlFromText(string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match_all('/url\(\s*([\'\"]?)(.*?)\1\s*\)/i', $text, $matches) !== false) {
            foreach ($matches[2] ?? [] as $match) {
                $url = trim((string)$match);
                if ($this->isSafeResourceUrl($url) && $this->isImageUrl($url)) {
                    return $url;
                }
            }
        }

        $plain = trim($text, " \t\n\r\0\x0B\"'");
        if ($this->isSafeResourceUrl($plain) && $this->isImageUrl($plain)) {
            return $plain;
        }

        return null;
    }

    private function addIntrinsicImageDimensions(): void
    {
        $processed = 0;
        foreach (\pq('img') as $node) {
            if (++$processed > 100) {
                break;
            }

            $image = \pq($node);
            if ($image->attr('width') !== null || $image->attr('height') !== null) {
                continue;
            }

            $src = $this->imageSource($image);
            if ($src === '' || !$this->isImageUrl($src)) {
                continue;
            }

            $path = $this->localFileForUrl($src);
            if ($path === null || !is_readable($path)) {
                continue;
            }

            $size = @getimagesize($path);
            if (!is_array($size) || !isset($size[0], $size[1])) {
                continue;
            }

            $width = (int)$size[0];
            $height = (int)$size[1];
            if ($width < 1 || $height < 1 || $width > 20000 || $height > 20000) {
                continue;
            }

            $image->attr('width', (string)$width);
            $image->attr('height', (string)$height);
            $image->attr('data-mgd-jtl-seo-dimensions', '1');
        }
    }

    private function optimizeBelowFoldImages(): void
    {
        $skip = max(2, min(12, (int)($this->options['skip_images'] ?? 4)));
        $index = 0;

        foreach (\pq('main img, #content img') as $node) {
            ++$index;
            $image = \pq($node);

            if ($image->attr('decoding') === null) {
                $image->attr('decoding', 'async');
            }

            if ($index <= $skip) {
                continue;
            }

            if ($image->attr('loading') === null) {
                $image->attr('loading', 'lazy');
            }
            if ($image->attr('fetchpriority') === null) {
                $image->attr('fetchpriority', 'low');
            }
        }
    }

    private function imageSource($image): string
    {
        foreach (['src', 'data-src', 'data-lazy-src'] as $attribute) {
            $value = trim((string)$image->attr($attribute));
            if ($value !== '') {
                return $value;
            }
        }

        foreach (['srcset', 'data-srcset'] as $attribute) {
            $value = trim((string)$image->attr($attribute));
            if ($value === '') {
                continue;
            }
            $first = trim(explode(',', $value)[0] ?? '');
            if ($first !== '') {
                return trim((string)(preg_split('/\s+/', $first)[0] ?? ''));
            }
        }

        return '';
    }

    private function hasImagePreload(string $url): bool
    {
        $target = $this->absoluteUrl($url);
        foreach (\pq('head link[rel="preload"]') as $node) {
            $link = \pq($node);
            if (strtolower(trim((string)$link->attr('as'))) !== 'image') {
                continue;
            }
            $href = trim((string)$link->attr('href'));
            if ($href !== '' && $this->absoluteUrl($href) === $target) {
                return true;
            }
        }
        return false;
    }

    private function localFileForUrl(string $url): ?string
    {
        if ($url === '' || !$this->isSafeResourceUrl($url)) {
            return null;
        }

        $absolute = $this->absoluteUrl($url);
        $host = strtolower((string)parse_url($absolute, PHP_URL_HOST));
        $shopHost = strtolower((string)parse_url(Shop::getURL(), PHP_URL_HOST));
        if ($host === '' || $shopHost === '' || $host !== $shopHost) {
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
        return ($scheme === 'http' || $scheme === 'https')
            && (string)parse_url($url, PHP_URL_HOST) !== '';
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

    private function isImageUrl(string $url): bool
    {
        $path = strtolower((string)parse_url($url, PHP_URL_PATH));
        return (bool)preg_match('/\.(?:avif|webp|png|jpe?g|gif)(?:$)/i', $path);
    }

    private function imageMimeType(string $url): ?string
    {
        $ext = strtolower((string)pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return match ($ext) {
            'avif' => 'image/avif',
            'webp' => 'image/webp',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => null,
        };
    }
}
