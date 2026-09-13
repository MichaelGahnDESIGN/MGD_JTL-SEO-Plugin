<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Audit;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\UrlGuard;

final class HtmlAnalyzer
{
    /** @return array<string,mixed> */
    public function analyze(string $html, string $url): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return $this->emptyResult();
        }
        $xpath = new DOMXPath($document);
        $titles = $this->textList($xpath, '//title');
        $descriptions = $this->attributeList($xpath, "//meta[translate(@name,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='description']", 'content');
        $robots = $this->attributeList($xpath, "//meta[translate(@name,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='robots']", 'content');
        $canonicals = $this->attributeList($xpath, "//link[contains(concat(' ', translate(normalize-space(@rel),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'), ' '), ' canonical ')]", 'href', $url);
        $h1 = $this->textList($xpath, '//h1');
        $htmlLang = '';
        $htmlNode = $xpath->query('/html')->item(0);
        if ($htmlNode instanceof DOMElement) {
            $htmlLang = trim($htmlNode->getAttribute('lang'));
        }

        $images = [];
        foreach ($xpath->query('//img') ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $src = trim($node->getAttribute('src'));
            if ($src === '') {
                $src = trim($node->getAttribute('data-src'));
            }
            $resolved = $src !== '' ? UrlGuard::resolve($url, $src) : null;
            $images[] = [
                'src' => $resolved ?? $src,
                'alt' => trim($node->getAttribute('alt')),
                'width' => trim($node->getAttribute('width')),
                'height' => trim($node->getAttribute('height')),
                'loading' => strtolower(trim($node->getAttribute('loading'))),
                'fetchpriority' => strtolower(trim($node->getAttribute('fetchpriority'))),
                'decoding' => strtolower(trim($node->getAttribute('decoding'))),
                'srcset' => trim($node->getAttribute('srcset')),
                'class' => trim($node->getAttribute('class')),
            ];
        }

        $backgroundImages = [];
        foreach ($xpath->query('//*[@style]') ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $style = html_entity_decode($node->getAttribute('style'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match_all('/background(?:-image)?\s*:[^;]*url\(\s*([\'\"]?)(.*?)\1\s*\)/i', $style, $matches) === false) {
                continue;
            }
            foreach ($matches[2] ?? [] as $candidate) {
                $candidate = trim((string)$candidate);
                $resolved = UrlGuard::resolve($url, $candidate);
                if ($resolved !== null) {
                    $backgroundImages[] = [
                        'src' => $resolved,
                        'tag' => strtolower($node->tagName),
                        'class' => trim($node->getAttribute('class')),
                        'id' => trim($node->getAttribute('id')),
                    ];
                }
            }
        }

        $scripts = [];
        foreach ($xpath->query('//script[@src]') ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $src = UrlGuard::resolve($url, trim($node->getAttribute('src')));
            if ($src === null) {
                continue;
            }
            $scripts[] = [
                'src' => $src,
                'async' => $node->hasAttribute('async'),
                'defer' => $node->hasAttribute('defer'),
                'type' => strtolower(trim($node->getAttribute('type'))),
                'module' => strtolower(trim($node->getAttribute('type'))) === 'module',
            ];
        }

        $stylesheets = [];
        foreach ($xpath->query("//link[contains(concat(' ', translate(normalize-space(@rel),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'), ' '), ' stylesheet ')]") ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $href = UrlGuard::resolve($url, trim($node->getAttribute('href')));
            if ($href !== null) {
                $stylesheets[] = ['href' => $href, 'media' => trim($node->getAttribute('media'))];
            }
        }

        $links = [];
        foreach ($xpath->query('//a[@href]') ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $href = UrlGuard::resolve($url, trim($node->getAttribute('href')));
            if ($href === null) {
                continue;
            }
            $rel = strtolower(trim($node->getAttribute('rel')));
            $links[] = [
                'href' => $href,
                'text' => trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? ''),
                'nofollow' => preg_match('/(?:^|\s)nofollow(?:\s|$)/', $rel) === 1,
            ];
        }

        $jsonLd = [];
        $jsonLdTypes = [];
        $invalidJsonLd = 0;
        foreach ($xpath->query("//script[translate(@type,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='application/ld+json']") ?: [] as $node) {
            $raw = trim($node->textContent);
            if ($raw === '') {
                continue;
            }
            try {
                $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
                $types = [];
                $this->collectJsonLdTypes($data, $types);
                $jsonLd[] = ['types' => array_values(array_unique($types)), 'valid' => true];
                $jsonLdTypes = array_merge($jsonLdTypes, $types);
            } catch (\JsonException) {
                ++$invalidJsonLd;
                $jsonLd[] = ['types' => [], 'valid' => false];
            }
        }

        return [
            'title' => $titles[0] ?? '',
            'titles' => $titles,
            'description' => $descriptions[0] ?? '',
            'descriptions' => $descriptions,
            'canonical' => $canonicals[0] ?? '',
            'canonicals' => $canonicals,
            'robots' => strtolower($robots[0] ?? ''),
            'robots_all' => array_map('strtolower', $robots),
            'h1' => $h1,
            'html_lang' => $htmlLang,
            'images' => $images,
            'background_images' => $backgroundImages,
            'scripts' => $scripts,
            'stylesheets' => $stylesheets,
            'links' => $links,
            'jsonld' => $jsonLd,
            'jsonld_types' => array_values(array_unique($jsonLdTypes)),
            'invalid_jsonld' => $invalidJsonLd,
        ];
    }

    /** @return list<string> */
    private function textList(DOMXPath $xpath, string $query): array
    {
        $result = [];
        foreach ($xpath->query($query) ?: [] as $node) {
            $text = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
            if ($text !== '') {
                $result[] = $text;
            }
        }
        return $result;
    }

    /** @return list<string> */
    private function attributeList(DOMXPath $xpath, string $query, string $attribute, ?string $base = null): array
    {
        $result = [];
        foreach ($xpath->query($query) ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $value = trim($node->getAttribute($attribute));
            if ($value === '') {
                continue;
            }
            if ($base !== null && in_array($attribute, ['href', 'src'], true)) {
                $value = UrlGuard::resolve($base, $value) ?? $value;
            }
            $result[] = $value;
        }
        return $result;
    }

    private function collectJsonLdTypes(mixed $value, array &$types): void
    {
        if (!is_array($value)) {
            return;
        }
        if (isset($value['@type'])) {
            $raw = $value['@type'];
            if (is_string($raw) && $raw !== '') {
                $types[] = $raw;
            } elseif (is_array($raw)) {
                foreach ($raw as $type) {
                    if (is_string($type) && $type !== '') {
                        $types[] = $type;
                    }
                }
            }
        }
        foreach ($value as $child) {
            if (is_array($child)) {
                $this->collectJsonLdTypes($child, $types);
            }
        }
    }

    /** @return array<string,mixed> */
    private function emptyResult(): array
    {
        return [
            'title' => '', 'titles' => [], 'description' => '', 'descriptions' => [],
            'canonical' => '', 'canonicals' => [], 'robots' => '', 'robots_all' => [],
            'h1' => [], 'html_lang' => '', 'images' => [], 'background_images' => [],
            'scripts' => [], 'stylesheets' => [], 'links' => [], 'jsonld' => [],
            'jsonld_types' => [], 'invalid_jsonld' => 0,
        ];
    }
}
