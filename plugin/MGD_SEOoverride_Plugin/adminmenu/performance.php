<?php declare(strict_types=1);
require __DIR__ . '/_common.php';
require_once dirname(__DIR__) . '/src/Http/UrlGuard.php';
require_once dirname(__DIR__) . '/src/Http/SafeHttpClient.php';
require_once dirname(__DIR__) . '/src/Audit/HtmlAnalyzer.php';
require_once dirname(__DIR__) . '/src/Audit/MediaAudit.php';
require_once dirname(__DIR__) . '/src/Audit/ServerDiagnostics.php';
require_once dirname(__DIR__) . '/src/Audit/JavaScriptAudit.php';
require_once dirname(__DIR__) . '/src/PageSpeed/PageSpeedClient.php';
require_once dirname(__DIR__) . '/src/Storage/AuditHistoryRepository.php';

use JTL\Shop;
use Plugin\MGD_SEOoverride_Plugin\Src\Audit\HtmlAnalyzer;
use Plugin\MGD_SEOoverride_Plugin\Src\Audit\JavaScriptAudit;
use Plugin\MGD_SEOoverride_Plugin\Src\Audit\MediaAudit;
use Plugin\MGD_SEOoverride_Plugin\Src\Audit\ServerDiagnostics;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\SafeHttpClient;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\UrlGuard;
use Plugin\MGD_SEOoverride_Plugin\Src\PageSpeed\PageSpeedClient;
use Plugin\MGD_SEOoverride_Plugin\Src\Storage\AuditHistoryRepository;

