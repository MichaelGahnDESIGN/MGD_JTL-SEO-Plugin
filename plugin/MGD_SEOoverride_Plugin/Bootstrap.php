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
            if (Setup::isBackend()) {
                $this->reportInstall();
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
        $this->kit()?->ping(true);
    }

    public function enabled()
    {
        parent::enabled();
        $this->kit()?->ping(true);
    }

    public function updated($oldVersion, $newVersion)
    {
        parent::updated($oldVersion, $newVersion);
        // JTL kennt hier noch die alte Version > neue mitgeben
        $this->kit((string)$newVersion)?->ping(true);
    }

    // Tabs normal ueber adminmenu/*.php, das hier greift nur wenn die Datei fehlt
    public function renderAdminMenuTab(string $tabName, int $menuID, \JTL\Smarty\JTLSmarty $smarty): string
    {
        return Setup::renderTab(Setup::forShop($this->getPlugin(), ['legacyFolders' => ['mgd-seo-update']]), $tabName)
            ?? parent::renderAdminMenuTab($tabName, $menuID, $smarty);
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
    private function kit(?string $version = null): ?object
    {
        try {
            $o = ['legacyFolders' => ['mgd-seo-update']]; // alter Datenordner aus 1.x
            if ($version !== null) {
                $o['version'] = $version;
            }

            return Setup::forShop($this->getPlugin(), $o);
        } catch (\Throwable) {
            return null;
        }
    }

    // Anmeldung bei theme.michael-gahn.de - darf NIE was kaputt machen
    private function reportInstall(): void
    {
        try {
            $this->kit()?->ping();
        } catch (\Throwable) {
            // egal, Plugin laeuft weiter
        }
    }
}
