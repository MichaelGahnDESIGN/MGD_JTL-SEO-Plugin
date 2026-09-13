<?php declare(strict_types=1);
require __DIR__ . '/_common.php';
require_once dirname(__DIR__) . '/src/Update/GitHubReleaseChecker.php';
require_once dirname(__DIR__) . '/src/Update/SafePluginUpdater.php';

use Plugin\MGD_SEOoverride_Plugin\Src\Update\GitHubReleaseChecker;
use Plugin\MGD_SEOoverride_Plugin\Src\Update\SafePluginUpdater;

$current = (string)$oPlugin->getMeta()->getVersion();
$enabled = mgdSeoYes($oPlugin->getConfig()->getValue('mgd_update_notices'));
$checker = new GitHubReleaseChecker();
$updater = new SafePluginUpdater();
$force = false;
$action = '';
$verified = null;
$applyResult = null;
$error = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        mgdSeoAssertPost();
        $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
        $force = $action === 'force_check';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$result = $checker->check($current, $enabled, $force);
if ($error === null && ($action === 'verify' || $action === 'apply_hotfix')) {
    try {
        if (($result['ok'] ?? false) !== true || ($result['update'] ?? false) !== true) {
            throw new RuntimeException('Es steht kein neueres verifizierbares Release bereit.');
        }
        $verified = $updater->prepare($result, $current);
        if ($action === 'apply_hotfix') {
            if (!mgdSeoYes($oPlugin->getConfig()->getValue('mgd_safe_updates'))) {
                throw new RuntimeException('Sichere Self-Updates sind in den Plugin-Einstellungen deaktiviert.');
            }
            $applyResult = $updater->applyVerifiedHotfix($verified);
        } elseif (($verified['requires_jtl_update'] ?? true) === true) {
            $work = $verified['work_dir'] ?? null;
            $zip = $verified['zip_file'] ?? null;
            if (is_string($zip) && is_file($zip)) @unlink($zip);
            if (is_string($work) && is_dir($work)) @rmdir($work);
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

mgdSeoHeader('Updates & Integrität', 'Prüft stabile GitHub-Releases, SHA256, Release-Manifest, Plugin-ID und ZIP-Struktur. JTL-Lifecycle-Updates werden bewusst über den nativen Plugin-Manager abgeschlossen.');
if ($error !== null) echo mgdSeoAlert(mgdSeoEsc($error), 'bad');
if (is_array($applyResult) && ($applyResult['ok'] ?? false) === true) echo mgdSeoAlert(mgdSeoEsc($applyResult['message'] ?? 'Update erfolgreich.'), 'ok');

if (!$enabled) {
    echo mgdSeoCard('Updateprüfung deaktiviert', '<p>Aktiviere „GitHub-Updatehinweise“ in den Plugin-Einstellungen, wenn der Shop neue stabile Releases prüfen darf.</p>', 'warn');
} elseif (($result['ok'] ?? false) !== true) {
    echo mgdSeoCard('GitHub momentan nicht sicher prüfbar', '<p>Der Shop läuft unverändert weiter.</p><p class="mgd-small">' . mgdSeoEsc($result['error'] ?? '') . '</p>', 'warn');
} elseif (($result['update'] ?? false) === true) {
    $body = '<p><strong>Installiert:</strong> ' . mgdSeoEsc($current) . '<br><strong>Verfügbar:</strong> ' . mgdSeoEsc($result['latest']) . '<br><strong>Veröffentlicht:</strong> ' . mgdSeoEsc($result['published_at'] ?? '—') . '</p>';
    if (!empty($result['download_url'])) $body .= '<a class="mgd-btn secondary" target="_blank" rel="noopener noreferrer" href="' . mgdSeoEsc($result['download_url']) . '">Release-ZIP</a> ';
    $body .= '<a class="mgd-btn secondary" target="_blank" rel="noopener noreferrer" href="' . mgdSeoEsc($result['release_url']) . '">Release ansehen</a>';
    $body .= '<form method="post" style="display:inline-block;margin-left:6px"><input type="hidden" name="jtl_token" value="' . mgdSeoEsc(mgdSeoToken()) . '"><input type="hidden" name="action" value="verify"><button class="mgd-btn" type="submit">Paket sicher prüfen</button></form>';
    echo mgdSeoCard('Update verfügbar', $body, 'ok');
} else {
    echo mgdSeoCard('Aktuell', '<p>Version <strong>' . mgdSeoEsc($current) . '</strong> ist auf dem neuesten stabilen Stand.</p>', 'ok');
}

echo '<form method="post" style="margin:12px 0"><input type="hidden" name="jtl_token" value="' . mgdSeoEsc(mgdSeoToken()) . '"><input type="hidden" name="action" value="force_check"><button class="mgd-btn secondary" type="submit">GitHub jetzt erneut prüfen</button></form>';

if (is_array($verified)) {
    $manifest = is_array($verified['manifest'] ?? null) ? $verified['manifest'] : [];
    echo '<div class="mgd-section"><h3>Verifizierungsbericht</h3><div class="mgd-grid">';
    echo mgdSeoCard('SHA256', '<p class="mgd-code">' . mgdSeoEsc($verified['sha256'] ?? '') . '</p><span class="mgd-small">ZIP stimmt mit Release-Prüfsumme und Manifest überein.</span>', 'ok');
    echo mgdSeoCard('ZIP Struktur', '<p><strong>Dateien:</strong> ' . mgdSeoEsc($verified['zip']['files'] ?? 0) . '<br><strong>entpackt:</strong> ' . number_format(((int)($verified['zip']['uncompressed_bytes'] ?? 0)) / 1024, 0, ',', '.') . ' KB</p><span class="mgd-small">Pfadtraversal und Symlinks werden abgewiesen.</span>', 'ok');
    echo mgdSeoCard('JTL Mindestversion', '<p><strong>' . mgdSeoEsc($manifest['min_shop_version'] ?? '—') . '</strong></p>', 'info');
    echo mgdSeoCard('Update-Modus', (($verified['requires_jtl_update'] ?? true) === true)
        ? '<p>' . mgdSeoPill('JTL Lifecycle erforderlich', 'warn') . '</p><span class="mgd-small">Dieses Release verändert Version, info.xml, Einstellungen oder Migrationen. Deshalb muss JTL den Update-Schritt ausführen.</span>'
        : '<p>' . mgdSeoPill('Self-Update zulässig', 'good') . '</p><span class="mgd-small">Nur für explizit freigegebene Datei-Hotfixes ohne JTL-Metadatenänderung.</span>',
        (($verified['requires_jtl_update'] ?? true) === true) ? 'warn' : 'ok');
    echo '</div>';
    if (($verified['requires_jtl_update'] ?? true) === true) {
        echo mgdSeoAlert('Das Paket ist kryptografisch geprüft. Für dieses Versionsupdate bitte die Release-ZIP über Plugins → Plugin-Manager → Upload einspielen und anschließend den JTL-Update-Button verwenden. Dadurch werden info.xml, neue Einstellungen und Migrationen korrekt verarbeitet.', 'warn');
    } elseif (($verified['self_update_safe'] ?? false) === true && mgdSeoYes($oPlugin->getConfig()->getValue('mgd_safe_updates'))) {
        echo '<form method="post" onsubmit="return confirm(\'Den verifizierten Datei-Hotfix jetzt atomar einspielen?\')"><input type="hidden" name="jtl_token" value="' . mgdSeoEsc(mgdSeoToken()) . '"><input type="hidden" name="action" value="apply_hotfix"><button class="mgd-btn" type="submit">Verifizierten Hotfix jetzt installieren</button></form>';
    }
    echo '</div>';
}

echo '<div class="mgd-note"><strong>Warum nicht blind alles automatisch?</strong> JTL übernimmt neue Admin-Menüs, Einstellungen, Versionen und Datenbankmigrationen im eigenen Plugin-Update-Lifecycle. Das Plugin überschreibt deshalb normale Versionsupdates nicht hinter JTLs Rücken. Der Ein-Klick-Pfad ist auf Release-Pakete beschränkt, die im signierten Manifest ausdrücklich als dateireiner Hotfix ohne Lifecycle-Änderung markiert sind. Vor dem Austausch werden ZIP, Pfade, Plugin-ID, Versionsdaten und SHA256 geprüft; die alte Installation wird atomar gesichert und bei Aktivierungsfehlern zurückgerollt.</div>';
mgdSeoFooter();
