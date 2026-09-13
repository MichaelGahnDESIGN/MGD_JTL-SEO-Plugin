<?php declare(strict_types=1);
require __DIR__ . '/_common.php';
require_once dirname(__DIR__) . '/src/Http/UrlGuard.php';
require_once dirname(__DIR__) . '/src/Http/SafeHttpClient.php';
require_once dirname(__DIR__) . '/src/Audit/HtmlAnalyzer.php';
require_once dirname(__DIR__) . '/src/Audit/SitemapReader.php';
require_once dirname(__DIR__) . '/src/Audit/SiteCrawler.php';
require_once dirname(__DIR__) . '/src/Audit/VariantCanonicalAudit.php';
require_once dirname(__DIR__) . '/src/Storage/AuditHistoryRepository.php';

use JTL\Shop;
use Plugin\MGD_SEOoverride_Plugin\Src\Audit\HtmlAnalyzer;
use Plugin\MGD_SEOoverride_Plugin\Src\Audit\SiteCrawler;
use Plugin\MGD_SEOoverride_Plugin\Src\Audit\VariantCanonicalAudit;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\SafeHttpClient;
use Plugin\MGD_SEOoverride_Plugin\Src\Storage\AuditHistoryRepository;

$config = $oPlugin->getConfig();
$maxPages = max(5, min(250, (int)($config->getValue('mgd_crawl_max_pages') ?? 50)));
$maxDepth = max(1, min(8, (int)($config->getValue('mgd_crawl_max_depth') ?? 4)));
$result = null;
$variant = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        mgdSeoAssertPost();
        $maxPages = max(5, min(250, (int)($_POST['max_pages'] ?? $maxPages)));
        $maxDepth = max(1, min(8, (int)($_POST['max_depth'] ?? $maxDepth)));
        $crawler = new SiteCrawler(new SafeHttpClient(), new HtmlAnalyzer());
        $result = $crawler->crawl($maxPages, $maxDepth);
        $variant = (new VariantCanonicalAudit(Shop::Container()->getDB()))->analyze($result);
        try {
            (new AuditHistoryRepository(Shop::Container()->getDB()))->save('crawl', mgdSeoShopUrl(), [
                'visited_count' => $result['visited_count'],
                'discovered_count' => $result['discovered_count'],
                'broken' => $result['broken'],
                'redirect_chains' => $result['redirect_chains'],
                'deep_pages' => array_slice($result['deep_pages'], 0, 100),
                'no_inlinks' => array_slice($result['no_inlinks'], 0, 100),
                'orphan_candidates' => array_slice($result['orphan_candidates'], 0, 200),
                'canonical_groups' => $result['canonical_groups'],
                'duplicate_titles' => $result['duplicate_titles'],
                'truncated' => $result['truncated'],
            ]);
        } catch (Throwable) {}
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

mgdSeoHeader('Crawl, interne Links & Varianten', 'Begrenzt gecrawlter Same-Origin-Audit für Crawl-Tiefe, interne Verlinkung, Broken Links, Redirectketten, Sitemap-Orphans und JTL-Varianten-Canonicals.');
if ($error !== null) {
    echo mgdSeoAlert(mgdSeoEsc($error), 'bad');
}
echo '<form method="post" class="mgd-card"><input type="hidden" name="jtl_token" value="' . mgdSeoEsc(mgdSeoToken()) . '"><div class="mgd-form-row"><div class="mgd-field"><label>Maximale Seiten</label><input type="number" min="5" max="250" name="max_pages" value="' . mgdSeoEsc($maxPages) . '"><span class="mgd-small">Für erste Tests 25 bis 50. Große Werte erzeugen echte HTTP-Anfragen an den Shop.</span></div><div class="mgd-field"><label>Maximale Crawl-Tiefe</label><input type="number" min="1" max="8" name="max_depth" value="' . mgdSeoEsc($maxDepth) . '"></div><button class="mgd-btn" type="submit">Shop Crawl starten</button></div></form>';

