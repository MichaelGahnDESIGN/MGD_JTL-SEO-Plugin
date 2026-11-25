<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Kit\Platform\Jtl;

use Plugin\MGD_SEOoverride_Plugin\Kit\Catalog\MediaCache;
use Plugin\MGD_SEOoverride_Plugin\Kit\Update\Factory as UpdateFactory;

// Einstieg für JTL-Plugins (docs/plugin-baukasten/jtl.md). Ein Aufruf in Bootstrap oder adminmenu/*.php
// Fehler > null, Shop + Backend laufen immer weiter
final class Setup
{
    public const PLUGIN_ID_RE = '/^[A-Za-z][A-Za-z0-9_]{2,60}$/D';
    public const FOLDER_RE = '/^[a-z0-9_]{3,80}$/D';
    public const SIMPLE_PREFIX = 'https://theme.michael-gahn.de/plugin-updates/';

    /**
     * Kit aus dem JTL-Plugin-Objekt. Werte kommen aus Config (Generator), $o überschreibt einzeln:
     * product, name, catalogSlug, repo, pluginId, dataFolder, fallback (list), licenseEnabled (bool)
     * @param array<string,mixed> $o
     */
    public static function forShop(object $plugin, array $o = []): ?JtlKit
    {
        try {
            $pluginId = (string) ($o['pluginId'] ?? Config::PLUGIN_ID);
            $folder = (string) ($o['dataFolder'] ?? strtolower($pluginId) . '_update');
            $base = self::basePath($plugin);
            $compiled = defined('PFAD_ROOT') && defined('PFAD_COMPILEDIR') ? constant('PFAD_ROOT') . constant('PFAD_COMPILEDIR') : '';
            $dir = $compiled !== '' ? self::dataDir($compiled, $folder) : null;
            if ($dir !== null && preg_match(self::PLUGIN_ID_RE, $pluginId) === 1) {
                // alte Ordner unter templates_c: Standard mgd-update-client/<PluginID> + eigene (legacyFolder / legacyFolders, z.B. mgd-seo-update)
                foreach (self::legacyCandidates($pluginId, $o) as $rel) {
                    self::adoptLegacy($compiled, $pluginId, $dir, $rel);
                }
            }
            // updated($old, $new): JTL hat noch die alte Version drin > neue mitgeben (Befund slider)
            $given = is_string($o['version'] ?? null) && preg_match('/^\d{1,5}\.\d{1,5}\.\d{1,5}$/D', $o['version']) === 1 ? $o['version'] : '';
            $version = $given !== '' ? $given : self::version($plugin, $base);
            if (preg_match(self::PLUGIN_ID_RE, $pluginId) !== 1 || $dir === null || $version === '') {
                return null;
            }
            $product = (string) ($o['product'] ?? Config::PRODUCT);
            $name = (string) ($o['name'] ?? Config::NAME);

            return new JtlKit($product, $name, $version, $dir, Guard::forShop(), self::domain(),
                (string) ($o['catalogSlug'] ?? Config::CATALOG_SLUG), (string) ($o['repo'] ?? Config::REPO),
                is_array($o['fallback'] ?? null) ? $o['fallback'] : [], ($o['licenseEnabled'] ?? false) === true,
                self::updates($base . '/update/root-keys.json', $dir, $product, $name, $version), media: self::media($product));
        } catch (\Throwable) {
            return null;
        }
    }

    /** Tab ausgeben (adminmenu/*.php oder Bootstrap::renderAdminMenuTab). null = nicht unser Tab */
    public static function renderTab(?JtlKit $kit, string $tabName): ?string
    {
        if ($tabName !== JtlKit::TAB_DASHBOARD && $tabName !== JtlKit::TAB_PLUGINS) {
            return null;
        }
        if ($kit === null) {
            return '<p>Dieser Bereich kann gerade nicht angezeigt werden. Der Shop läuft normal weiter.</p>';
        }
        if ($tabName === JtlKit::TAB_PLUGINS) {
            return $kit->recommendationsPage();
        }
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $out = $kit->page($method, $method === 'POST' ? $_POST : [], gmdate('Y-m-d'));
        if ($out['deliver'] !== null) {
            self::sendZip($out['deliver']);
        }

        return $out['html'];
    }

