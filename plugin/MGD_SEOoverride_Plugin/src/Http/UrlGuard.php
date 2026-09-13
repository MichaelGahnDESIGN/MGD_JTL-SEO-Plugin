<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Http;

use JTL\Shop;

final class UrlGuard
{
    public static function shopOrigin(): string
    {
        $shop = rtrim(Shop::getURL(), '/');
        $scheme = strtolower((string)parse_url($shop, PHP_URL_SCHEME));
        $host = strtolower((string)parse_url($shop, PHP_URL_HOST));
        $port = parse_url($shop, PHP_URL_PORT);
        $origin = ($scheme === 'http' ? 'http' : 'https') . '://' . $host;
        if ($port !== null) {
            $origin .= ':' . (int)$port;
        }
        return $origin;
    }

    public static function isHttpUrl(string $url): bool
    {
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        $host = (string)parse_url($url, PHP_URL_HOST);
        return ($scheme === 'http' || $scheme === 'https') && $host !== '';
    }

    public static function isSameShopHost(string $url): bool
    {
        if (!self::isHttpUrl($url)) {
            return false;
        }
        $shopHost = strtolower((string)parse_url(Shop::getURL(), PHP_URL_HOST));
        $urlHost = strtolower((string)parse_url($url, PHP_URL_HOST));
        $shopPort = parse_url(Shop::getURL(), PHP_URL_PORT);
        $urlPort = parse_url($url, PHP_URL_PORT);
        return $shopHost !== '' && $shopHost === $urlHost && $shopPort === $urlPort;
    }

    public static function normalize(string $url): ?string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || preg_match('/[\r\n\x00]/', $url) === 1) {
            return null;
        }
        $lower = strtolower($url);
        foreach (['javascript:', 'data:', 'mailto:', 'tel:', 'blob:', 'file:'] as $blocked) {
            if (str_starts_with($lower, $blocked)) {
                return null;
            }
        }
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        if (($scheme !== 'http' && $scheme !== 'https') || $host === '') {
            return null;
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        $path = self::normalizePath((string)($parts['path'] ?? '/'));
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        return $scheme . '://' . $host . $port . $path . $query;
    }

    public static function resolve(string $base, string $reference): ?string
    {
        $reference = trim(html_entity_decode($reference, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($reference === '' || $reference[0] === '#') {
            return null;
        }
        if (preg_match('/^(?:javascript|data|mailto|tel|blob|file):/i', $reference) === 1) {
            return null;
        }
        if (self::isHttpUrl($reference)) {
            return self::normalize($reference);
        }
        $baseParts = parse_url($base);
        if (!is_array($baseParts) || empty($baseParts['host'])) {
            return null;
        }
        $scheme = strtolower((string)($baseParts['scheme'] ?? 'https'));
        $host = strtolower((string)$baseParts['host']);
        $port = isset($baseParts['port']) ? ':' . (int)$baseParts['port'] : '';
        if (str_starts_with($reference, '//')) {
            return self::normalize($scheme . ':' . $reference);
        }
        $query = '';
        $refPath = $reference;
        $question = strpos($reference, '?');
        if ($question !== false) {
            $refPath = substr($reference, 0, $question);
            $query = substr($reference, $question);
        }
        if ($refPath === '') {
            $refPath = (string)($baseParts['path'] ?? '/');
        } elseif (!str_starts_with($refPath, '/')) {
            $basePath = (string)($baseParts['path'] ?? '/');
            $directory = str_ends_with($basePath, '/') ? $basePath : dirname($basePath) . '/';
            $refPath = $directory . $refPath;
        }
        return self::normalize($scheme . '://' . $host . $port . self::normalizePath($refPath) . $query);
    }

    public static function withoutQuery(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }
        $scheme = (string)($parts['scheme'] ?? 'https');
        $host = (string)($parts['host'] ?? '');
        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        return $scheme . '://' . $host . $port . self::normalizePath((string)($parts['path'] ?? '/'));
    }

    private static function normalizePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }
        $leading = str_starts_with($path, '/');
        $trailing = str_ends_with($path, '/');
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        $normalized = ($leading ? '/' : '') . implode('/', $segments);
        if ($normalized === '') {
            $normalized = '/';
        } elseif ($trailing && $normalized !== '/') {
            $normalized .= '/';
        }
        return $normalized;
    }
}
