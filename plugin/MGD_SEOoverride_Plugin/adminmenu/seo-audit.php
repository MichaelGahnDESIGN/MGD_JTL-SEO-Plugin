<?php declare(strict_types=1);
require __DIR__ . '/_common.php';
require_once dirname(__DIR__) . '/src/Http/UrlGuard.php';
require_once dirname(__DIR__) . '/src/Http/SafeHttpClient.php';
require_once dirname(__DIR__) . '/src/Audit/HtmlAnalyzer.php';
require_once dirname(__DIR__) . '/src/Audit/SitemapReader.php';
require_once dirname(__DIR__) . '/src/Audit/TechnicalSeoAudit.php';
require_once dirname(__DIR__) . '/src/Audit/StructuredDataAudit.php';
require_once dirname(__DIR__) . '/src/Storage/AuditHistoryRepository.php';

use JTL\Shop;
use Plugin\MGD_SEOoverride_Plugin\Src\Audit\HtmlAnalyzer;
use Plugin\MGD_SEOoverride_Plugin\Src\Audit\StructuredDataAudit;
use Plugin\MGD_SEOoverride_Plugin\Src\Audit\TechnicalSeoAudit;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\SafeHttpClient;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\UrlGuard;
use Plugin\MGD_SEOoverride_Plugin\Src\Storage\AuditHistoryRepository;