echo '<div class="mgd-note"><strong>Schonend:</strong> Der Crawler bleibt auf der eigenen Shop-Domain, ignoriert statische Dateien und typische Checkout-/Adminpfade und hat harte Obergrenzen. Für sehr große Shops ist dies ein Diagnose-Crawler, kein Ersatz für Screaming Frog oder einen verteilten Enterprise-Crawler.</div>';

if (is_array($result)) {
    echo '<div class="mgd-grid">';
    echo mgdSeoCard('Besucht', '<div class="mgd-kpi">' . mgdSeoScore(min(100, (int)$result['visited_count'])) . '<div><strong>' . mgdSeoEsc($result['visited_count']) . ' Seiten</strong><br><span class="mgd-small">entdeckt: ' . mgdSeoEsc($result['discovered_count']) . '</span></div></div>', 'info');
    echo mgdSeoCard('Broken Links', '<p><strong>' . count($result['broken'] ?? []) . '</strong> fehlerhafte/unerreichbare URLs im Crawl</p>', count($result['broken'] ?? []) === 0 ? 'ok' : 'bad');
    echo mgdSeoCard('Redirectketten', '<p><strong>' . count($result['redirect_chains'] ?? []) . '</strong> URLs mit Weiterleitung</p>', count($result['redirect_chains'] ?? []) > 3 ? 'warn' : 'info');
    echo mgdSeoCard('Sitemap-Orphans', '<p><strong>' . count($result['orphan_candidates'] ?? []) . '</strong> Sitemap-URLs wurden im begrenzten Linkcrawl nicht entdeckt</p>', count($result['orphan_candidates'] ?? []) > 0 ? 'warn' : 'ok');
    echo mgdSeoCard('Tiefe Seiten', '<p><strong>' . count($result['deep_pages'] ?? []) . '</strong> Seiten liegen tiefer als Ebene 3</p>', count($result['deep_pages'] ?? []) > 0 ? 'warn' : 'ok');
    echo mgdSeoCard('Doppelte Titles', '<p><strong>' . count($result['duplicate_titles'] ?? []) . '</strong> wiederholte Titelgruppen</p>', count($result['duplicate_titles'] ?? []) > 0 ? 'warn' : 'ok');
    echo '</div>';

    if (($result['truncated'] ?? false) === true) {
        echo mgdSeoAlert('Der Crawl wurde an der konfigurierten Obergrenze beendet. Orphan- und Tiefe-Aussagen sind deshalb nur Stichproben.', 'warn');
    }

    $pages = is_array($result['pages'] ?? null) ? $result['pages'] : [];
    if ($pages !== []) {
        echo '<div class="mgd-section"><h3>Gecrawlte Seiten</h3><div class="mgd-table-wrap"><table class="mgd-table"><thead><tr><th>Status</th><th>Tiefe</th><th>Inlinks</th><th>URL</th><th>Title</th><th>Canonical</th></tr></thead><tbody>';
        foreach ($pages as $page) {
            if (!is_array($page)) continue;
            $status = (int)($page['status'] ?? 0);
            $tone = $status === 200 ? 'good' : ($status >= 400 || $status === 0 ? 'bad' : 'warn');
            echo '<tr><td>' . mgdSeoPill((string)$status, $tone) . '</td><td>' . mgdSeoEsc($page['depth'] ?? '') . '</td><td>' . mgdSeoEsc($page['inlinks'] ?? '') . '</td><td><span class="mgd-code">' . mgdSeoEsc($page['url'] ?? '') . '</span></td><td>' . mgdSeoEsc($page['title'] ?? '') . '</td><td><span class="mgd-code">' . mgdSeoEsc($page['canonical'] ?? '') . '</span></td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    $broken = is_array($result['broken'] ?? null) ? $result['broken'] : [];
    if ($broken !== []) {
        echo '<div class="mgd-section"><h3>Broken Links</h3><div class="mgd-table-wrap"><table class="mgd-table"><thead><tr><th>Status</th><th>Ziel</th><th>Gefunden auf</th></tr></thead><tbody>';
        foreach ($broken as $row) {
            if (!is_array($row)) continue;
            echo '<tr><td>' . mgdSeoPill((string)($row['status'] ?? 0), 'bad') . '</td><td><span class="mgd-code">' . mgdSeoEsc($row['url'] ?? '') . '</span></td><td>' . mgdSeoEsc(implode(', ', $row['referrers'] ?? [])) . '</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    $orphans = is_array($result['orphan_candidates'] ?? null) ? $result['orphan_candidates'] : [];
    if ($orphans !== []) {
        echo '<div class="mgd-section"><h3>Potenzielle verwaiste URLs</h3><p class="mgd-muted">Diese URLs stehen in der Sitemap, wurden aber im begrenzten Crawl nicht über interne Links gefunden. Bei abgeschnittenem Crawl sind Fehlalarme möglich.</p><div class="mgd-table-wrap"><table class="mgd-table"><tbody>';
        foreach (array_slice($orphans, 0, 200) as $orphan) {
            echo '<tr><td><span class="mgd-code">' . mgdSeoEsc($orphan) . '</span></td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    $redirects = is_array($result['redirect_chains'] ?? null) ? $result['redirect_chains'] : [];
    if ($redirects !== []) {
        echo '<div class="mgd-section"><h3>Weiterleitungen</h3><div class="mgd-table-wrap"><table class="mgd-table"><thead><tr><th>Start</th><th>Stufen</th><th>Final</th></tr></thead><tbody>';
        foreach ($redirects as $row) {
            if (!is_array($row)) continue;
            $parts = [];
            foreach ($row['chain'] ?? [] as $step) {
                if (is_array($step)) $parts[] = (string)($step['status'] ?? '') . ' → ' . (string)($step['to'] ?? '');
            }
            echo '<tr><td><span class="mgd-code">' . mgdSeoEsc($row['url'] ?? '') . '</span></td><td>' . mgdSeoEsc(implode(' | ', $parts)) . '</td><td><span class="mgd-code">' . mgdSeoEsc($row['final_url'] ?? '') . '</span></td></tr>';
        }
        echo '</tbody></table></div></div>';
    }
}

if (is_array($variant)) {
    $stats = is_array($variant['variant_stats'] ?? null) ? $variant['variant_stats'] : [];
    echo '<div class="mgd-section"><h3>Canonical & JTL Varianten Audit</h3><div class="mgd-grid">';
    echo mgdSeoCard('Kindartikel', '<p><strong>' . mgdSeoEsc($stats['child_articles'] ?? 'nicht ermittelbar') . '</strong><br><span class="mgd-small">tartikel.kVaterArtikel &gt; 0</span></p>', 'info');
    echo mgdSeoCard('Varianten-Gruppen', '<p><strong>' . mgdSeoEsc($stats['parent_groups'] ?? 'nicht ermittelbar') . '</strong><br><span class="mgd-small">Eltern mit Kindartikeln</span></p>', 'info');
    echo mgdSeoCard('Canonical-Konsolidierungen', '<p><strong>' . mgdSeoEsc($variant['canonical_source_count'] ?? 0) . '</strong><br><span class="mgd-small">gecrawlte URLs mit abweichendem Canonical</span></p>', 'info');
    echo mgdSeoCard('JTL Funktionsattribut', '<p><span class="mgd-code">' . mgdSeoEsc($variant['functional_attribute'] ?? 'varkombi_canonicalurl') . '</span></p><span class="mgd-small">für gezielte Varkombi-Canonical-Strategien</span>', 'info');
    echo '</div>' . mgdSeoRenderIssues($variant['issues'] ?? []) . '</div>';

    $groups = is_array($variant['canonical_groups'] ?? null) ? $variant['canonical_groups'] : [];
    if ($groups !== []) {
        echo '<div class="mgd-table-wrap"><table class="mgd-table"><thead><tr><th>Canonical-Ziel</th><th>Quell-URLs</th></tr></thead><tbody>';
        foreach ($groups as $target => $sources) {
            if (!is_array($sources)) continue;
            echo '<tr><td><span class="mgd-code">' . mgdSeoEsc($target) . '</span></td><td>' . count($sources) . '<br><span class="mgd-small">' . mgdSeoEsc(implode(' | ', array_slice($sources, 0, 5))) . (count($sources) > 5 ? ' …' : '') . '</span></td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

mgdSeoFooter();
