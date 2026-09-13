<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use JTL\Shop;
use Plugin\MGD_SEOoverride_Plugin\Src\FrontendOptimizer;
use Plugin\MGD_SEOoverride_Plugin\Src\Monitor\NotFoundMonitor;

final class Bootstrap extends Bootstrapper
{
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        if (!Shop::isFrontend()) {
            return;
        }

        $config = $this->getPlugin()->getConfig();
        if ((string)$config->getValue('mgd_enabled') === 'Y') {
            require_once __DIR__ . '/src/FrontendOptimizer.php';

            $optimizer = new FrontendOptimizer([
                'lcp_preload'                  => (string)$config->getValue('mgd_lcp_preload') === 'Y',
                'lcp_image_url'                => (string)($config->getValue('mgd_lcp_image_url') ?? ''),
                'inline_small_css'             => (string)$config->getValue('mgd_inline_small_css') === 'Y',
                'inline_css_max_bytes'         => (int)($config->getValue('mgd_inline_css_max_bytes') ?? 8192),
                'critical_css'                 => (string)($config->getValue('mgd_critical_css') ?? ''),
                'async_css_patterns'           => (string)($config->getValue('mgd_async_css_patterns') ?? ''),
                'async_image_decode'           => (string)$config->getValue('mgd_async_image_decode') === 'Y',
                'lazy_images'                  => (string)$config->getValue('mgd_lazy_images') === 'Y',
                'lazy_skip_images'             => (int)($config->getValue('mgd_lazy_skip_images') ?? 4),
                'preconnect_origins'           => (string)($config->getValue('mgd_preconnect_origins') ?? ''),
                'defer_js_patterns'            => (string)($config->getValue('mgd_defer_js_patterns') ?? ''),
                'third_party_delay_mode'       => (string)($config->getValue('mgd_third_party_delay_mode') ?? 'off'),
                'third_party_delay_patterns'   => (string)($config->getValue('mgd_third_party_delay_patterns') ?? ''),
                'third_party_idle_delay_ms'    => (int)($config->getValue('mgd_third_party_idle_delay_ms') ?? 3500),
            ]);

            $dispatcher->listen('shop.hook.' . \HOOK_SMARTY_OUTPUTFILTER, [$optimizer, 'optimize'], 50);
        }

        if ((string)$config->getValue('mgd_404_monitor') === 'Y') {
            require_once __DIR__ . '/src/Monitor/NotFoundMonitor.php';
            $monitor = new NotFoundMonitor($this->getDB());
            $dispatcher->listen('shop.hook.' . \HOOK_SMARTY_OUTPUTFILTER, [$monitor, 'capture'], 95);
        }
    }

    public function updated($oldVersion, $newVersion): void
    {
        parent::updated($oldVersion, $newVersion);
        try {
            $this->getCache()->flushTags([CACHING_GROUP_PLUGIN, CACHING_GROUP_TEMPLATE, CACHING_GROUP_OBJECT, CACHING_GROUP_OPTION]);
        } catch (\Throwable) {
            // Cache-Bereinigung darf ein erfolgreiches Plugin-Update nicht zurückrollen.
        }
    }
}
