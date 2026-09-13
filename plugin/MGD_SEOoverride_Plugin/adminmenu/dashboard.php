<?php declare(strict_types=1);
require __DIR__ . '/_common.php';
require_once dirname(__DIR__) . '/src/Storage/AuditHistoryRepository.php';
require_once dirname(__DIR__) . '/src/Monitor/NotFoundMonitor.php';

use JTL\Shop;
use Plugin\MGD_SEOoverride_Plugin\Src\Monitor\NotFoundMonitor;
use Plugin\MGD_SEOoverride_Plugin\Src\Storage\AuditHistoryRepository;

$config = $oPlugin->getConfig();
$enabled = mgdSeoYes($config->getValue('mgd_enabled'));
$lcp = mgdSeoYes($config->getValue('mgd_lcp_preload'));
$inlineCss = mgdSeoYes($config->getValue('mgd_inline_small_css'));
$lazy = mgdSeoYes($config->getValue('mgd_lazy_images'));
$monitor404 = mgdSeoYes($config->getValue('mgd_404_monitor'));
$lastPsi = null;
$lastCrawl = null;
$notFoundCount = null;
try {
    $history = new AuditHistoryRepository(Shop::Container()->getDB());
    $lastPsiRows = $history->latest('pagespeed', 1);
    $lastPsi = $lastPsiRows[0]['payload'] ?? null;
    $lastCrawlRows = $history->latest('crawl', 1);
    $lastCrawl = $lastCrawlRows[0]['payload'] ?? null;
    $notFoundCount = count((new NotFoundMonitor(Shop::Container()->getDB()))->latest(200));
} catch (Throwable) {}

mgdSeoHeader('MGD JTL SEO & PageSpeed', 'Technisches SEO, Core Web Vitals, Shop-Crawling, PageSpeed-Verlauf und sichere Diagnose für JTL-Shop 5.');
echo '<div class="mgd-grid">';
echo mgdSeoCard('Plugin', '<p>' . mgdSeoPill('Version ' . $oPlugin->getMeta()->getVersion(), 'info') . '</p><p>' . ($enabled ? 'Frontend-Optimierungen sind aktiv.' : 'Frontend-Optimierungen sind deaktiviert.') . '</p>', $enabled ? 'ok' : 'warn');
echo mgdSeoCard('LCP', '<p>' . ($lcp ? mgdSeoPill('Preload aktiv', 'good') : mgdSeoPill('deaktiviert', 'warn')) . '</p><p class="mgd-small">Priorisiert auf der Startseite ein erkanntes OPC-Hintergrundbild oder die konfigurierte LCP-URL.</p>', $lcp ? 'ok' : 'warn');
echo mgdSeoCard('CSS', '<p>' . ($inlineCss ? mgdSeoPill('Klein-CSS Inlining aktiv', 'good') : mgdSeoPill('deaktiviert', 'info')) . '</p><p class="mgd-small">Nur lokale kleine Stylesheets ohne url() oder @import.</p>', $inlineCss ? 'ok' : 'info');
echo mgdSeoCard('Lazy Loading', '<p>' . ($lazy ? mgdSeoPill('zusätzlich aktiv', 'warn') : mgdSeoPill('konservativ aus', 'good')) . '</p><p class="mgd-small">Für JTL zunächst besser messen, bevor zusätzliches Lazy Loading aktiviert wird.</p>', $lazy ? 'warn' : 'ok');
echo mgdSeoCard('404 Monitor', '<p>' . ($monitor404 ? mgdSeoPill('aktiv', 'good') : mgdSeoPill('deaktiviert', 'warn')) . '</p><p class="mgd-small">Keine IPs, keine Query-Parameter, nur Pfade und Trefferzahlen.</p>', $monitor404 ? 'ok' : 'warn');
if (is_array($lastPsi)) {
    echo mgdSeoCard('Letzter PageSpeed', '<div class="mgd-kpi">' . mgdSeoScore($lastPsi['performance_score'] ?? null) . '<div><strong>' . mgdSeoEsc(ucfirst((string)($lastPsi['strategy'] ?? ''))) . '</strong><br><span class="mgd-small">SEO ' . mgdSeoEsc($lastPsi['seo_score'] ?? '—') . ' · LCP ' . mgdSeoEsc($lastPsi['metrics']['lcp']['display'] ?? '—') . '</span></div></div>', 'info');
} else {
    echo mgdSeoCard('PageSpeed', '<p>Noch kein PageSpeed-Lauf gespeichert.</p><span class="mgd-small">Im Bereich Performance & Core Web Vitals kann Mobile oder Desktop direkt gemessen werden.</span>', 'info');
}
echo '</div>';

if (is_array($lastCrawl) || $notFoundCount !== null) {
    echo '<div class="mgd-grid">';
    if (is_array($lastCrawl)) {
        echo mgdSeoCard('Letzter Crawl', '<p><strong>' . mgdSeoEsc($lastCrawl['visited_count'] ?? 0) . '</strong> Seiten besucht<br><strong>' . count($lastCrawl['broken'] ?? []) . '</strong> Broken Links<br><strong>' . count($lastCrawl['orphan_candidates'] ?? []) . '</strong> Orphan-Kandidaten<br><strong>' . count($lastCrawl['redirect_chains'] ?? []) . '</strong> Weiterleitungen</p>', count($lastCrawl['broken'] ?? []) > 0 ? 'warn' : 'ok');
    }
    if ($notFoundCount !== null) {
        echo mgdSeoCard('404 Historie', '<p><strong>' . mgdSeoEsc($notFoundCount) . '</strong> zuletzt gespeicherte Pfade</p><span class="mgd-small">Details und interner Referrer im Bereich Redirects & 404.</span>', $notFoundCount > 0 ? 'warn' : 'ok');
    }
    echo '</div>';
}

echo '<div class="mgd-section"><h3>Werkzeuge</h3><div class="mgd-grid">';
echo mgdSeoCard('Technical SEO Audit', '<p>Title, Description, Canonical, Robots, H1, HTTP-Status, robots.txt, Sitemap und strukturierte Daten.</p>', 'info');
echo mgdSeoCard('Performance', '<p>Google PageSpeed Insights, Verlauf, Bilder, CLS, Server, Caching, Drittanbieter, JavaScript und INP-Risikofaktoren.</p>', 'info');
echo mgdSeoCard('Crawl & Links', '<p>Interne Verlinkung, Crawl-Tiefe, Broken Links, Redirectketten, Sitemap-Orphans, doppelte Titles und JTL-Varianten-Canonicals.</p>', 'info');
echo mgdSeoCard('Updates', '<p>GitHub Releases mit SHA256, Manifestprüfung und sicherem JTL-Lifecycle-Gate. Keine ungeprüften ZIPs werden ausgeführt.</p>', 'info');
echo '</div></div>';

echo '<div class="mgd-note"><strong>Arbeitsweise:</strong> Das Plugin trennt Diagnose und Eingriff. Kritische SEO-Strategien wie Canonicals, noindex, Redirects und JavaScript defer werden nicht pauschal verändert. Erst messen und prüfen, dann gezielt konfigurieren.</div>';
mgdSeoFooter();