$url = mgdSeoShopUrl();
$result = null;
$structured = null;
$infrastructure = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        mgdSeoAssertPost();
        $submitted = isset($_POST['url']) && is_string($_POST['url']) ? trim($_POST['url']) : '';
        $normalized = UrlGuard::normalize($submitted);
        if ($normalized === null || !UrlGuard::isSameShopHost($normalized)) {
            throw new RuntimeException('Bitte nur eine URL der eigenen Shop-Domain prüfen.');
        }
        $url = $normalized;
        $client = new SafeHttpClient();
        $analyzer = new HtmlAnalyzer();
        $audit = new TechnicalSeoAudit($client, $analyzer);
        $result = $audit->audit($url);
        $infrastructure = $audit->auditInfrastructure();
        $raw = $client->get($url, true, 12);
        $structured = (new StructuredDataAudit())->analyze($raw['body']);
        try {
            (new AuditHistoryRepository(Shop::Container()->getDB()))->save('seo', $url, [
                'score' => $result['score'],
                'status' => $result['status'],
                'issues' => $result['issues'],
                'structured_types' => $structured['types'],
                'structured_issues' => $structured['issues'],
            ]);
        } catch (Throwable) {
            // Der Audit bleibt nutzbar, auch wenn die optionale Historie nicht gespeichert werden kann.
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

mgdSeoHeader('Technical SEO Audit', 'Prüft eine konkrete Shopseite sowie robots.txt, Sitemap und strukturierte Daten. Es werden keine Inhalte verändert.');
if ($error !== null) {
    echo mgdSeoAlert(mgdSeoEsc($error), 'bad');
}
echo '<form method="post" class="mgd-card"><input type="hidden" name="jtl_token" value="' . mgdSeoEsc(mgdSeoToken()) . '"><div class="mgd-form-row"><div class="mgd-field"><label for="mgd-url">Shop-URL prüfen</label><input id="mgd-url" type="url" name="url" required value="' . mgdSeoEsc($url) . '"><span class="mgd-small">Aus Sicherheitsgründen sind nur URLs der eigenen Shop-Domain erlaubt.</span></div><button class="mgd-btn" type="submit">SEO Audit starten</button></div></form>';

if (is_array($result)) {
    $page = is_array($result['page'] ?? null) ? $result['page'] : [];
    echo '<div class="mgd-grid">';
    echo mgdSeoCard('SEO Score', '<div class="mgd-kpi">' . mgdSeoScore((int)$result['score']) . '<div><strong>technischer Audit</strong><br><span class="mgd-small">Heuristische Plugin-Bewertung, kein Google-Rankingwert</span></div></div>', ((int)$result['score'] >= 85 ? 'ok' : 'warn'));
    echo mgdSeoCard('HTTP', '<p><strong>Status:</strong> ' . mgdSeoEsc($result['status']) . '<br><strong>TTFB:</strong> ' . mgdSeoEsc($result['ttfb_ms']) . ' ms<br><strong>Protokoll:</strong> ' . mgdSeoEsc($result['http_version']) . '<br><strong>Redirects:</strong> ' . count($result['redirects'] ?? []) . '</p>', ((int)$result['status'] === 200 ? 'ok' : 'warn'));
    echo mgdSeoCard('Indexierung', '<p><strong>Robots:</strong> ' . mgdSeoEsc($page['robots'] ?: 'kein Meta-Tag') . '<br><strong>Canonical:</strong><br><span class="mgd-code">' . mgdSeoEsc($page['canonical'] ?: 'nicht gesetzt') . '</span></p>', 'info');
    echo mgdSeoCard('Dokument', '<p><strong>H1:</strong> ' . count($page['h1'] ?? []) . '<br><strong>Sprache:</strong> ' . mgdSeoEsc($page['html_lang'] ?: 'nicht gesetzt') . '<br><strong>JSON-LD Typen:</strong> ' . mgdSeoEsc(implode(', ', $page['jsonld_types'] ?? []) ?: 'keine') . '</p>', 'info');
    echo '</div>';

    echo '<div class="mgd-section"><h3>Seitensignale</h3><div class="mgd-table-wrap"><table class="mgd-table"><tbody>';
    echo '<tr><th>Finale URL</th><td><span class="mgd-code">' . mgdSeoEsc($result['final_url']) . '</span></td></tr>';
    echo '<tr><th>Title</th><td>' . mgdSeoEsc($page['title'] ?: '—') . ' <span class="mgd-small">(' . mb_strlen((string)($page['title'] ?? '')) . ' Zeichen)</span></td></tr>';
    echo '<tr><th>Meta Description</th><td>' . mgdSeoEsc($page['description'] ?: '—') . ' <span class="mgd-small">(' . mb_strlen((string)($page['description'] ?? '')) . ' Zeichen)</span></td></tr>';
    echo '<tr><th>H1</th><td>' . mgdSeoEsc(implode(' | ', $page['h1'] ?? []) ?: '—') . '</td></tr>';
    echo '<tr><th>Canonical</th><td><span class="mgd-code">' . mgdSeoEsc($page['canonical'] ?: '—') . '</span></td></tr>';
    echo '<tr><th>Robots</th><td>' . mgdSeoEsc($page['robots'] ?: '—') . '</td></tr>';
    echo '</tbody></table></div></div>';

    echo '<div class="mgd-section"><h3>SEO Auffälligkeiten</h3>' . mgdSeoRenderIssues($result['issues'] ?? []) . '</div>';
}

if (is_array($infrastructure)) {
    $sitemap = is_array($infrastructure['sitemap'] ?? null) ? $infrastructure['sitemap'] : [];
    $robots = is_array($infrastructure['robots'] ?? null) ? $infrastructure['robots'] : [];
    echo '<div class="mgd-section"><h3>Crawl Infrastruktur</h3><div class="mgd-grid">';
    echo mgdSeoCard('robots.txt', '<p><strong>HTTP:</strong> ' . mgdSeoEsc($robots['status'] ?? 0) . '<br><strong>Sitemap-Angaben:</strong> ' . count($robots['sitemaps'] ?? []) . '</p>', (($robots['status'] ?? 0) === 200 ? 'ok' : 'warn'));
    echo mgdSeoCard('Sitemap', '<p><strong>Gefunden:</strong> ' . (($sitemap['found'] ?? false) ? 'Ja' : 'Nein') . '<br><strong>gelesene Sitemap-Dateien:</strong> ' . count($sitemap['sitemaps'] ?? []) . '<br><strong>URLs in Stichprobe:</strong> ' . count($sitemap['urls'] ?? []) . '</p>', (($sitemap['found'] ?? false) ? 'ok' : 'warn'));
    echo '</div>' . mgdSeoRenderIssues($infrastructure['issues'] ?? []) . '</div>';
}

if (is_array($structured)) {
    echo '<div class="mgd-section"><h3>Structured Data Audit</h3>';
    echo '<div class="mgd-note"><strong>JSON-LD Blöcke:</strong> ' . mgdSeoEsc($structured['blocks'] ?? 0) . ' · <strong>Typen:</strong> ' . mgdSeoEsc(implode(', ', $structured['types'] ?? []) ?: 'keine') . ' · <strong>Microdata itemtypes:</strong> ' . mgdSeoEsc(implode(', ', $structured['microdata_types'] ?? []) ?: 'keine') . '</div>';
    echo mgdSeoRenderIssues($structured['issues'] ?? []);
    echo '</div>';
}

echo '<div class="mgd-note"><strong>Hinweis:</strong> Das Plugin ergänzt bewusst keine Product-, Offer- oder Breadcrumb-Daten automatisch. Der Audit soll vorhandene JTL-, Template- oder Plugin-Ausgaben prüfen, damit keine doppelten strukturierten Daten entstehen.</div>';
mgdSeoFooter();
