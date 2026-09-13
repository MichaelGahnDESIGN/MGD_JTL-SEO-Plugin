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
}
