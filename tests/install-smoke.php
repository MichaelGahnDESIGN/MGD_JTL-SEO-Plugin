<?php declare(strict_types=1);
// Smoke Test Free-Lizenz Anmeldung ohne JTL + ohne Netz. Aufruf: php tests/install-smoke.php

$plugin = dirname(__DIR__) . '/plugin/MGD_SEOoverride_Plugin';
require $plugin . '/src/UpdateBridge.php';

use Plugin\MGD_SEOoverride_Plugin\Install\{InstallClient, JsonFileStore, Response, Settings, Transport};
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

// Fake-Plattform, merkt sich nur die Requests
final class FakeTransport implements Transport
{
    /** @var list<array{0:string,1:string,2:array<string,string>}> */
    public array $calls = [];

    public function send(string $method, string $url, array $headers): Response
    {
        $this->calls[] = [$method, $url, $headers];
        if ($method === 'DELETE') {
            return new Response(204);
        }

        return new Response(200, ['x-mgd-install-key' => 'testkey-0123456789abcdef-' . count($this->calls)], '{"version":"1.5.0"}');
    }
}

$tmp = sys_get_temp_dir() . '/mgd-seo-install-' . bin2hex(random_bytes(4));
mkdir($tmp . '/templates_c', 0700, true);
$data = UpdateBridge::dataDir($tmp);

// 1 Text + Bridge
check('Hinweistext vorhanden', strlen(Settings::HINT) > 40 && str_contains(Settings::HINT, 'theme.michael-gahn.de'));
check('Kein Entwurf-Hinweis mehr im Settings-Code', !str_contains((string)file_get_contents($plugin . '/src/Install/Settings.php'), 'ENTWURF'));
check('Bridge baut echten Client', UpdateBridge::installClient($data, '1.5.0') instanceof InstallClient);
check('Kein Client = kein Schalter', UpdateBridge::installReporting(null) === null);

// 2 Client mit Fake-Plattform
$fake = new FakeTransport();
$ic = new InstallClient($fake, new JsonFileStore($data . '/install.json'), UpdateBridge::PRODUCT, 'jtl5', '1.5.0', 'shop.example.test');
check('Schalter ab Werk an', $ic->isEnabled());
check('ping(true) meldet', $ic->ping(true) === true && count($fake->calls) === 1);
check('Meldung geht nur an theme.michael-gahn.de', str_starts_with($fake->calls[0][1], 'https://theme.michael-gahn.de/'));
check('zweiter ping am selben Tag schickt nix', $ic->ping() === false && count($fake->calls) === 1);

// 3 Update-Seite mit Schalter
$calls = ['csrf' => 0];
$page = UpdateBridge::page(
    $plugin,
    $data,
    '1.5.0',
    'shop.example.test',
    '5.8.0',
    static function () use (&$calls): bool { $calls['csrf']++; return true; },
    static fn (): bool => true,
    '<input type="hidden" name="jtl_token" value="TESTTOKEN">',
    UpdateBridge::installReporting($ic)
);
$out = $page->handle('GET', []);
check('Seite zeigt Schalter', str_contains($out['html'], 'Installation bei Michael Gahn DESIGN melden'));
check('Seite zeigt Hinweistext', str_contains($out['html'], htmlspecialchars(Settings::HINT, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
check('Schalter angehakt', (bool)preg_match('/name="install_report" value="1" checked/', $out['html']));

// 4 aus- und wieder anschalten
$page->handle('POST', ['jtl_token' => 'TESTTOKEN', 'mgd_update_action' => 'save_install']);
check('Ausschalten per Seite', !$ic->isEnabled());
check('Ausschalten loescht Eintrag (DELETE)', in_array('DELETE', array_column($fake->calls, 0), true));
$n = count($fake->calls);
check('Aus = keine Meldung mehr', $ic->ping(true) === false && count($fake->calls) === $n);
$page->handle('POST', ['jtl_token' => 'TESTTOKEN', 'mgd_update_action' => 'save_install', 'install_report' => '1']);
check('Wieder anschalten', $ic->isEnabled());
check('Nach Anschalten wieder Meldung', $ic->ping(true) === true);

// 5 Bootstrap darf nie crashen
check('Bootstrap Syntax mit ?->', str_contains((string)file_get_contents($plugin . '/Bootstrap.php'), 'installClient($dataDir, $version)?->ping($force)'));

echo $fails === 0 ? "\nALLES OK\n" : "\n$fails FEHLER\n";
exit($fails === 0 ? 0 : 1);
