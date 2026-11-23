<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use Plugin\MGD_SEOoverride_Plugin\Kit\Platform\Jtl\Setup;
use Plugin\MGD_SEOoverride_Plugin\Src\FrontendOptimizer;

final class Bootstrap extends Bootstrapper
{
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        if (!Shop::isFrontend()) {
            // Installation melden: max 1x am Tag, nur im Admin
            if (Shop::isAdmin() && \PHP_SAPI !== 'cli') {
                $this->reportInstall(false);
            }

            return;
        }

        $config = $this->getPlugin()->getConfig();
        if ((string)$config->getValue('mgd_enabled') !== 'Y') {
            return;
        }

        require_once __DIR__ . '/src/FrontendOptimizer.php';

        $optimizer = new FrontendOptimizer([
            'lcp_preload'          => (string)$config->getValue('mgd_lcp_preload') === 'Y',
            'lcp_image_url'        => (string)($config->getValue('mgd_lcp_image_url') ?? ''),
            'inline_small_css'     => (string)$config->getValue('mgd_inline_small_css') === 'Y',
            'inline_css_max_bytes' => (int)($config->getValue('mgd_inline_css_max_bytes') ?? 4096),
            'async_image_decode'   => (string)$config->getValue('mgd_async_image_decode') === 'Y',
            'lazy_images'          => (string)$config->getValue('mgd_lazy_images') === 'Y',
            'lazy_skip_images'     => (int)($config->getValue('mgd_lazy_skip_images') ?? 4),
            'preconnect_origins'   => (string)($config->getValue('mgd_preconnect_origins') ?? ''),
            'defer_js_patterns'    => (string)($config->getValue('mgd_defer_js_patterns') ?? ''),
        ]);

        $dispatcher->listen('shop.hook.' . \HOOK_SMARTY_OUTPUTFILTER, [$optimizer, 'optimize'], 50);
    }

    public function installed()
    {
        parent::installed();
        $this->reportInstall(true);
    }

    public function enabled()
    {
        parent::enabled();
        $this->reportInstall(true);
    }

    public function updated($oldVersion, $newVersion)
    {
        parent::updated($oldVersion, $newVersion);
        $this->reportInstall(true);
    }

    // Abmelden beim Deinstallieren - Baukasten Kit
    public function uninstalled(bool $deleteData = true)
    {
        try {
            $this->kit()?->uninstall($deleteData);
        } catch (\Throwable) {
            // egal, Deinstallation laeuft weiter
        }
        parent::uninstalled($deleteData);
    }

    // Baukasten (Kit) - null wenn was fehlt, dann passiert einfach nix
    private function kit(): ?object
    {
        try {
            $this->takeOverOldData();

            return Setup::forShop($this->getPlugin());
        } catch (\Throwable) {
            return null;
        }
    }

    // alter Datenordner mgd-seo-update > neuer Kit Ordner (Bindestrich geht im Kit nicht), nur einmal
    private function takeOverOldData(): void
    {
        if (!\defined('PFAD_ROOT') || !\defined('PFAD_COMPILEDIR')) {
            return;
        }
        $base = \rtrim((string)\PFAD_ROOT . (string)\PFAD_COMPILEDIR, '/\\');
        $old = $base . '/mgd-seo-update';
        $new = $base . '/mgd_seooverride_plugin_update';
        if (!\is_dir($old) || \is_link($old) || \is_file($new . '/install.json')) {
            return;
        }
        if (!\is_dir($new) && !@\mkdir($new, 0700, true) && !\is_dir($new)) {
            return;
        }
        foreach (['install.json', 'state.json'] as $f) {
            $src = $old . '/' . $f;
            if (\is_file($src) && !\is_link($src) && \filesize($src) <= 65536 && !\is_file($new . '/' . $f)) {
                $data = (string)\file_get_contents($src);
                if (\is_array(\json_decode($data, true)) && \file_put_contents($new . '/' . $f, $data, LOCK_EX) !== false) {
                    @\chmod($new . '/' . $f, 0600);
                }
            }
        }
    }

    // Anmeldung bei theme.michael-gahn.de - darf NIE was kaputt machen
    private function reportInstall(bool $force): void
    {
        try {
            $this->kit()?->ping($force);
        } catch (\Throwable) {
            // egal, Plugin laeuft weiter
        }
    }
}
