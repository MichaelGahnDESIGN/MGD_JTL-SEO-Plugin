<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Audit;

use DOMDocument;
use DOMElement;
use DOMXPath;

final class StructuredDataAudit
{
    /** @return array<string,mixed> */
    public function analyze(string $html): array
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return ['types' => [], 'microdata_types' => [], 'issues' => [['severity' => 'error', 'title' => 'HTML nicht lesbar', 'message' => 'Die strukturierten Daten konnten nicht aus dem HTML gelesen werden.']], 'blocks' => 0];
        }
        $xpath = new DOMXPath($doc);
        $issues = [];
        $nodesByType = [];
        $blockCount = 0;
        $invalidCount = 0;
        foreach ($xpath->query("//script[translate(@type,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='application/ld+json']") ?: [] as $node) {
            $raw = trim($node->textContent);
            if ($raw === '') {
                continue;
            }
            ++$blockCount;
            try {
                $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
                $this->collectTypedNodes($data, $nodesByType);
            } catch (\JsonException $e) {
                ++$invalidCount;
                $issues[] = $this->issue('error', 'Ungültiges JSON-LD', 'JSON-LD-Block ' . $blockCount . ' ist syntaktisch ungültig: ' . $e->getMessage());
            }
        }

        $microdata = [];
        foreach ($xpath->query('//*[@itemscope and @itemtype]') ?: [] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            $type = trim($node->getAttribute('itemtype'));
            if ($type !== '') {
                $microdata[] = $type;
            }
        }
        $microdata = array_values(array_unique($microdata));

        foreach ($nodesByType['Product'] ?? [] as $index => $product) {
            $prefix = 'Product #' . ($index + 1) . ': ';
            if (!$this->hasText($product, 'name')) {
                $issues[] = $this->issue('error', 'Product ohne name', $prefix . 'Die Eigenschaft name fehlt oder ist leer.');
            }
            if (!isset($product['image']) || $product['image'] === '' || $product['image'] === []) {
                $issues[] = $this->issue('warning', 'Product ohne image', $prefix . 'Google kann Produktbilder aus anderen Quellen erkennen, strukturierte Bilddaten sind aber empfehlenswert.');
            }
            if (!isset($product['offers'])) {
                $issues[] = $this->issue('warning', 'Product ohne Offer', $prefix . 'Es wurde keine offers-Eigenschaft gefunden. Für kaufbare Produkte sind Angebotsdaten besonders wichtig.');
            }
        }

        foreach ($nodesByType['Offer'] ?? [] as $index => $offer) {
            $prefix = 'Offer #' . ($index + 1) . ': ';
            if (!$this->hasText($offer, 'price') && !isset($offer['priceSpecification'])) {
                $issues[] = $this->issue('error', 'Offer ohne Preis', $prefix . 'Weder price noch priceSpecification wurde gefunden.');
            }
            if (!$this->hasText($offer, 'priceCurrency')) {
                $issues[] = $this->issue('warning', 'Offer ohne priceCurrency', $prefix . 'Die Währung fehlt.');
            }
            if (!$this->hasText($offer, 'availability')) {
                $issues[] = $this->issue('info', 'Offer ohne availability', $prefix . 'Eine Verfügbarkeitsangabe kann Merchant- und Suchergebnisse verbessern.');
            }
            if (!$this->hasText($offer, 'url')) {
                $issues[] = $this->issue('info', 'Offer ohne url', $prefix . 'Eine direkte Angebots-URL ist nicht zwingend, aber häufig hilfreich.');
            }
        }

        foreach ($nodesByType['BreadcrumbList'] ?? [] as $index => $crumbs) {
            $items = $crumbs['itemListElement'] ?? null;
            if (!is_array($items) || count($items) < 2) {
                $issues[] = $this->issue('warning', 'BreadcrumbList unvollständig', 'BreadcrumbList #' . ($index + 1) . ' enthält weniger als zwei Listenelemente.');
            } else {
                foreach ($items as $position => $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    if (!$this->hasText($item, 'name') || !isset($item['position'])) {
                        $issues[] = $this->issue('warning', 'Breadcrumb-Element unvollständig', 'Ein ListItem besitzt nicht gleichzeitig name und position.');
                        break;
                    }
                }
            }
        }

        foreach ($nodesByType['Organization'] ?? [] as $index => $organization) {
            if (!$this->hasText($organization, 'name')) {
                $issues[] = $this->issue('warning', 'Organization ohne name', 'Organization #' . ($index + 1) . ' besitzt keinen Namen.');
            }
            if (!$this->hasText($organization, 'url')) {
                $issues[] = $this->issue('info', 'Organization ohne url', 'Organization #' . ($index + 1) . ' besitzt keine URL.');
            }
        }

        if ($blockCount === 0 && $microdata === []) {
            $issues[] = $this->issue('info', 'Keine strukturierten Daten erkannt', 'Weder JSON-LD noch Microdata mit itemtype wurden im geprüften HTML gefunden.');
        } elseif ($blockCount === 0 && $microdata !== []) {
            $issues[] = $this->issue('info', 'Microdata statt JSON-LD erkannt', 'JTL/NOVA kann strukturierte Daten als Microdata ausgeben. Das ist gültig; JSON-LD ist nur eine alternative Darstellungsform.');
        }
        if (count($nodesByType['Product'] ?? []) > 1) {
            $issues[] = $this->issue('info', 'Mehrere Product-Entitäten', 'Auf der Seite wurden mehrere Product-Entitäten gefunden. Das kann bei Listen sinnvoll sein; auf einer Artikeldetailseite sollte die Zuordnung eindeutig bleiben.');
        }

        return [
            'types' => array_keys($nodesByType),
            'microdata_types' => $microdata,
            'issues' => $issues,
            'blocks' => $blockCount,
            'invalid_blocks' => $invalidCount,
            'counts' => array_map('count', $nodesByType),
        ];
    }

    /** @param mixed $value @param array<string,list<array<string,mixed>>> $byType */
    private function collectTypedNodes(mixed $value, array &$byType): void
    {
        if (!is_array($value)) {
            return;
        }
        if (isset($value['@type'])) {
            $types = is_array($value['@type']) ? $value['@type'] : [$value['@type']];
            foreach ($types as $type) {
                if (is_string($type) && preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,80}$/D', $type) === 1) {
                    $byType[$type] ??= [];
                    $byType[$type][] = $value;
                }
            }
        }
        foreach ($value as $child) {
            if (is_array($child)) {
                $this->collectTypedNodes($child, $byType);
            }
        }
    }

    /** @param array<string,mixed> $data */
    private function hasText(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;
        if (is_string($value) || is_numeric($value)) {
            return trim((string)$value) !== '';
        }
        return false;
    }

    /** @return array{severity:string,title:string,message:string} */
    private function issue(string $severity, string $title, string $message): array
    {
        return ['severity' => $severity, 'title' => $title, 'message' => $message];
    }
}
