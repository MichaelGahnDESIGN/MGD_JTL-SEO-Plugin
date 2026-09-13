<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Audit;

use Plugin\MGD_SEOoverride_Plugin\Src\Http\SafeHttpClient;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\UrlGuard;

final class MediaAudit
{
    public function __construct(private readonly SafeHttpClient $http) {}

    /** @param array<string,mixed> $page @return array<string,mixed> */
    public function analyze(array $page, int $probeLimit = 60): array
    {
        $issues = [];
        $images = is_array($page['images'] ?? null) ? $page['images'] : [];
        $backgrounds = is_array($page['background_images'] ?? null) ? $page['background_images'] : [];
        $rows = [];
        $missingDimensions = 0;
        $missingAlt = 0;
        $lazyAboveFold = 0;
        $legacyFormats = 0;
        $largeFiles = 0;
        $probed = 0;

        foreach ($images as $index => $image) {
            if (!is_array($image)) {
                continue;
            }
            $src = trim((string)($image['src'] ?? ''));
            $width = trim((string)($image['width'] ?? ''));
            $height = trim((string)($image['height'] ?? ''));
            $alt = trim((string)($image['alt'] ?? ''));
            $loading = strtolower(trim((string)($image['loading'] ?? '')));
            if ($width === '' || $height === '') {
                ++$missingDimensions;
            }
            if ($alt === '') {
                ++$missingAlt;
            }
            if ($index < 4 && $loading === 'lazy') {
                ++$lazyAboveFold;
            }
            $extension = strtolower((string)pathinfo((string)parse_url($src, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                ++$legacyFormats;
            }
            $meta = $this->probe($src, $probed, $probeLimit);
            if (($meta['bytes'] ?? 0) > 250_000) {
                ++$largeFiles;
            }
            $rows[] = [
                'kind' => 'img',
                'src' => $src,
                'alt' => $alt,
                'width' => $width,
                'height' => $height,
                'loading' => $loading,
                'fetchpriority' => (string)($image['fetchpriority'] ?? ''),
                'extension' => $extension,
                'bytes' => $meta['bytes'] ?? null,
                'content_type' => $meta['content_type'] ?? '',
                'cache_control' => $meta['cache_control'] ?? '',
                'status' => $meta['status'] ?? null,
            ];
        }

        foreach ($backgrounds as $background) {
            if (!is_array($background)) {
                continue;
            }
            $src = trim((string)($background['src'] ?? ''));
            $extension = strtolower((string)pathinfo((string)parse_url($src, PHP_URL_PATH), PATHINFO_EXTENSION));
            if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
                ++$legacyFormats;
            }
            $meta = $this->probe($src, $probed, $probeLimit);
            if (($meta['bytes'] ?? 0) > 250_000) {
                ++$largeFiles;
            }
            $rows[] = [
                'kind' => 'background',
                'src' => $src,
                'alt' => '',
                'width' => '',
                'height' => '',
                'loading' => '',
                'fetchpriority' => '',
                'extension' => $extension,
                'bytes' => $meta['bytes'] ?? null,
                'content_type' => $meta['content_type'] ?? '',
                'cache_control' => $meta['cache_control'] ?? '',
                'status' => $meta['status'] ?? null,
            ];
        }

        if ($missingDimensions > 0) {
            $issues[] = $this->issue('warning', 'CLS-Risiko durch Bildabmessungen', $missingDimensions . ' img-Element(e) besitzen nicht gleichzeitig width und height. Intrinsische Abmessungen helfen dem Browser, Platz vor dem Laden zu reservieren.');
        }
        if ($missingAlt > 0) {
            $issues[] = $this->issue('warning', 'Fehlende Alt-Texte', $missingAlt . ' img-Element(e) besitzen einen leeren oder fehlenden Alt-Text. Dekorative Bilder dürfen bewusst alt="" verwenden; Produkt- und Inhaltsbilder sollten geprüft werden.');
        }
        if ($lazyAboveFold > 0) {
            $issues[] = $this->issue('warning', 'Lazy Loading im frühen Seitenbereich', $lazyAboveFold . ' der ersten vier Bilder sind lazy geladen. Ein LCP-Bild sollte in der Regel eager geladen werden.');
        }
        if ($legacyFormats > 0) {
            $issues[] = $this->issue('info', 'Moderne Bildformate prüfen', $legacyFormats . ' erkannte Bildreferenz(en) verwenden JPG/JPEG/PNG. WebP oder AVIF können je nach Motiv deutlich kleiner sein.');
        }
        if ($largeFiles > 0) {
            $issues[] = $this->issue('warning', 'Große Bilddateien', $largeFiles . ' geprüfte Bilddatei(en) überschreiten 250 KB.');
        }
        if ($backgrounds !== []) {
            $issues[] = $this->issue('info', 'CSS-Hintergrundbilder', count($backgrounds) . ' Hintergrundbild(er) wurden erkannt. Ein sichtbares Hintergrundbild kann LCP-relevant sein und wird vom Browser später entdeckt als ein normales img-Element.');
        }

        $lcpCandidate = null;
        if ($backgrounds !== []) {
            $lcpCandidate = $backgrounds[0]['src'] ?? null;
        } elseif ($images !== []) {
            foreach ($images as $image) {
                if (!is_array($image)) {
                    continue;
                }
                $src = (string)($image['src'] ?? '');
                if ($src !== '' && !preg_match('/(?:logo|icon|sprite|svg)/i', $src)) {
                    $lcpCandidate = $src;
                    break;
                }
            }
        }

        return [
            'rows' => $rows,
            'issues' => $issues,
            'counts' => [
                'images' => count($images),
                'backgrounds' => count($backgrounds),
                'missing_dimensions' => $missingDimensions,
                'missing_alt' => $missingAlt,
                'lazy_early' => $lazyAboveFold,
                'legacy_formats' => $legacyFormats,
                'large_files' => $largeFiles,
                'probed' => $probed,
            ],
            'lcp_candidate' => is_string($lcpCandidate) ? $lcpCandidate : null,
        ];
    }

    /** @return array<string,mixed> */
    private function probe(string $url, int &$probed, int $limit): array
    {
        if ($url === '' || $probed >= $limit || !UrlGuard::isSameShopHost($url)) {
            return [];
        }
        ++$probed;
        try {
            $response = $this->http->head($url, true, 5, 2);
            $headers = $response['headers'];
            $length = $headers['content-length'] ?? null;
            return [
                'status' => $response['status'],
                'bytes' => is_string($length) && ctype_digit(trim($length)) ? (int)trim($length) : null,
                'content_type' => (string)($headers['content-type'] ?? ''),
                'cache_control' => (string)($headers['cache-control'] ?? ''),
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array{severity:string,title:string,message:string} */
    private function issue(string $severity, string $title, string $message): array
    {
        return ['severity' => $severity, 'title' => $title, 'message' => $message];
    }
}
