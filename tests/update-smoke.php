<?php declare(strict_types=1);
// Smoke Test Update-Bereich ohne JTL. Aufruf: php tests/update-smoke.php

$plugin = dirname(__DIR__) . '/plugin/MGD_SEOoverride_Plugin';
require $plugin . '/src/UpdateBridge.php';

use Plugin\MGD_SEOoverride_Plugin\UpdateBridge;

UpdateBridge::registerAutoload($plugin);

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'OK    ' : 'FEHLER') . ' ' . $name . "\n";
    if (!$ok) {
        $fails++;
    }
}

$tmp = sys_get_temp_dir() . '/mgd-seo-smoke-' . bin2hex(random_bytes(4));
mkdir($tmp . '/templates_c', 0700, true);
$data = UpdateBridge::dataDir($tmp);

// Seite mit Fake-Callbacks bauen, Zaehler fuer Rechte/CSRF
function build(string $data, string $plugin, bool $right, bool $csrf, array &$calls)
{
    return UpdateBridge::page(
        $plugin,
        $data,
        '1.1.0',
        'shop.example.test',
        '5.8.0',
        static function () use ($csrf, &$calls): bool { $calls['csrf']++; return $csrf; },
        static function () use ($right, &$calls): bool { $calls['right']++; return $right; },
        '<input type="hidden" name="jtl_token" value="TESTTOKEN">'
    );
}

// 1 ohne Recht
$c = ['csrf' => 0, 'right' => 0];
$r = build($data, $plugin, false, true, $c)->handle('POST', ['mgd_update_action' => 'check_now']);
check('ohne Recht: Fehlermeldung', str_contains($r['html'], 'Berechtigung'));
check('ohne Recht: kein Download', $r['deliver'] === null);
check('ohne Recht: kein State geschrieben', !is_file($data . '/state.json'));

// 2 ohne CSRF
$c = ['csrf' => 0, 'right' => 0];
$r = build($data, $plugin, true, false, $c)->handle('POST', ['mgd_update_action' => 'save_settings', 'checks_enabled' => '1']);
check('ohne CSRF: Meldung', str_contains($r['html'], 'Sicherheits-Token'));
check('ohne CSRF: Einstellung nicht gespeichert', !is_file($data . '/state.json'));
$r = build($data, $plugin, true, false, $c)->handle('POST', ['mgd_update_action' => 'deliver', 'confirm' => '1']);
check('ohne CSRF: deliver liefert nichts', $r['deliver'] === null);

// 3 GET ohne Einschalten: kein Netzabruf (Hilfs-Test ueber State: nach GET kein last_check)
$c = ['csrf' => 0, 'right' => 0];
$r = build($data, $plugin, true, true, $c)->handle('GET', []);
check('GET: Seite wird gezeigt', str_contains($r['html'], 'Update-Pr'));
check('GET: kein last_check (kein Abruf)', !is_file($data . '/state.json') || !isset(json_decode((string)file_get_contents($data . '/state.json'), true)['last_check']));
check('GET: CSRF nicht geprueft (keine Aktion)', $c['csrf'] === 0);
check('GET: CSRF-Feld in Formularen', str_contains($r['html'], 'TESTTOKEN'));
check('GET: nichts im Quarantaene-Ordner', !is_dir($data . '/quarantine') || count(glob($data . '/quarantine/*') ?: []) === 0);

// 4 Einschalten per POST mit CSRF: nur Einstellung, kein Abruf
$c = ['csrf' => 0, 'right' => 0];
$r = build($data, $plugin, true, true, $c)->handle('POST', ['mgd_update_action' => 'save_settings', 'checks_enabled' => '1']);
check('Einschalten: CSRF geprueft', $c['csrf'] === 1);
$st = json_decode((string)file_get_contents($data . '/state.json'), true);
check('Einschalten: enabled gespeichert', ($st['enabled'] ?? null) === true);

// 5 Anzeige escaped
$st['last_result'] = ['status' => 'update_available', 'message' => '<script>alert(1)</script>', 'version' => '9.9.9"><img src=x onerror=1>', 'changelog' => '<b>x</b>', 'security' => false];
$st['last_check'] = time();
file_put_contents($data . '/state.json', json_encode($st));
$r = build($data, $plugin, true, true, $c)->handle('GET', []);
check('Escape: kein rohes <script>', !str_contains($r['html'], '<script>'));
check('Escape: kein rohes <img', !str_contains($r['html'], '<img src=x'));
check('Escape: kein rohes <b>', !str_contains($r['html'], '<b>x</b>'));
check('Escape: Text escaped vorhanden', str_contains($r['html'], '&lt;script&gt;'));

// 6 Download ohne Bestaetigung: nichts passiert
$c = ['csrf' => 0, 'right' => 0];
$r = build($data, $plugin, true, true, $c)->handle('POST', ['mgd_update_action' => 'download']);
check('Download ohne Haken: Hinweis', str_contains($r['html'], 'Best'));
check('Download ohne Haken: kein deliver', $r['deliver'] === null);

// 7 Kanal nicht eingerichtet (B1): check_now liefert Meldung ohne Netz
// direkt ueber die Factory ohne Pruefsummen-Adresse, sonst ginge der Test ins Netz
$bare = \Plugin\MGD_SEOoverride_Plugin\Update\Factory::page($plugin . '/update/root-keys.json', $data, UpdateBridge::PRODUCT, UpdateBridge::EDITION, UpdateBridge::PLUGIN_NAME, '1.1.0', 'shop.example.test', '5.8.0', static fn (): bool => true, static fn (): bool => true, '');
$r = $bare->handle('POST', ['mgd_update_action' => 'check_now']);
check('B1: Kanal noch nicht eingerichtet', str_contains($r['html'], 'nicht eingerichtet'));
// einfacher Modus verdrahtet, nur statisch geprueft
check('einfacher Modus: Adresse der Pruefsummen-Datei', UpdateBridge::SIMPLE_URL === 'https://theme.michael-gahn.de/plugin-updates/mgd-jtl-seo.json');
check('einfacher Modus: Bridge gibt die Adresse weiter', str_contains((string)file_get_contents($plugin . '/src/UpdateBridge.php'), "['info.xml'],\n            self::SIMPLE_URL"));

// 8 Datenordner geschuetzt, Produkt, Domain
check('Datenordner unter templates_c', str_contains($data, '/templates_c/mgd-seo-update'));
check('.htaccess Deny vorhanden', is_file($data . '/.htaccess') && str_contains((string)file_get_contents($data . '/.htaccess'), 'denied'));
check('Produktname', UpdateBridge::PRODUCT === 'mgd-jtl-seo');
check('Domain nur Host', UpdateBridge::domainFromUrl('https://Shop.Example.de/pfad?a=b') === 'shop.example.de');
$keys = json_decode((string)file_get_contents($plugin . '/update/root-keys.json'), true);
check('root-keys.json ohne Schluessel (B1)', ($keys['keys'] ?? null) === []);
check('keine Secrets in update/', !preg_match('/PRIVATE|secret|token/i', (string)file_get_contents($plugin . '/update/root-keys.json')));

// aufraeumen
exec('rm -rf ' . escapeshellarg($tmp));
echo $fails === 0 ? "\nALLES OK\n" : "\n$fails FEHLER\n";
exit($fails === 0 ? 0 : 1);
