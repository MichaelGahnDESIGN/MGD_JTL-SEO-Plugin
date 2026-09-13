<?php declare(strict_types=1);
require __DIR__ . '/_common.php';
require_once dirname(__DIR__) . '/src/Http/UrlGuard.php';
require_once dirname(__DIR__) . '/src/Http/SafeHttpClient.php';
require_once dirname(__DIR__) . '/src/Audit/HtmlAnalyzer.php';
require_once dirname(__DIR__) . '/src/Audit/MediaAudit.php';
require_once dirname(__DIR__) . '/src/Audit/CssAudit.php';
require_once dirname(__DIR__) . '/src/Audit/ServerDiagnostics.php';
require_once dirname(__DIR__) . '/src/Audit/JavaScriptAudit.php';
require_once dirname(__DIR__) . '/src/PageSpeed/PageSpeedClient.php';
require_once dirname(__DIR__) . '/src/Storage/AuditHistoryRepository.php';
require_once dirname(__DIR__) . '/src/Optimization/CacheConfigGenerator.php';
require_once dirname(__DIR__) . '/src/Optimization/ImageOptimizer.php';

use JTL\Shop;
use Plugin\MGD_SEOoverride_Plugin\Src\Audit\CssAudit;
use Plugin\MGD_SEOoverride_Plugin\Src\Audit\HtmlAnalyzer;
use Plugin\MGD_SEOoverride_Plugin\Src\Audit\JavaScriptAudit;
use Plugin\MGD_SEOoverride_Plugin\Src\Audit\MediaAudit;
use Plugin\MGD_SEOoverride_Plugin\Src\Audit\ServerDiagnostics;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\SafeHttpClient;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\UrlGuard;
use Plugin\MGD_SEOoverride_Plugin\Src\Optimization\CacheConfigGenerator;
use Plugin\MGD_SEOoverride_Plugin\Src\Optimization\ImageOptimizer;
use Plugin\MGD_SEOoverride_Plugin\Src\PageSpeed\PageSpeedClient;
use Plugin\MGD_SEOoverride_Plugin\Src\Storage\AuditHistoryRepository;

