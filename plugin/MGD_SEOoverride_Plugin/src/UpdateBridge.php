<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin;

use Plugin\MGD_SEOoverride_Plugin\Install\InstallClient;
use Plugin\MGD_SEOoverride_Plugin\Install\Platform\Jtl;
use Plugin\MGD_SEOoverride_Plugin\Install\Settings;
use Plugin\MGD_SEOoverride_Plugin\Update\Admin\InstallReporting;
use Plugin\MGD_SEOoverride_Plugin\Update\Admin\UpdatesPage;
use Plugin\MGD_SEOoverride_Plugin\Update\Factory;

// Verbindung JTL <> MGD Update Client. Alles JTL-spezifische kommt per Callback rein (dadurch ohne JTL testbar)
final class UpdateBridge
{
    // Produktname Update-API (Free)
    public const PRODUCT = 'mgd-jtl-seo';
    public const EDITION = 'free';
    public const PLUGIN_NAME = 'MGD JTL SEO & PageSpeed';
    // Prüfsummen-Datei auf All-Inkl, einfacher Modus ohne Schlüssel
    public const SIMPLE_URL = 'https://theme.michael-gahn.de/plugin-updates/mgd-jtl-seo.json';
    // Namensraum > Unterordner, beides generiert (Update + Install Client)
    private const NAMESPACES = [
        'Plugin\\MGD_SEOoverride_Plugin\\Update\\'  => 'src/Update/',
        'Plugin\\MGD_SEOoverride_Plugin\\Install\\' => 'src/Install/',
    ];

    // Autoload für src/Update + src/Install (Klassen sind generiert, kein JTL-Autoloader noetig)
    public static function registerAutoload(string $pluginDir): void
    {
        static $done = [];
        $root = rtrim($pluginDir, '/\\') . '/';
        if (isset($done[$root])) {
            return;
        }
        $done[$root] = true;
        spl_autoload_register(static function (string $class) use ($root): void {
            foreach (self::NAMESPACES as $prefix => $dir) {
                if (!str_starts_with($class, $prefix)) {
                    continue;
                }
                $rel = substr($class, strlen($prefix));
                if (!preg_match('/^[A-Za-z0-9\\\\]+$/D', $rel)) {
                    return;
                }
                $file = $root . $dir . str_replace('\\', '/', $rel) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }

                return;
            }
        });
    }

    // Free-Lizenz Anmeldung, null = ungueltig > dann einfach nix melden
    public static function installClient(string $dataDir, string $version): ?InstallClient
    {
        try {
            return Jtl::client($dataDir, self::PRODUCT, $version);
        } catch (\Throwable) {
            return null;
        }
    }

    // Schalter fuer die Update-Seite
    public static function installReporting(?InstallClient $ic): ?InstallReporting
    {
        if ($ic === null) {
            return null;
        }

        return new InstallReporting(
            static fn () => $ic->ping(),
            static fn (): bool => $ic->isEnabled(),
            static fn (bool $an) => $ic->setEnabled($an),
            static fn (): string => $ic->status(),
            Settings::HINT,
        );
    }

    // Datenordner ausserhalb Web: templates_c/ im Shop, per .htaccess zusaetzlich gesperrt
    public static function dataDir(string $shopRoot, string $compileDir = 'templates_c/'): string
    {
        $dir = rtrim($shopRoot, '/\\') . '/' . trim($compileDir, '/\\') . '/mgd-seo-update';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (is_dir($dir)) {
            $deny = $dir . '/.htaccess';
            if (!is_file($deny)) {
                @file_put_contents($deny, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
            }
            $index = $dir . '/index.html';
            if (!is_file($index)) {
                @file_put_contents($index, '');
            }
        }

        return $dir;
    }

    // Domain aus Shop-URL (nur Host)
    public static function domainFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }

    public static function page(
        string $pluginDir,
        string $dataDir,
        string $installedVersion,
        string $domain,
        string $shopVersion,
        callable $csrfValid,
        callable $canManage,
        string $csrfField,
        ?InstallReporting $install = null,
    ): UpdatesPage {
        // Free: kein LicenseProvider, Factory nimmt NullLicenseProvider
        return Factory::page(
            rtrim($pluginDir, '/\\') . '/update/root-keys.json',
            $dataDir,
            self::PRODUCT,
            self::EDITION,
            self::PLUGIN_NAME,
            $installedVersion,
            $domain,
            $shopVersion,
            $csrfValid,
            $canManage,
            $csrfField,
            null,
            ['info.xml'],
            self::SIMPLE_URL,
            $install,
        );
    }
}
