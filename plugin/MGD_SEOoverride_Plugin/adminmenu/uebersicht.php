<?php

declare(strict_types=1);

use JTL\Plugin\PluginInterface;
use Plugin\MGD_SEOoverride_Plugin\Kit\Platform\Jtl\Setup;

if (!defined('PFAD_ROOT') || !isset($oPlugin) || !$oPlugin instanceof PluginInterface) {
    http_response_code(403);
    return;
}
// Kit Tab Übersicht
echo Setup::renderTab(Setup::forShop($oPlugin), 'Übersicht');
