<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update;

use Plugin\MGD_SEOoverride_Plugin\Update\Admin\UpdatesPage;
use Plugin\MGD_SEOoverride_Plugin\Update\Crypto\KeyRing;
use Plugin\MGD_SEOoverride_Plugin\Update\Http\CurlHttpClient;
use Plugin\MGD_SEOoverride_Plugin\Update\License\{LicenseProvider, NullLicenseProvider};
use Plugin\MGD_SEOoverride_Plugin\Update\Store\JsonFileStateStore;

// Baut Client und Admin-Seite aus den Plugin-Angaben. rootKeysFile = gepinnte root-keys.json im Plugin
final class Factory
{
    public static function page(
        string $rootKeysFile,
        string $dataDir,
        string $product,
        string $edition,
        string $pluginName,
        string $installedVersion,
        string $domain,
        string $shopVersion,
        callable $csrfValid,
        callable $canManage,
        string $csrfField,
        ?LicenseProvider $license = null,
        array $requiredFiles = ['info.xml'],
    ): UpdatesPage {
        $raw = is_file($rootKeysFile) ? file_get_contents($rootKeysFile) : false;
        $data = is_string($raw) ? json_decode($raw, true) : null;
        $roots = KeyRing::fromRootFile(is_array($data) ? $data : []);
        $cfg = new ClientConfig($product, $edition, $installedVersion, $domain, $roots, $dataDir, $shopVersion, $requiredFiles);
        $store = new JsonFileStateStore(rtrim($dataDir, '/') . '/state.json');
        $client = new UpdateClient($cfg, new CurlHttpClient($cfg->allowedHosts(), $cfg->userAgent()), $license ?? new NullLicenseProvider(), $store);

        return new UpdatesPage($client, $store, $csrfValid, $canManage, $csrfField, $pluginName, $installedVersion);
    }
}
