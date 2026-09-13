<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Audit;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\SafeHttpClient;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\UrlGuard;

final class SitemapReader
{
    public function __construct(private readonly SafeHttpClient $http) {}

    /**
     * @return array{found:bool,entry_url:string,urls:list<string>,sitemaps:list<string>,errors:list<string>}
     */
    public function read(int $maxUrls = 2500): array
    {
        $origin = UrlGuard::shopOrigin();
        $candidates = [$origin . '/sitemap_index.xml', $origin . '/sitemap.xml'];
        $entry = '';
        foreach ($candidates as $candidate) {
            try {
                $response = $this->http->get($candidate, true, 8, SafeHttpClient::MAX_TEXT_BYTES, 2);
                if ($response['status'] === 200 && trim($response['body']) !== '') {
                    $entry = $response['url'];
                    break;
                }
            } catch (\Throwable) {
                // Nächsten Standardpfad probieren.
            }
        }
        if ($entry === '') {
            return ['found' => false, 'entry_url' => '', 'urls' => [], 'sitemaps' => [], 'errors' => ['Keine Sitemap unter sitemap_index.xml oder sitemap.xml gefunden.']];
        }

        $urls = [];
        $sitemaps = [];
        $errors = [];
        $queue = [$entry];
        $seenMaps = [];
        while ($queue !== [] && count($urls) < $maxUrls && count($seenMaps) < 50) {
            $mapUrl = array_shift($queue);
            if (!is_string($mapUrl) || isset($seenMaps[$mapUrl])) {
                continue;
            }
            $seenMaps[$mapUrl] = true;
            $sitemaps[] = $mapUrl;
            try {
                $response = $this->http->get($mapUrl, true, 10, 5_242_880, 2);
                if ($response['status'] !== 200) {
                    $errors[] = 'Sitemap lieferte HTTP ' . $response['status'] . ': ' . $mapUrl;
                    continue;
                }
                $parsed = $this->parse($response['body']);
                foreach ($parsed['sitemaps'] as $child) {
                    if (UrlGuard::isSameShopHost($child) && !isset($seenMaps[$child])) {
                        $queue[] = $child;
                    }
                }
                foreach ($parsed['urls'] as $page) {
                    if (!UrlGuard::isSameShopHost($page)) {
                        continue;
                    }
                    $page = UrlGuard::withoutQuery($page);
                    $urls[$page] = true;
                    if (count($urls) >= $maxUrls) {
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = 'Sitemap konnte nicht gelesen werden: ' . $mapUrl . ' (' . $e->getMessage() . ')';
            }
        }
        return [
            'found' => true,
            'entry_url' => $entry,
            'urls' => array_keys($urls),
            'sitemaps' => $sitemaps,
            'errors' => $errors,
        ];
    }

    /** @return array{urls:list<string>,sitemaps:list<string>} */
    private function parse(string $xml): array
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded || !$document->documentElement instanceof DOMElement) {
            throw new \RuntimeException('Ungültiges XML.');
        }
        $xpath = new DOMXPath($document);
        $root = strtolower($document->documentElement->localName);
        $urls = [];
        $sitemaps = [];
        foreach ($xpath->query('//*[local-name()="loc"]') ?: [] as $node) {
            $value = UrlGuard::normalize(trim($node->textContent));
            if ($value === null) {
                continue;
            }
            if ($root === 'sitemapindex') {
                $sitemaps[] = $value;
            } else {
                $urls[] = $value;
            }
        }
        return ['urls' => $urls, 'sitemaps' => $sitemaps];
    }
}