    // Befund Pilot ai-kennzeichnung: alter Update-Client lag in templates_c/mgd-update-client/<PluginID>
    // > install.json + state.json einmal rüberkopieren (nur wenn im neuen Ordner noch nix ist)
    // danach alt > *.migrated, sonst holt ein Neuinstallieren nach "Daten löschen" die abgemeldete ID zurück (Befund Pilot)
    public const LEGACY_FILES = ['install.json', 'state.json'];

    /** @return list<string> */
    public static function legacyCandidates(string $pluginId, array $o): array
    {
        $out = ['mgd-update-client/' . $pluginId];
        $more = $o['legacyFolders'] ?? [];
        foreach (array_merge(is_array($more) ? $more : [], is_string($o['legacyFolder'] ?? null) ? [$o['legacyFolder']] : []) as $f) {
            if (is_string($f) && preg_match(self::LEGACY_FOLDER_RE, $f) === 1 && !in_array($f, $out, true)) {
                $out[] = $f;
            }
            if (count($out) >= 6) {
                break;
            }
        }

        return $out;
    }

    // nur echter Admin-Bereich (nicht Shop-Besucher, nicht Wawi-Abgleich dbeS, nicht CLI) > da darf gemeldet werden
    public static function isBackend(?string $script = null, ?string $adminDir = null): bool
    {
        if (PHP_SAPI === 'cli' && $script === null) {
            return false;
        }
        $script = str_replace('\\', '/', (string) ($script ?? ($_SERVER['SCRIPT_NAME'] ?? '')));
        $admin = trim((string) ($adminDir ?? (defined('PFAD_ADMIN') ? constant('PFAD_ADMIN') : 'admin/')), '/');
        if ($admin === '' || str_contains($script, '/dbeS/')) {
            return false;
        }

        return str_contains($script, '/' . $admin . '/');
    }

    public const LEGACY_FOLDER_RE = '#^[A-Za-z0-9_-]{2,80}(?:/[A-Za-z0-9_-]{2,80})?$#D';

    /** @return list<string> übernommene Dateien */
    public static function adoptLegacy(string $compiledBase, string $pluginId, string $newDir, ?string $legacyFolder = null): array
    {
        $done = [];
        try {
            if (preg_match(self::PLUGIN_ID_RE, $pluginId) !== 1) {
                return [];
            }
            // Standard alter Update-Client, sonst eigener alter Ordnername (Bindestrich ok, max 2 Ebenen, kein ..)
            $rel = $legacyFolder ?? 'mgd-update-client/' . $pluginId;
            if (preg_match(self::LEGACY_FOLDER_RE, $rel) !== 1) {
                return [];
            }
            $old = rtrim($compiledBase, '/\\') . '/' . $rel;
            if (!is_dir($old) || is_link($old) || realpath($old) === realpath($newDir)) {
                return [];
            }
            foreach (self::LEGACY_FILES as $f) {
                $src = $old . '/' . $f;
                $dst = rtrim($newDir, '/\\') . '/' . $f;
                if (!is_file($src) || is_link($src)) {
                    continue;
                }
                if (!is_file($dst) && filesize($src) <= 65536) {
                    $data = (string) file_get_contents($src);
                    if (is_array(json_decode($data, true)) && file_put_contents($dst, $data, LOCK_EX) !== false) {
                        @chmod($dst, 0600);
                        $done[] = $f;
                    }
                }
                // alt stilllegen, sobald es im neuen Ordner eine Datei gibt (kopiert oder schon da)
                if (is_file($dst)) {
                    @rename($src, $src . '.migrated');
                }
            }
        } catch (\Throwable) {
        }

        return $done;
    }