$url = mgdSeoShopUrl();
$localResult = null;
$psiResult = null;
$error = null;
$notice = null;
$historyRepo = new AuditHistoryRepository(Shop::Container()->getDB());

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        mgdSeoAssertPost();
        $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
        $submitted = isset($_POST['url']) && is_string($_POST['url']) ? trim($_POST['url']) : '';
        $normalized = UrlGuard::normalize($submitted);
        if ($normalized === null || !UrlGuard::isSameShopHost($normalized)) {
            throw new RuntimeException('Bitte nur eine URL der eigenen Shop-Domain prüfen.');
        }
        $url = $normalized;
        if ($action === 'local') {
            $client = new SafeHttpClient();
            $response = $client->get($url, true, 12);
            $page = (new HtmlAnalyzer())->analyze($response['body'], $response['url']);
            $localResult = [
                'media' => (new MediaAudit($client))->analyze($page),
                'server' => (new ServerDiagnostics($client))->analyze($page),
                'javascript' => (new JavaScriptAudit($client))->analyze($page),
                'page' => $page,
            ];
            try {
                $historyRepo->save('performance_local', $url, [
                    'media_counts' => $localResult['media']['counts'] ?? [],
                    'server' => [
                        'ttfb_ms' => $localResult['server']['ttfb_ms'] ?? null,
                        'http_version' => $localResult['server']['http_version'] ?? null,
                        'content_encoding' => $localResult['server']['content_encoding'] ?? null,
                    ],
                    'js' => [
                        'blocking_count' => $localResult['javascript']['blocking_count'] ?? 0,
                        'known_bytes' => $localResult['javascript']['known_bytes'] ?? 0,
                    ],
                ]);
            } catch (Throwable) {}
        } elseif ($action === 'pagespeed') {
            $strategy = isset($_POST['strategy']) && $_POST['strategy'] === 'desktop' ? 'desktop' : 'mobile';
            $apiKey = trim((string)($oPlugin->getConfig()->getValue('mgd_pagespeed_api_key') ?? ''));
            $psiResult = (new PageSpeedClient())->run($url, $strategy, $apiKey);
            try {
                $historyRepo->save('pagespeed', $url, $psiResult, $strategy);
            } catch (Throwable) {}
            $notice = 'PageSpeed Insights wurde für ' . ($strategy === 'mobile' ? 'Mobile' : 'Desktop') . ' aktualisiert.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$history = [];
try {
    $history = $historyRepo->latest('pagespeed', 20);
} catch (Throwable) {}

mgdSeoHeader('Performance & Core Web Vitals', 'Lokale Shopdiagnose und optional Google PageSpeed Insights direkt im JTL-Backend. Messwerte werden begrenzt als Verlauf gespeichert.');
if ($error !== null) {
    echo mgdSeoAlert(mgdSeoEsc($error), 'bad');
}
if ($notice !== null) {
    echo mgdSeoAlert(mgdSeoEsc($notice), 'ok');
}

echo '<div class="mgd-grid">';
echo '<form method="post" class="mgd-card"><input type="hidden" name="jtl_token" value="' . mgdSeoEsc(mgdSeoToken()) . '"><input type="hidden" name="action" value="local"><h3>Lokale Performance-Diagnose</h3><p>Prüft Bilder, CLS-Risiken, Serverantwort, Cache-Header, Drittanbieter und JavaScript ohne externe API.</p><div class="mgd-field"><label>Shop-URL</label><input type="url" name="url" required value="' . mgdSeoEsc($url) . '"></div><p><button class="mgd-btn" type="submit">Lokale Diagnose starten</button></p></form>';
echo '<form method="post" class="mgd-card"><input type="hidden" name="jtl_token" value="' . mgdSeoEsc(mgdSeoToken()) . '"><input type="hidden" name="action" value="pagespeed"><h3>Google PageSpeed Insights</h3><p>Ruft die offizielle PageSpeed-API auf. Für verlässliche Nutzung empfiehlt sich ein eigener API-Key in den Plugin-Einstellungen.</p><div class="mgd-field"><label>Shop-URL</label><input type="url" name="url" required value="' . mgdSeoEsc($url) . '"></div><div class="mgd-field" style="margin-top:8px"><label>Strategie</label><select name="strategy"><option value="mobile">Mobile</option><option value="desktop">Desktop</option></select></div><p><button class="mgd-btn" type="submit">PageSpeed messen</button></p></form>';
echo '</div>';

if (is_array($psiResult)) {
    $metrics = is_array($psiResult['metrics'] ?? null) ? $psiResult['metrics'] : [];
    echo '<div class="mgd-section"><h3>PageSpeed Ergebnis</h3><div class="mgd-grid">';
    echo mgdSeoCard('Performance', '<div class="mgd-kpi">' . mgdSeoScore($psiResult['performance_score'] ?? null) . '<div>' . mgdSeoEsc(ucfirst((string)$psiResult['strategy'])) . '<br><span class="mgd-small">Lighthouse ' . mgdSeoEsc($psiResult['lighthouse_version'] ?? '') . '</span></div></div>', 'info');
    echo mgdSeoCard('SEO', '<div class="mgd-kpi">' . mgdSeoScore($psiResult['seo_score'] ?? null) . '<div>Google Lighthouse SEO<br><span class="mgd-small">technischer Teilscore</span></div></div>', 'info');
    echo mgdSeoCard('LCP', '<p><strong>' . mgdSeoEsc($metrics['lcp']['display'] ?? '—') . '</strong><br><span class="mgd-small">Largest Contentful Paint</span></p>', 'info');
    echo mgdSeoCard('CLS', '<p><strong>' . mgdSeoEsc($metrics['cls']['display'] ?? '—') . '</strong><br><span class="mgd-small">Cumulative Layout Shift</span></p>', 'info');
    echo mgdSeoCard('TBT', '<p><strong>' . mgdSeoEsc($metrics['tbt']['display'] ?? '—') . '</strong><br><span class="mgd-small">Labornäherung für Main-Thread-Blockierung</span></p>', 'info');
    echo mgdSeoCard('FCP', '<p><strong>' . mgdSeoEsc($metrics['fcp']['display'] ?? '—') . '</strong><br><span class="mgd-small">First Contentful Paint</span></p>', 'info');
    echo '</div>';

    $field = is_array($psiResult['field'] ?? null) ? $psiResult['field'] : [];
    if ($field !== []) {
        echo '<div class="mgd-note"><strong>Echte Nutzerdaten, falls von Google geliefert:</strong> ';
        $parts = [];
        foreach ($field as $name => $metric) {
            if (!is_array($metric)) continue;
            $value = $metric['percentile'] ?? null;
            if ($name === 'cls' && is_numeric($value)) $value = ((float)$value) / 100;
            elseif (is_numeric($value)) $value = round((float)$value) . ' ms';
            $parts[] = strtoupper((string)$name) . ': ' . (string)$value . ' (' . (string)($metric['category'] ?? '') . ')';
        }
        echo mgdSeoEsc(implode(' · ', $parts)) . '</div>';
    }

    $opportunities = is_array($psiResult['opportunities'] ?? null) ? $psiResult['opportunities'] : [];
    if ($opportunities !== []) {
        echo '<div class="mgd-table-wrap"><table class="mgd-table"><thead><tr><th>PageSpeed Hinweis</th><th>Messwert</th><th>Potenzial</th></tr></thead><tbody>';
        foreach ($opportunities as $item) {
            if (!is_array($item)) continue;
            $potential = [];
            if (is_numeric($item['savings_ms'] ?? null) && (float)$item['savings_ms'] > 0) $potential[] = round((float)$item['savings_ms']) . ' ms';
            if (is_numeric($item['savings_bytes'] ?? null) && (int)$item['savings_bytes'] > 0) $potential[] = number_format(((int)$item['savings_bytes']) / 1024, 0, ',', '.') . ' KB';
            echo '<tr><td><strong>' . mgdSeoEsc($item['title'] ?? $item['id'] ?? '') . '</strong><br><span class="mgd-small">' . mgdSeoEsc($item['description'] ?? '') . '</span></td><td>' . mgdSeoEsc($item['display'] ?? '') . '</td><td>' . mgdSeoEsc(implode(' / ', $potential) ?: '—') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';
}

if (is_array($localResult)) {
    $media = $localResult['media'];
    $server = $localResult['server'];
    $js = $localResult['javascript'];
    echo '<div class="mgd-section"><h3>Lokale Diagnose</h3><div class="mgd-grid">';
    echo mgdSeoCard('Server', '<p><strong>TTFB:</strong> ' . mgdSeoEsc($server['ttfb_ms'] ?? '—') . ' ms<br><strong>HTTP:</strong> ' . mgdSeoEsc($server['http_version'] ?? '—') . '<br><strong>Kompression:</strong> ' . mgdSeoEsc($server['content_encoding'] ?? '—') . '<br><strong>JTL Cache:</strong> ' . mgdSeoEsc($server['cache_method'] ?: $server['cache_class'] ?? '—') . '</p>', 'info');
    echo mgdSeoCard('Bilder', '<p><strong>img:</strong> ' . mgdSeoEsc($media['counts']['images'] ?? 0) . '<br><strong>CSS Backgrounds:</strong> ' . mgdSeoEsc($media['counts']['backgrounds'] ?? 0) . '<br><strong>ohne Maße:</strong> ' . mgdSeoEsc($media['counts']['missing_dimensions'] ?? 0) . '<br><strong>groß &gt;250 KB:</strong> ' . mgdSeoEsc($media['counts']['large_files'] ?? 0) . '</p>', 'info');
    echo mgdSeoCard('JavaScript', '<p><strong>Blockierend erkannt:</strong> ' . mgdSeoEsc($js['blocking_count'] ?? 0) . '<br><strong>bekannte lokale Größe:</strong> ' . number_format(((int)($js['known_bytes'] ?? 0)) / 1024, 0, ',', '.') . ' KB<br><strong>Drittanbieter:</strong> ' . count($js['third_party_hosts'] ?? []) . '</p>', 'info');
    echo mgdSeoCard('LCP Kandidat', '<p class="mgd-code">' . mgdSeoEsc($media['lcp_candidate'] ?? 'nicht automatisch ermittelt') . '</p>', 'info');
    echo '</div>';
    echo '<h4>Bilder & CLS</h4>' . mgdSeoRenderIssues($media['issues'] ?? []);
    echo '<h4 style="margin-top:18px">Server & Hosting</h4>' . mgdSeoRenderIssues($server['issues'] ?? []);
    echo '<h4 style="margin-top:18px">JavaScript & INP</h4>' . mgdSeoRenderIssues($js['issues'] ?? []);

    $candidates = is_array($js['defer_candidates'] ?? null) ? $js['defer_candidates'] : [];
    if ($candidates !== []) {
        echo '<div class="mgd-note"><strong>defer-Testkandidaten:</strong><ul class="mgd-list">';
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) continue;
            echo '<li><span class="mgd-code">' . mgdSeoEsc($candidate['pattern'] ?? '') . '</span> · ' . mgdSeoEsc($candidate['reason'] ?? '') . '</li>';
        }
        echo '</ul><strong>Nicht automatisch aktivieren:</strong> zuerst Warenkorb, Varianten, Consent, Suche, Login und Checkout testen.</div>';
    }

    $rows = is_array($media['rows'] ?? null) ? array_slice($media['rows'], 0, 80) : [];
    if ($rows !== []) {
        echo '<h4 style="margin-top:18px">Bildressourcen</h4><div class="mgd-table-wrap"><table class="mgd-table"><thead><tr><th>Typ</th><th>Datei</th><th>Format</th><th>Größe</th><th>Maße</th><th>Loading</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $bytes = $row['bytes'] ?? null;
            echo '<tr><td>' . mgdSeoEsc($row['kind'] ?? '') . '</td><td><span class="mgd-code">' . mgdSeoEsc($row['src'] ?? '') . '</span></td><td>' . mgdSeoEsc($row['extension'] ?? '') . '</td><td>' . (is_numeric($bytes) ? number_format(((int)$bytes) / 1024, 1, ',', '.') . ' KB' : '—') . '</td><td>' . mgdSeoEsc(($row['width'] ?: '—') . ' × ' . ($row['height'] ?: '—')) . '</td><td>' . mgdSeoEsc($row['loading'] ?: 'default') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';
}

if ($history !== []) {
    echo '<div class="mgd-section"><h3>PageSpeed Verlauf</h3><div class="mgd-table-wrap"><table class="mgd-table"><thead><tr><th>Zeit</th><th>Gerät</th><th>Performance</th><th>SEO</th><th>LCP</th><th>CLS</th></tr></thead><tbody>';
    foreach ($history as $entry) {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        $m = is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [];
        echo '<tr><td>' . mgdSeoEsc($entry['created_at'] ?? '') . '</td><td>' . mgdSeoEsc($entry['strategy'] ?? '') . '</td><td>' . mgdSeoScore($payload['performance_score'] ?? null) . '</td><td>' . mgdSeoScore($payload['seo_score'] ?? null) . '</td><td>' . mgdSeoEsc($m['lcp']['display'] ?? '—') . '</td><td>' . mgdSeoEsc($m['cls']['display'] ?? '—') . '</td></tr>';
    }
    echo '</tbody></table></div></div>';
}

echo '<div class="mgd-note"><strong>Messprinzip:</strong> Ein einzelner Lighthouse-Lauf kann schwanken. Für Entscheidungen mehrere Läufe vergleichen und, sofern verfügbar, echte CrUX-Nutzerdaten stärker gewichten als einen einzelnen Labortest.</div>';
mgdSeoFooter();
