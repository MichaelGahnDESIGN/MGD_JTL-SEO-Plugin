<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update;

use Plugin\MGD_SEOoverride_Plugin\Update\Crypto\KeyRing;

// Einstellungen je Plugin. Alles Plugin-spezifische kommt von hier, der Client selbst ist für alle gleich
final class ClientConfig
{
    public const CLIENT_VERSION = '1.0.0';
    public const DEFAULT_BASE_URL = 'https://theme.michael-gahn.de';
    /** Hosts für Free-Downloads (GitHub-Release-Asset und dessen CDN) */
    public const DOWNLOAD_HOSTS = ['github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com'];

    /**
     * @param string $product Produktname laut Update-API (z. B. michatheme-v5)
     * @param 'free'|'pro' $edition
     * @param KeyRing $rootKeys gepinnte Root-Schlüssel (aus root-keys.json des Plugins)
     * @param list<string> $requiredFiles Pflichtdateien im Paket relativ zum Root-Ordner
     */
    public function __construct(
        public readonly string $product,
        public readonly string $edition,
        public readonly string $installedVersion,
        public readonly string $domain,
        public readonly KeyRing $rootKeys,
        public readonly string $dataDir,
        public readonly string $shopVersion = '',
        public readonly array $requiredFiles = ['info.xml'],
        public readonly string $baseUrl = self::DEFAULT_BASE_URL,
    ) {
        if (!in_array($edition, ['free', 'pro'], true) || !Manifest::isVersion($installedVersion)) {
            throw new \InvalidArgumentException('Edition oder installierte Version ungültig.');
        }
    }

    public function host(): string
    {
        return strtolower((string) parse_url($this->baseUrl, PHP_URL_HOST));
    }

    /** @return list<string> */
    public function allowedHosts(): array
    {
        return array_values(array_unique(array_merge([$this->host()], self::DOWNLOAD_HOSTS)));
    }

    public function userAgent(): string
    {
        return 'MichaThemeUpdateClient/' . self::CLIENT_VERSION . ' (jtl5)';
    }
}