    /** Unterordner in templates_c + Zugriffssperre, wie bei den bisherigen MGD-JTL-Plugins. null = geht nicht */
    public static function dataDir(string $base, string $folder): ?string
    {
        $base = rtrim($base, '/\\');
        if (preg_match(self::FOLDER_RE, $folder) !== 1 || $base === '' || !is_dir($base) || !is_writable($base)) {
            return null;
        }
        $dir = $base . '/' . $folder;
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return null;
        }
        // Deny falls der Ordner doch im Web liegt (Apache 2.2 + 2.4)
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
        }
        if (!is_file($dir . '/index.html')) {
            @file_put_contents($dir . '/index.html', '');
        }

        return is_writable($dir) ? $dir : null;
    }

    // Version aus dem Plugin-Objekt, sonst info.xml, nie fest im Code
    public static function version(object $plugin, string $base): string
    {
        $v = '';
        if (method_exists($plugin, 'getMeta')) {
            $meta = $plugin->getMeta();
            $v = is_object($meta) && method_exists($meta, 'getVersion') ? (string) $meta->getVersion() : '';
        }
        if ($v === '' && $base !== '' && is_file($base . '/info.xml')) {
            $xml = @simplexml_load_file($base . '/info.xml');
            $v = $xml !== false ? (string) $xml->Version : '';
        }

        return preg_match(JtlKit::VERSION_RE, $v) === 1 ? $v : '';
    }

    // geprüfte ZIP als Download, kommt nur nach POST + CSRF + Bestätigung aus der UpdatesPage
    public static function sendZip(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file)) . '"');
        header('Content-Length: ' . (string) filesize($file));
        header('X-Content-Type-Options: nosniff');
        readfile($file);
        exit;
    }

    /** vorhandener Update-Client (signiert, sonst einfacher Modus), Install-Schalter steht im eigenen Bereich */
    private static function updates(string $rootKeys, string $dir, string $product, string $name, string $version): \Closure
    {
        return static function (Guard $g, string $method, array $post) use ($rootKeys, $dir, $product, $name, $version): array {
            $page = UpdateFactory::page($rootKeys, $dir, $product, 'free', $name, $version, self::domain(),
                defined('APPLICATION_VERSION') ? (string) constant('APPLICATION_VERSION') : '',
                static fn (): bool => $g->postValid($method, $post), static fn (): bool => $g->canManage(), $g->tokenInput(),
                null, ['info.xml'], self::SIMPLE_PREFIX . $product . '.json', null);

            return $page->handle($method, $post);
        };
    }

    public const MEDIA_FOLDER = 'mgd-kit-catalog';

    /** Katalog-Bilder > <shop>/mediafiles/mgd-kit-catalog/<produkt>, per URL erreichbar. null = kein Ordner (dann Farbfläche) */
    public static function media(string $product, ?string $root = null, ?string $shopUrl = null): ?MediaCache
    {
        try {
            $root ??= defined('PFAD_ROOT') ? (string) constant('PFAD_ROOT') : '';
            $mf = defined('PFAD_MEDIAFILES') ? (string) constant('PFAD_MEDIAFILES') : 'mediafiles/';
            $base = rtrim($root, '/\\') . '/' . trim($mf, '/');
            if ($root === '' || preg_match(JtlKit::PRODUCT_RE, $product) !== 1 || preg_match('#^[a-z0-9_-]+$#D', trim($mf, '/')) !== 1 || !is_dir($base) || !is_writable($base)) {
                return null;
            }
            // nur Pfad, kein Host > Bild kommt immer vom gleichen Server wie das Backend
            $path = rtrim((string) parse_url($shopUrl ?? (class_exists('\\JTL\\Shop') ? (string) \JTL\Shop::getURL() : (defined('URL_SHOP') ? (string) constant('URL_SHOP') : '')), PHP_URL_PATH), '/');
            if (preg_match('#^(/[A-Za-z0-9._-]+)*$#D', $path) !== 1 || str_contains($path, '..')) {
                return null;
            }
            $rel = trim($mf, '/') . '/' . self::MEDIA_FOLDER . '/' . $product;

            return new MediaCache($base . '/' . self::MEDIA_FOLDER . '/' . $product, MediaCache::curl('MGDKit/1.0 (jtl5)'), $path . '/' . $rel);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function basePath(object $plugin): string
    {
        $p = method_exists($plugin, 'getPaths') ? $plugin->getPaths() : null;

        return is_object($p) && method_exists($p, 'getBasePath') ? rtrim((string) $p->getBasePath(), '/') : '';
    }

    private static function domain(): string
    {
        $url = class_exists('\JTL\Shop') ? (string) \JTL\Shop::getURL() : (defined('URL_SHOP') ? (string) constant('URL_SHOP') : '');

        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }
}
