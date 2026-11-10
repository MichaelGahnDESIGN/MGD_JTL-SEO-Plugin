<?php declare(strict_types=1);
require __DIR__ . '/_common.php';
require_once dirname(__DIR__) . '/src/UpdateBridge.php';

use JTL\Helpers\Form;
use JTL\Shop;
use Plugin\MGD_SEOoverride_Plugin\UpdateBridge;

// Updates Menue - MGD Update Client
UpdateBridge::registerAutoload(dirname(__DIR__));

$pluginDir = dirname(__DIR__);
$version = (string)$oPlugin->getMeta()->getVersion(); // Version aus Plugin, nie fest
$domain = UpdateBridge::domainFromUrl((string)Shop::getURL());
$shopVersion = defined('APPLICATION_VERSION') ? (string)APPLICATION_VERSION : '';

// Rechte Check, im Zweifel kein Zugriff
$canManage = static function (): bool {
    try {
        return (bool)Shop::Container()->getAdminAccount()->permission('PLUGIN_ADMIN_VIEW');
    } catch (\Throwable) {
        return false;
    }
};

$page = UpdateBridge::page(
    $pluginDir,
    UpdateBridge::dataDir((string)PFAD_ROOT, defined('PFAD_COMPILEDIR') ? (string)PFAD_COMPILEDIR : 'templates_c/'), // Datenordner ausserhalb Web
    $version,
    $domain,
    $shopVersion,
    static fn (): bool => Form::validateToken(), // CSRF Check
    $canManage,
    Form::getTokenInput()
);
$out = $page->handle((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), $_POST);

// geprueftes ZIP per POST ausliefern
if (is_string($out['deliver']) && is_file($out['deliver'])) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . rawurlencode(basename($out['deliver'])) . '"');
    header('Content-Length: ' . (string)filesize($out['deliver']));
    header('X-Content-Type-Options: nosniff');
    readfile($out['deliver']);
    exit;
}

mgdSeoHeader('Updates', 'Update-Prüfung mit Signatur. Es wird nichts automatisch installiert.');
echo mgdSeoCard('Update-Kanal', $out['html']);
echo '<div class="mgd-note"><strong>Sicherheitsprinzip:</strong> Das Plugin lädt Updates nur herunter und prüft sie. Eingespielt wird die ZIP-Datei bewusst von dir in der JTL-Pluginverwaltung.</div>';
mgdSeoFooter();
