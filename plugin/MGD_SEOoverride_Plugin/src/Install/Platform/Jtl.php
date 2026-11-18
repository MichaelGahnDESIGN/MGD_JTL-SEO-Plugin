<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Install\Platform;

use Plugin\MGD_SEOoverride_Plugin\Install\{Factory, InstallClient, JsonFileStore};

// JTL-Anbindung. Bootstrap: installed()/enabled()/updated() -> client()->ping(true), Backend-Hook -> client()->ping()
// Nie in Frontend-Hooks aufrufen!
final class Jtl
{
    /** null = Angaben ungültig, dann keine Meldung (nie Exception im Bootstrap) */
    public static function client(string $dataDir, string $product, string $version, ?string $domain = null): ?InstallClient
    {
        try {
            return Factory::create(new JsonFileStore(rtrim($dataDir, '/') . '/install.json'), $product, 'jtl5', $version, $domain ?? self::shopDomain());
        } catch (\Throwable) {
            return null;
        }
    }

    public static function shopDomain(): string
    {
        $url = defined('URL_SHOP') ? (string) constant('URL_SHOP') : '';

        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }
}
