<?php declare(strict_types=1);
require __DIR__ . '/_common.php';
require_once dirname(__DIR__) . '/src/Update/GitHubReleaseChecker.php';

use Plugin\MGD_SEOoverride_Plugin\Src\Update\GitHubReleaseChecker;

$current = (string)$oPlugin->getMeta()->getVersion();
$enabled = mgdSeoYes($oPlugin->getConfig()->getValue('mgd_update_notices'));
$result = (new GitHubReleaseChecker())->check($current, $enabled);

mgdSeoHeader('Updates', 'Sichere Versionsprüfung über die öffentlichen GitHub-Releases des Projekts.');
if (!$enabled) {
    echo mgdSeoCard('Updateprüfung deaktiviert', '<p>Aktiviere „GitHub-Updatehinweise“ in den Plugin-Einstellungen, wenn der Shop neue stabile Releases prüfen darf.</p>', 'warn');
} elseif (($result['ok'] ?? false) !== true) {
    echo mgdSeoCard('GitHub momentan nicht erreichbar', '<p>Der Shop läuft unverändert weiter. Die Prüfung wird später erneut versucht.</p><p class="mgd-small">' . mgdSeoEsc($result['error'] ?? '') . '</p>', 'warn');
} elseif (($result['update'] ?? false) === true) {
    $button = '<p><strong>Installiert:</strong> ' . mgdSeoEsc($current) . '<br><strong>Verfügbar:</strong> ' . mgdSeoEsc($result['latest']) . '</p>';
    if (!empty($result['download_url'])) { $button .= '<a class="mgd-btn" target="_blank" rel="noopener noreferrer" href="' . mgdSeoEsc($result['download_url']) . '">Release-ZIP herunterladen</a> '; }
    $button .= '<a class="mgd-btn secondary" target="_blank" rel="noopener noreferrer" href="' . mgdSeoEsc($result['release_url']) . '">Release ansehen</a>';
    echo mgdSeoCard('Update verfügbar', $button, 'ok');
} else {
    echo mgdSeoCard('Aktuell', '<p>Version <strong>' . mgdSeoEsc($current) . '</strong> ist auf dem neuesten stabilen Stand.</p>', 'ok');
}
echo '<div class="mgd-note"><strong>Sicherheitsprinzip:</strong> Version 1.1.0 installiert Updates nicht selbst. Eine neue ZIP wird bewusst über den JTL-Plugin-Manager eingespielt. Ein späterer Ein-Klick-Updater soll erst nach Prüfsumme, Backup und Rollback freigegeben werden.</div>';
mgdSeoFooter();
