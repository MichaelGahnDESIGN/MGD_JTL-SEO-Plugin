<?php declare(strict_types=1);
// Smoke Test Baukasten (Kit) ohne JTL + ohne Netz. Aufruf: php tests/kit-smoke.php

$plugin = dirname(__DIR__) . '/plugin/MGD_SEOoverride_Plugin';

// Autoload wie JTL: Plugin\MGD_SEOoverride_Plugin\ > plugin Ordner
spl_autoload_register(static function (string $c) use ($plugin): void {
    $pre = 'Plugin\\MGD_SEOoverride_Plugin\\';
    if (strncmp($c, $pre, strlen($pre)) === 0) {
        $f = $plugin . '/' . str_replace('\\', '/', substr($c, strlen($pre))) . '.php';
        if (is_file($f)) {
            require $f;
        }
    }
});

use Plugin\MGD_SEOoverride_Plugin\Kit\Platform\Jtl\Config;
use Plugin\MGD_SEOoverride_Plugin\Kit\Platform\Jtl\Setup;

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'OK    ' : 'FEHLER') . ' ' . $name . "\n";
    if (!$ok) {
        $fails++;
    }
}

$xml = (string)file_get_contents($plugin . '/info.xml');
$boot = (string)file_get_contents($plugin . '/Bootstrap.php');

check('Config Produktname bleibt mgd-jtl-seo', Config::PRODUCT === 'mgd-jtl-seo');
check('Config Plugin-ID stimmt', Config::PLUGIN_ID === 'MGD_SEOoverride_Plugin');
check('Setup Klasse ladbar', class_exists(Setup::class));
check('Ohne JTL kein Kit (null, kein Crash)', Setup::forShop(new stdClass()) === null);
check('Tab Uebersicht in info.xml', str_contains($xml, '<Name>Übersicht</Name><Filename>uebersicht.php</Filename>'));
check('Tab Weitere Plugins in info.xml', str_contains($xml, '<Name>Weitere Plugins</Name><Filename>weitere-plugins.php</Filename>'));
check('Alter Updates Tab nicht mehr angemeldet', !str_contains($xml, 'updates.php'));
check('Tab Dateien da', is_file($plugin . '/adminmenu/uebersicht.php') && is_file($plugin . '/adminmenu/weitere-plugins.php'));
check('Bootstrap meldet per Kit', str_contains($boot, '->ping(true)') && str_contains($boot, 'Setup::isBackend()') && str_contains($boot, 'mgd-seo-update'));
check('Bootstrap meldet beim Deinstallieren ab', str_contains($boot, '?->uninstall($deleteData)') && str_contains($boot, 'function uninstalled('));
check('Bootstrap nutzt alten Update Code nicht mehr', !str_contains($boot, 'UpdateBridge'));
check('root-keys.json da', is_file($plugin . '/update/root-keys.json'));

echo $fails === 0 ? "\nALLES OK\n" : "\n$fails FEHLER\n";
exit($fails === 0 ? 0 : 1);