$url = mgdSeoShopUrl();
$localResult = null;
$psiResult = null;
$imageResult = null;
$error = null;
$notice = null;
$restoreToken = null;
$restoreUrl = null;
$historyRepo = new AuditHistoryRepository(Shop::Container()->getDB());

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        mgdSeoAssertPost();
        $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
        $submitted = isset($_POST['url']) && is_string($_POST['url']) ? trim($_POST['url']) : mgdSeoShopUrl();
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
                'css' => (new CssAudit($client))->analyze($page),
                'server' => (new ServerDiagnostics($client))->analyze($page),
                'javascript' => (new JavaScriptAudit($client))->analyze($page),
                'page' => $page,
            ];
            try {
                $historyRepo->save('performance_local', $url, [
                    'media_counts' => $localResult['media']['counts'] ?? [],
                    'css' => [
                        'blocking_count' => $localResult['css']['blocking_count'] ?? 0,
                        'known_bytes' => $localResult['css']['known_bytes'] ?? 0,
                    ],
                    'server' => [
                        'ttfb_ms' => $localResult['server']['ttfb_ms'] ?? null,
                        'http_version' => $localResult['server']['http_version'] ?? null,
                        'content_encoding' => $localResult['server']['content_encoding'] ?? null,
                    ],
                    'js' => [
                        'blocking_count' => $localResult['javascript']['blocking_count'] ?? 0,
                        'known_bytes' => $localResult['javascript']['known_bytes'] ?? 0,
                        'tracking' => $localResult['javascript']['tracking'] ?? [],
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
        } elseif ($action === 'optimize_image') {
            $imageUrl = isset($_POST['image_url']) && is_string($_POST['image_url']) ? trim($_POST['image_url']) : '';
            $quality = (int)($oPlugin->getConfig()->getValue('mgd_image_quality') ?? 82);
            $imageResult = (new ImageOptimizer())->optimize($imageUrl, $quality);
            $notice = (string)($imageResult['message'] ?? 'Bildoptimierung abgeschlossen.');
            if (($imageResult['changed'] ?? false) === true && is_string($imageResult['backup_token'] ?? null)) {
                $restoreToken = $imageResult['backup_token'];
                $restoreUrl = $imageUrl;
            }
        } elseif ($action === 'restore_image') {
            $imageUrl = isset($_POST['image_url']) && is_string($_POST['image_url']) ? trim($_POST['image_url']) : '';
            $backupToken = isset($_POST['backup_token']) && is_string($_POST['backup_token']) ? trim($_POST['backup_token']) : '';
            (new ImageOptimizer())->restore($imageUrl, $backupToken);
            $notice = 'Das gesicherte Originalbild wurde wiederhergestellt.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$history = [];
try {
    $history = $historyRepo->latest('pagespeed', 20);
} catch (Throwable) {}

$config = $oPlugin->getConfig();
$delayMode = (string)($config->getValue('mgd_third_party_delay_mode') ?? 'off');
$delayPatterns = trim((string)($config->getValue('mgd_third_party_delay_patterns') ?? ''));
$criticalCss = trim((string)($config->getValue('mgd_critical_css') ?? ''));
$asyncCssPatterns = trim((string)($config->getValue('mgd_async_css_patterns') ?? ''));

mgdSeoHeader('Performance & Core Web Vitals', 'PageSpeed, Bilder, CSS, JavaScript, Tracking, Caching und Serverdiagnose für JTL-Shop 5. Kritische Eingriffe bleiben standardmäßig aus.');
if ($error !== null) {
    echo mgdSeoAlert(mgdSeoEsc($error), 'bad');
}
if ($notice !== null) {
    echo mgdSeoAlert(mgdSeoEsc($notice), 'ok');
}

if ($restoreToken !== null && $restoreUrl !== null) {
    echo '<form method="post" class="mgd-alert warn"><input type="hidden" name="jtl_token" value="' . mgdSeoEsc(mgdSeoToken()) . '"><input type="hidden" name="action" value="restore_image"><input type="hidden" name="url" value="' . mgdSeoEsc($url) . '"><input type="hidden" name="image_url" value="' . mgdSeoEsc($restoreUrl) . '"><input type="hidden" name="backup_token" value="' . mgdSeoEsc($restoreToken) . '"><strong>Rollback verfügbar:</strong> Das Original dieses Bildes wurde gesichert. <button class="mgd-btn secondary" type="submit">Original jetzt wiederherstellen</button></form>';
}

echo '<div class="mgd-grid">';
echo '<form method="post" class="mgd-card"><input type="hidden" name="jtl_token" value="' . mgdSeoEsc(mgdSeoToken()) . '"><input type="hidden" name="action" value="local"><h3>Lokale Performance-Diagnose</h3><p>Prüft Bilder, CLS, CSS, Server, Cache-Header, Tracking-Drittanbieter und JavaScript ohne externe API.</p><div class="mgd-field"><label>Shop-URL</label><input type="url" name="url" required value="' . mgdSeoEsc($url) . '"></div><p><button class="mgd-btn" type="submit">Lokale Diagnose starten</button></p></form>';
echo '<form method="post" class="mgd-card"><input type="hidden" name="jtl_token" value="' . mgdSeoEsc(mgdSeoToken()) . '"><input type="hidden" name="action" value="pagespeed"><h3>Google PageSpeed Insights</h3><p>Ruft die offizielle PageSpeed-API auf. Für regelmäßige Nutzung empfiehlt sich ein eigener API-Key in den Einstellungen.</p><div class="mgd-field"><label>Shop-URL</label><input type="url" name="url" required value="' . mgdSeoEsc($url) . '"></div><div class="mgd-field" style="margin-top:8px"><label>Strategie</label><select name="strategy"><option value="mobile">Mobile</option><option value="desktop">Desktop</option></select></div><p><button class="mgd-btn" type="submit">PageSpeed messen</button></p></form>';
echo '</div>';

echo '<div class="mgd-section"><h3>Aktive Optimierungsmodi</h3><div class="mgd-grid">';
echo mgdSeoCard('Drittanbieter Delay', '<p>' . mgdSeoPill($delayMode === 'off' ? 'Aus' : $delayMode, $delayMode === 'off' ? 'good' : 'warn') . '</p><p class="mgd-small">Muster: ' . mgdSeoEsc($delayPatterns === '' ? 'keine' : $delayPatterns) . '</p><p>Tracking-Skripte werden nur verzögert, wenn Modus und Muster ausdrücklich gesetzt wurden. Zahlungs-, Checkout-, Consent- und CAPTCHA-Muster sind gesperrt.</p>', $delayMode === 'off' ? 'ok' : 'warn');
echo mgdSeoCard('Critical CSS', '<p>' . mgdSeoPill($criticalCss === '' ? 'Nicht gesetzt' : strlen($criticalCss) . ' Bytes', $criticalCss === '' ? 'info' : 'warn') . '</p><p class="mgd-small">Nachlade-Muster: ' . mgdSeoEsc($asyncCssPatterns === '' ? 'keine' : $asyncCssPatterns) . '</p><p>Große Stylesheets werden nur nachgeladen, wenn Critical CSS vorhanden ist und ein Muster explizit freigegeben wurde.</p>', $criticalCss === '' ? 'info' : 'warn');
echo mgdSeoCard('Bildoptimierer', '<p>WebP/JPEG können einzeln und mit Backup neu komprimiert werden. Ersetzt wird nur, wenn mindestens 3 % gespart werden.</p><p class="mgd-small">Qualität: ' . mgdSeoEsc($config->getValue('mgd_image_quality') ?? 82) . '</p>', 'info');
echo mgdSeoCard('Cache-Konfigurator', '<p>Das Plugin schreibt keine .htaccess. Unten wird ein Apache-Vorschlag für versionierte statische Assets erzeugt.</p>', 'info');
echo '</div></div>';

if (is_array($psiResult)) {
    $metrics = is_array($psiResult['metrics'] ?? null) ? $psiResult['metrics'] : [];
    echo '<div class="mgd-section"><h3>PageSpeed Ergebnis</h3><div class="mgd-grid">';
    echo mgdSeoCard('Performance', '<div class="mgd-kpi">' . mgdSeoScore($psiResult['performance_score'] ?? null) . '<div>' . mgdSeoEsc(ucfirst((string)($psiResult['strategy'] ?? ''))) . '<br><span class="mgd-small">Lighthouse ' . mgdSeoEsc($psiResult['lighthouse_version'] ?? '') . '</span></div></div>', 'info');
    echo mgdSeoCard('SEO', '<div class="mgd-kpi">' . mgdSeoScore($psiResult['seo_score'] ?? null) . '<div>Google Lighthouse SEO<br><span class="mgd-small">technischer Teilscore</span></div></div>', 'info');
    echo mgdSeoCard('LCP', '<p><strong>' . mgdSeoEsc($metrics['lcp']['display'] ?? '—') . '</strong><br><span class="mgd-small">Largest Contentful Paint</span></p>', 'info');
    echo mgdSeoCard('CLS', '<p><strong>' . mgdSeoEsc($metrics['cls']['display'] ?? '—') . '</strong><br><span class="mgd-small">Cumulative Layout Shift</span></p>', 'info');
    echo mgdSeoCard('TBT', '<p><strong>' . mgdSeoEsc($metrics['tbt']['display'] ?? '—') . '</strong><br><span class="mgd-small">Main-Thread-Blockierung im Lab</span></p>', 'info');
    echo mgdSeoCard('FCP', '<p><strong>' . mgdSeoEsc($metrics['fcp']['display'] ?? '—') . '</strong><br><span class="mgd-small">First Contentful Paint</span></p>', 'info');
    echo '</div>';

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

if (is_array($imageResult)) {
    $before = (int)($imageResult['before_bytes'] ?? 0);
    $after = (int)($imageResult['after_bytes'] ?? 0);
    echo '<div class="mgd-section"><h3>Bildoptimierung</h3>' . mgdSeoCard('Ergebnis', '<p><strong>Vorher:</strong> ' . number_format($before / 1024, 1, ',', '.') . ' KB<br><strong>Nachher:</strong> ' . number_format($after / 1024, 1, ',', '.') . ' KB<br><strong>Ersparnis:</strong> ' . number_format(max(0, $before - $after) / 1024, 1, ',', '.') . ' KB</p><p class="mgd-code">' . mgdSeoEsc($imageResult['url'] ?? '') . '</p>', ($imageResult['changed'] ?? false) ? 'ok' : 'info') . '</div>';
}

if (is_array($localResult)) {
    $media = $localResult['media'];
    $css = $localResult['css'];
    $server = $localResult['server'];
    $js = $localResult['javascript'];
    echo '<div class="mgd-section"><h3>Lokale Diagnose</h3><div class="mgd-grid">';
    echo mgdSeoCard('Server', '<p><strong>TTFB:</strong> ' . mgdSeoEsc($server['ttfb_ms'] ?? '—') . ' ms<br><strong>HTTP:</strong> ' . mgdSeoEsc($server['http_version'] ?? '—') . '<br><strong>Kompression:</strong> ' . mgdSeoEsc($server['content_encoding'] ?? '—') . '<br><strong>JTL Cache:</strong> ' . mgdSeoEsc($server['cache_method'] ?: $server['cache_class'] ?? '—') . '</p>', 'info');
    echo mgdSeoCard('Bilder', '<p><strong>img:</strong> ' . mgdSeoEsc($media['counts']['images'] ?? 0) . '<br><strong>CSS Backgrounds:</strong> ' . mgdSeoEsc($media['counts']['backgrounds'] ?? 0) . '<br><strong>ohne Maße:</strong> ' . mgdSeoEsc($media['counts']['missing_dimensions'] ?? 0) . '<br><strong>groß &gt;250 KB:</strong> ' . mgdSeoEsc($media['counts']['large_files'] ?? 0) . '</p>', 'info');
    echo mgdSeoCard('CSS', '<p><strong>blockierend:</strong> ' . mgdSeoEsc($css['blocking_count'] ?? 0) . '<br><strong>bekannte lokale Größe:</strong> ' . number_format(((int)($css['known_bytes'] ?? 0)) / 1024, 0, ',', '.') . ' KB<br><strong>groß &gt;50 KB:</strong> ' . mgdSeoEsc($css['large_count'] ?? 0) . '</p>', 'info');
    echo mgdSeoCard('JavaScript', '<p><strong>blockierend:</strong> ' . mgdSeoEsc($js['blocking_count'] ?? 0) . '<br><strong>bekannte lokale Größe:</strong> ' . number_format(((int)($js['known_bytes'] ?? 0)) / 1024, 0, ',', '.') . ' KB<br><strong>Drittanbieter:</strong> ' . count($js['third_party_hosts'] ?? []) . '</p>', 'info');
    echo mgdSeoCard('LCP Kandidat', '<p class="mgd-code">' . mgdSeoEsc($media['lcp_candidate'] ?? 'nicht automatisch ermittelt') . '</p>', 'info');
    echo '</div>';

    echo '<h4>Bilder & CLS</h4>' . mgdSeoRenderIssues($media['issues'] ?? []);
    echo '<h4 style="margin-top:18px">CSS & Renderpfad</h4>' . mgdSeoRenderIssues($css['issues'] ?? []);
    echo '<h4 style="margin-top:18px">Server & Hosting</h4>' . mgdSeoRenderIssues($server['issues'] ?? []);
    echo '<h4 style="margin-top:18px">JavaScript, Tracking & INP</h4>' . mgdSeoRenderIssues($js['issues'] ?? []);

    $tracking = is_array($js['tracking'] ?? null) ? $js['tracking'] : [];
    $delaySuggestions = is_array($js['recommended_delay_patterns'] ?? null) ? $js['recommended_delay_patterns'] : [];
    if ($tracking !== [] || $delaySuggestions !== []) {
        echo '<div class="mgd-note"><strong>Tracking-Erkennung:</strong> GTM ' . mgdSeoEsc($tracking['gtm'] ?? 0) . ' · direkte gtag.js ' . mgdSeoEsc($tracking['gtag'] ?? 0) . ' · Smarketer ' . mgdSeoEsc($tracking['smarketer'] ?? 0) . '.';
        if ($delaySuggestions !== []) {
            echo '<br><strong>Mögliche Delay-Muster für einen kontrollierten Test:</strong> <span class="mgd-code">' . mgdSeoEsc(implode("\n", $delaySuggestions)) . '</span>';
        }
        echo '<br><span class="mgd-small">Vor Aktivierung Consent, Analytics, Ads-Conversions und Shopfunktionen testen. GTM + gtag parallel kann beabsichtigt sein, sollte aber auf doppelte Tags geprüft werden.</span></div>';
    }

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
        echo '<h4 style="margin-top:18px">Bildressourcen</h4><div class="mgd-table-wrap"><table class="mgd-table"><thead><tr><th>Typ</th><th>Datei</th><th>Format</th><th>Größe</th><th>Maße</th><th>Optimieren</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $bytes = $row['bytes'] ?? null;
            $src = (string)($row['src'] ?? '');
            $extension = strtolower((string)($row['extension'] ?? ''));
            $button = '—';
            if ($src !== '' && in_array($extension, ['webp', 'jpg', 'jpeg'], true)) {
                $button = '<form method="post"><input type="hidden" name="jtl_token" value="' . mgdSeoEsc(mgdSeoToken()) . '"><input type="hidden" name="action" value="optimize_image"><input type="hidden" name="url" value="' . mgdSeoEsc($url) . '"><input type="hidden" name="image_url" value="' . mgdSeoEsc($src) . '"><button class="mgd-btn secondary" type="submit">Mit Backup optimieren</button></form>';
            }
            echo '<tr><td>' . mgdSeoEsc($row['kind'] ?? '') . '</td><td><span class="mgd-code">' . mgdSeoEsc($src) . '</span></td><td>' . mgdSeoEsc($extension) . '</td><td>' . (is_numeric($bytes) ? number_format(((int)$bytes) / 1024, 1, ',', '.') . ' KB' : '—') . '</td><td>' . mgdSeoEsc((($row['width'] ?? '') ?: '—') . ' × ' . (($row['height'] ?? '') ?: '—')) . '</td><td>' . $button . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    $cssRows = is_array($css['rows'] ?? null) ? array_slice($css['rows'], 0, 40) : [];
    if ($cssRows !== []) {
        echo '<h4 style="margin-top:18px">Stylesheets</h4><div class="mgd-table-wrap"><table class="mgd-table"><thead><tr><th>Datei</th><th>Größe</th><th>Media</th><th>Renderpfad</th><th>Cache-Control</th></tr></thead><tbody>';
        foreach ($cssRows as $row) {
            if (!is_array($row)) continue;
            $bytes = $row['bytes'] ?? null;
            echo '<tr><td><span class="mgd-code">' . mgdSeoEsc($row['href'] ?? '') . '</span></td><td>' . (is_numeric($bytes) ? number_format(((int)$bytes) / 1024, 1, ',', '.') . ' KB' : '—') . '</td><td>' . mgdSeoEsc(($row['media'] ?? '') ?: 'all') . '</td><td>' . (($row['blocking'] ?? false) ? mgdSeoPill('normal/blockierend', 'warn') : mgdSeoPill('bedingt', 'good')) . '</td><td>' . mgdSeoEsc($row['cache_control'] ?? '—') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';
}

echo '<div class="mgd-section"><h3>Apache Cache-Konfigurator</h3><div class="mgd-note">Das Plugin ändert die Serverkonfiguration nicht selbst. Der folgende Vorschlag ist für versionierte statische Assets gedacht und muss vor Einsatz mit Hosting/CDN geprüft werden.</div><textarea readonly style="width:100%;min-height:360px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace">' . mgdSeoEsc(CacheConfigGenerator::apacheSnippet()) . '</textarea><ul class="mgd-list">';
foreach (CacheConfigGenerator::notes() as $note) {
    echo '<li>' . mgdSeoEsc($note) . '</li>';
}
echo '</ul></div>';

if ($history !== []) {
    echo '<div class="mgd-section"><h3>PageSpeed Verlauf</h3><div class="mgd-table-wrap"><table class="mgd-table"><thead><tr><th>Zeit</th><th>Gerät</th><th>Performance</th><th>SEO</th><th>LCP</th><th>CLS</th></tr></thead><tbody>';
    foreach ($history as $entry) {
        $payload = is_array($entry['payload'] ?? null) ? $entry['payload'] : [];
        $metrics = is_array($payload['metrics'] ?? null) ? $payload['metrics'] : [];
        echo '<tr><td>' . mgdSeoEsc($entry['created_at'] ?? '') . '</td><td>' . mgdSeoEsc($entry['context'] ?? $payload['strategy'] ?? '') . '</td><td>' . mgdSeoEsc($payload['performance_score'] ?? '—') . '</td><td>' . mgdSeoEsc($payload['seo_score'] ?? '—') . '</td><td>' . mgdSeoEsc($metrics['lcp']['display'] ?? '—') . '</td><td>' . mgdSeoEsc($metrics['cls']['display'] ?? '—') . '</td></tr>';
    }
    echo '</tbody></table></div></div>';
}

echo '<div class="mgd-note"><strong>Sicherheitsprinzip:</strong> jQuery, JTL-Core, Checkout, Zahlungsanbieter, Consent und CAPTCHA werden nicht automatisch verzögert. Drittanbieter-Delay, große Stylesheets und Bildneukompression sind Opt-in und müssen nach Aktivierung im Shop getestet werden.</div>';
mgdSeoFooter();
