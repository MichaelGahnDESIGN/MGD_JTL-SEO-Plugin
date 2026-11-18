<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use Plugin\MGD_SEOoverride_Plugin\Src\FrontendOptimizer;

final class Bootstrap extends Bootstrapper
{
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        if (!Shop::isFrontend()) {
            // Free-Lizenz: max 1x am Tag, nur im Admin
            if (Shop::isAdmin()) {
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
        $this->reportInstall(true, (string)$newVersion);
    }

    // Anmeldung bei theme.michael-gahn.de - darf NIE was kaputt machen
    private function reportInstall(bool $force, ?string $version = null): void
    {
        try {
            require_once __DIR__ . '/src/UpdateBridge.php';
            UpdateBridge::registerAutoload(__DIR__);
            $version ??= (string)$this->getPlugin()->getMeta()->getVersion();
            $dataDir = UpdateBridge::dataDir(
                (string)\PFAD_ROOT,
                \defined('PFAD_COMPILEDIR') ? (string)\PFAD_COMPILEDIR : 'templates_c/'
            );
            UpdateBridge::installClient($dataDir, $version)?->ping($force);
        } catch (\Throwable) {
            // egal, Plugin laeuft weiter
        }
    }
}
