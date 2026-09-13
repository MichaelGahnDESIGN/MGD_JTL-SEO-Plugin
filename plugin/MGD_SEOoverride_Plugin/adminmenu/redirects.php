<?php declare(strict_types=1);
require __DIR__ . '/_common.php';
require_once dirname(__DIR__) . '/src/Monitor/NotFoundMonitor.php';
require_once dirname(__DIR__) . '/src/Storage/AuditHistoryRepository.php';

use JTL\Shop;
use Plugin\MGD_SEOoverride_Plugin\Src\Monitor\NotFoundMonitor;
use Plugin\MGD_SEOoverride_Plugin\Src\Storage\AuditHistoryRepository;

$monitor = new NotFoundMonitor(Shop::Container()->getDB());
$error = null;
$notice = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        mgdSeoAssertPost();
        $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
        if ($action === 'clear404') {
            $monitor->clear();
            $notice = 'Die vom Plugin gespeicherten 404-Pfade wurden gelöscht.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$notFound = [];
try {
    $notFound = $monitor->latest(200);
} catch (Throwable $e) {
    $error = $error ?? '404-Historie konnte nicht gelesen werden: ' . $e->getMessage();
}
$lastCrawl = null;
try {
    $entries = (new AuditHistoryRepository(Shop::Container()->getDB()))->latest('crawl', 1);
    $lastCrawl = $entries[0]['payload'] ?? null;
} catch (Throwable) {}

mgdSeoHeader('Redirects & 404 Monitor', 'Findet echte 404-Aufrufe im Shop und zeigt Weiterleitungsketten aus dem letzten begrenzten Crawl. IP-Adressen und Query-Parameter werden nicht gespeichert.');
if ($error !== null) echo mgdSeoAlert(mgdSeoEsc($error), 'bad');
if ($notice !== null) echo mgdSeoAlert(mgdSeoEsc($notice), 'ok');

$enabled = mgdSeoYes($oPlugin->getConfig()->getValue('mgd_404_monitor'));
echo '<div class="mgd-grid">';
echo mgdSeoCard('404 Monitoring', '<p>' . ($enabled ? mgdSeoPill('Aktiv', 'good') : mgdSeoPill('Deaktiviert', 'warn')) . '</p><p class="mgd-small">Gespeichert werden ausschließlich URL-Pfad, optional interner Referrer-Pfad, Trefferzahl sowie erster/letzter Zeitpunkt.</p>', $enabled ? 'ok' : 'warn');
echo mgdSeoCard('Erfasste 404-Pfade', '<p><strong>' . count($notFound) . '</strong> zuletzt gespeicherte Pfade</p>', count($notFound) > 0 ? 'warn' : 'ok');
$redirectCount = is_array($lastCrawl) ? count($lastCrawl['redirect_chains'] ?? []) : 0;
echo mgdSeoCard('Letzter Crawl', '<p><strong>' . $redirectCount . '</strong> URL(s) mit Weiterleitungskette</p><span class="mgd-small">Crawl-Daten werden im Bereich „Crawl & Links“ aktualisiert.</span>', 'info');
echo '</div>';

if ($notFound !== []) {
    echo '<div class="mgd-section"><h3>Echte 404-Aufrufe</h3><div class="mgd-table-wrap"><table class="mgd-table"><thead><tr><th>Treffer</th><th>Pfad</th><th>interner Referrer</th><th>Erstmals</th><th>Zuletzt</th></tr></thead><tbody>';
    foreach ($notFound as $row) {
        echo '<tr><td><strong>' . mgdSeoEsc($row['hits']) . '</strong></td><td><span class="mgd-code">' . mgdSeoEsc($row['path']) . '</span></td><td><span class="mgd-code">' . mgdSeoEsc($row['referrer_path'] ?? '—') . '</span></td><td>' . mgdSeoEsc($row['first_seen']) . '</td><td>' . mgdSeoEsc($row['last_seen']) . '</td></tr>';
    }
    echo '</tbody></table></div><form method="post" style="margin-top:12px" onsubmit="return confirm(\'404-Historie wirklich löschen?\')"><input type="hidden" name="jtl_token" value="' . mgdSeoEsc(mgdSeoToken()) . '"><input type="hidden" name="action" value="clear404"><button class="mgd-btn danger" type="submit">404-Historie löschen</button></form></div>';
} else {
    echo mgdSeoAlert('Noch keine 404-Pfade gespeichert. Das ist gut oder das Monitoring wurde gerade erst aktiviert.', 'ok');
}

if (is_array($lastCrawl)) {
    $redirects = is_array($lastCrawl['redirect_chains'] ?? null) ? $lastCrawl['redirect_chains'] : [];
    if ($redirects !== []) {
        echo '<div class="mgd-section"><h3>Weiterleitungsketten aus letztem Crawl</h3><div class="mgd-table-wrap"><table class="mgd-table"><thead><tr><th>Start</th><th>Kette</th><th>Finale URL</th><th>Status</th></tr></thead><tbody>';
        foreach ($redirects as $row) {
            if (!is_array($row)) continue;
            $chain = [];
            foreach ($row['chain'] ?? [] as $step) {
                if (is_array($step)) $chain[] = (string)($step['status'] ?? '') . ' → ' . (string)($step['to'] ?? '');
            }
            echo '<tr><td><span class="mgd-code">' . mgdSeoEsc($row['url'] ?? '') . '</span></td><td>' . mgdSeoEsc(implode(' | ', $chain)) . '</td><td><span class="mgd-code">' . mgdSeoEsc($row['final_url'] ?? '') . '</span></td><td>' . mgdSeoEsc($row['final_status'] ?? '') . '</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }
    $broken = is_array($lastCrawl['broken'] ?? null) ? $lastCrawl['broken'] : [];
    if ($broken !== []) {
        echo '<div class="mgd-section"><h3>Broken Links aus letztem Crawl</h3><div class="mgd-table-wrap"><table class="mgd-table"><thead><tr><th>Status</th><th>Ziel</th><th>Referrer</th></tr></thead><tbody>';
        foreach ($broken as $row) {
            if (!is_array($row)) continue;
            echo '<tr><td>' . mgdSeoPill((string)($row['status'] ?? 0), 'bad') . '</td><td><span class="mgd-code">' . mgdSeoEsc($row['url'] ?? '') . '</span></td><td>' . mgdSeoEsc(implode(', ', $row['referrers'] ?? [])) . '</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }
}

echo '<div class="mgd-note"><strong>JTL Redirects:</strong> Dieses Modul verändert keine Weiterleitungen automatisch. Erkannte Fehler sollten anschließend gezielt über JTLs Weiterleitungsverwaltung oder die ursächliche interne Verlinkung korrigiert werden.</div>';
mgdSeoFooter();
