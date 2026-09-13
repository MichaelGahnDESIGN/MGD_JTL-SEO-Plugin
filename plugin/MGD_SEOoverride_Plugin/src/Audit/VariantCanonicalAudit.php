<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Audit;

use JTL\DB\DbInterface;

final class VariantCanonicalAudit
{
    public function __construct(private readonly DbInterface $db) {}

    /** @param array<string,mixed> $crawl @return array<string,mixed> */
    public function analyze(array $crawl): array
    {
        $issues = [];
        $groups = is_array($crawl['canonical_groups'] ?? null) ? $crawl['canonical_groups'] : [];
        $variantStats = ['child_articles' => null, 'parent_groups' => null, 'largest_groups' => []];
        try {
            $count = $this->db->getSingleObject('SELECT COUNT(*) AS `cnt` FROM `tartikel` WHERE `kVaterArtikel` > 0');
            $parents = $this->db->getSingleObject('SELECT COUNT(DISTINCT `kVaterArtikel`) AS `cnt` FROM `tartikel` WHERE `kVaterArtikel` > 0');
            $rows = $this->db->getObjects(
                'SELECT `kVaterArtikel`, COUNT(*) AS `children` FROM `tartikel` WHERE `kVaterArtikel` > 0 GROUP BY `kVaterArtikel` ORDER BY `children` DESC LIMIT 25',
            );
            $variantStats['child_articles'] = (int)($count->cnt ?? 0);
            $variantStats['parent_groups'] = (int)($parents->cnt ?? 0);
            foreach ($rows as $row) {
                $variantStats['largest_groups'][] = [
                    'parent_id' => (int)($row->kVaterArtikel ?? 0),
                    'children' => (int)($row->children ?? 0),
                ];
            }
        } catch (\Throwable) {
            $issues[] = $this->issue('info', 'Variantenzählung nicht verfügbar', 'Die JTL-Artikeltabelle konnte in dieser Installation nicht mit der erwarteten Struktur ausgewertet werden. Die HTML-Canonical-Prüfung läuft trotzdem weiter.');
        }

        $canonicalSources = 0;
        foreach ($groups as $target => $sources) {
            if (!is_array($sources)) {
                continue;
            }
            $canonicalSources += count($sources);
            if (count($sources) >= 8) {
                $issues[] = $this->issue('info', 'Große Canonical-Gruppe', count($sources) . ' gecrawlte URLs verweisen auf dasselbe Canonical-Ziel. Bei JTL-Varkombis kann das beabsichtigt sein; prüfe, ob die Kindartikel tatsächlich weitgehend identische Inhalte besitzen. Ziel: ' . $target);
            }
        }

        $noindexWithCanonical = [];
        $selfCanonicalMissing = [];
        foreach ($crawl['pages'] ?? [] as $page) {
            if (!is_array($page) || (int)($page['status'] ?? 0) !== 200) {
                continue;
            }
            $url = (string)($page['url'] ?? '');
            $canonical = (string)($page['canonical'] ?? '');
            $robots = strtolower((string)($page['robots'] ?? ''));
            if (str_contains($robots, 'noindex') && $canonical !== '') {
                $noindexWithCanonical[] = ['url' => $url, 'canonical' => $canonical];
            }
            if (!str_contains($robots, 'noindex') && $canonical === '') {
                $selfCanonicalMissing[] = $url;
            }
        }
        if ($noindexWithCanonical !== []) {
            $issues[] = $this->issue('info', 'noindex und Canonical kombiniert', count($noindexWithCanonical) . ' gecrawlte Seite(n) kombinieren noindex mit einem Canonical. Das ist nicht automatisch falsch, sollte aber einer bewussten Strategie folgen.');
        }
        if ($selfCanonicalMissing !== []) {
            $issues[] = $this->issue('warning', 'Indexierbare Seiten ohne Canonical', count($selfCanonicalMissing) . ' gecrawlte indexierbare Seite(n) besitzen kein Canonical.');
        }
        if (($variantStats['child_articles'] ?? 0) > 0 && $canonicalSources === 0) {
            $issues[] = $this->issue('warning', 'Varianten vorhanden, aber keine Canonical-Gruppen im Crawl', 'JTL enthält Kindartikel, im begrenzten Crawl wurde aber keine URL gefunden, die auf ein anderes Canonical-Ziel verweist. Prüfe Stichproben von Varkombi-Kindartikeln.');
        }
        if ($canonicalSources > 0) {
            $issues[] = $this->issue('info', 'Canonical-Konsolidierung erkannt', $canonicalSources . ' gecrawlte URL(s) verweisen auf eine andere kanonische URL. Bei Kindartikeln ist das eine typische JTL-Strategie gegen sehr ähnliche Inhalte.');
        }

        return [
            'variant_stats' => $variantStats,
            'canonical_groups' => $groups,
            'canonical_source_count' => $canonicalSources,
            'noindex_with_canonical' => array_slice($noindexWithCanonical, 0, 100),
            'indexable_without_canonical' => array_slice($selfCanonicalMissing, 0, 100),
            'functional_attribute' => defined('FKT_ATTRIBUT_CANONICALURL_VARKOMBI') ? (string)constant('FKT_ATTRIBUT_CANONICALURL_VARKOMBI') : 'varkombi_canonicalurl',
            'issues' => $issues,
        ];
    }

    /** @return array{severity:string,title:string,message:string} */
    private function issue(string $severity, string $title, string $message): array
    {
        return ['severity' => $severity, 'title' => $title, 'message' => $message];
    }
}
