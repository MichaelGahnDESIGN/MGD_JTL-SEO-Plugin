<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Kit\Platform\Jtl;

use Plugin\MGD_SEOoverride_Plugin\Kit\Install\{CurlTransport, Factory, InstallClient, JsonFileStore, Transport};
use Plugin\MGD_SEOoverride_Plugin\Kit\Catalog\{MediaCache, Recommendations};
use Plugin\MGD_SEOoverride_Plugin\Kit\License\{CurlPost, HttpPost, LicenseClient};

// Alle Kit-Teile für ein JTL-Plugin an einer Stelle. Baut Setup::forShop(), Tests bauen direkt
// Zustand im Datenordner (templates_c/<plugin>_update): install.json, state.json (Update-Client), kit-*.json
final class JtlKit
{
    public const TAB_DASHBOARD = 'Übersicht';
    public const TAB_PLUGINS = 'Weitere Plugins';
    public const PRODUCT_RE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D';
    public const VERSION_RE = '/^[0-9]{1,5}\.[0-9]{1,5}\.[0-9]{1,5}$/D';

    private ?InstallClient $install = null;
    private bool $installBuilt = false;

    /**
     * @param \Closure(Guard,string,array):array{html:string,deliver:?string}|null $updates baut + ruft UpdatesPage::handle()
     * @param list<array<string,mixed>> $fallback mitgelieferte Liste "Weitere Plugins"
     */
    public function __construct(
        public readonly string $product,
        public readonly string $pluginName,
        public readonly string $version,
        private readonly string $dataDir,
        private readonly Guard $guard,
        private readonly string $domain,
        private readonly string $catalogSlug = '',
        private readonly string $repo = '',
        private readonly array $fallback = [],
        private readonly bool $licenseEnabled = false,
        private readonly ?\Closure $updates = null,
        private readonly ?Transport $http = null,
        private readonly ?HttpPost $post = null,
        private readonly ?MediaCache $media = null,
    ) {
        if (preg_match(self::PRODUCT_RE, $product) !== 1 || preg_match(self::VERSION_RE, $version) !== 1 || !is_dir($dataDir)) {
            throw new \InvalidArgumentException('Produkt, Version oder Datenordner ungültig.');
        }
    }

    public function guard(): Guard
    {
        return $this->guard;
    }

    /** null = Angaben ungültig, dann eben keine Meldung */
    public function installClient(): ?InstallClient
    {
        if (!$this->installBuilt) {
            $this->installBuilt = true;
            try {
                $store = new JsonFileStore($this->file('install.json'));
                // gleiche Datei wie Install\Platform\Jtl::client, Transport nur für Tests austauschbar
                $this->install = $this->http !== null
                    ? new InstallClient($this->http, $store, $this->product, 'jtl5', $this->version, $this->domain)
                    : Factory::create($store, $this->product, 'jtl5', $this->version, $this->domain);
            } catch (\Throwable) {
                $this->install = null;
            }
        }

        return $this->install;
    }

    /** Bootstrap: installed/enabled/updated > true, Backend > false (max 1x am Tag). Nie im Frontend */
    public function ping(bool $force = false): void
    {
        try {
            $this->installClient()?->ping($force);
        } catch (\Throwable) {
        }
    }

    /**
     * Bootstrap::uninstalled($deleteData): Eintrag auf der Plattform löschen (immer), Datenordner nur bei $deleteData leeren
     * Wirft nie. true = Install-Client war da
     */
    public function uninstall(bool $deleteData = true): bool
    {
        $done = false;
        try {
            $c = $this->installClient();
            if ($c !== null) {
                $was = $c->isEnabled();
                $c->setEnabled(false);
                // Daten bleiben > Schalter wie vorher, nur ohne alte Anmeldung
                if (!$deleteData && $was) {
                    $c->setEnabled(true);
                }
                $done = true;
            }
        } catch (\Throwable) {
        }
        if ($deleteData) {
            // Katalog-Bilder in mediafiles gleich mit weg
            $this->media?->wipe();
            try {
                self::wipe($this->dataDir);
            } catch (\Throwable) {
            }
        }

        return $done;
    }

    public function media(): ?MediaCache
    {
        return $this->media;
    }

    public function recommendations(): ?Recommendations
    {
        try {
            $http = $this->http ?? new CurlTransport('MGDKit/1.0 (jtl5)');
            $slug = $this->catalogSlug !== '' ? $this->catalogSlug : $this->product;

            return new Recommendations($http, new JsonFileStore($this->file('kit-recommendations.json')), 'jtl5', $slug, $this->fallback, null, $this->repo);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Standard aus: kein Netz, kein UI, isPro() = false */
    public function license(): LicenseClient
    {
        $on = $this->licenseEnabled;

        return new LicenseClient($on ? ($this->post ?? new CurlPost('MGDKit/1.0 (jtl5)')) : null, new JsonFileStore($this->file('kit-license.json')), $on ? $this->domain : '', $on);
    }

    public function noticeReader(): NoticeReader
    {
        return new NoticeReader($this->installClient(), new JsonFileStore($this->file('kit-notice.json')), $this->product, new JsonFileStore($this->file('state.json')));
    }

    public function dashboard(): Dashboard
    {
        $u = $this->updates;
        $g = $this->guard;
        $updates = $u === null ? null : static fn (string $m, array $p): array => $u($g, $m, $p);

        return new Dashboard($this->pluginName, $this->version, $this->product, $this->guard, $updates, $this->noticeReader(),
            $this->recommendations(), $this->license(), $this->installClient(), $this->media, ColorPicker::file($this->dataDir));
    }

    /**
     * Tab "Übersicht"
     * @param array<string,mixed> $post
     * @return array{html:string,deliver:?string}
     */
    public function page(string $method, array $post, string $today): array
    {
        try {
            return $this->dashboard()->handle($method, $post, $today);
        } catch (\Throwable) {
            return ['html' => '<p>Die Seite konnte gerade nicht geladen werden.</p>', 'deliver' => null];
        }
    }

    /** Tab "Weitere Plugins" */
    public function recommendationsPage(): string
    {
        if (!$this->guard->canManage()) {
            return '<p class="alert alert-danger">Dafür fehlt die Berechtigung.</p>';
        }

        return Dashboard::style() . '<div class="mgd-kit-jtl"><h1>Weitere Plugins von Michael Gahn DESIGN</h1>'
            . RecommendationsView::render($this->recommendations(), $this->guard, $this->media) . '</div>';
    }

    // Datenordner leeren + weg. Symlinks nur entfernen, nie folgen
    private static function wipe(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $path = $f->getPathname();
            is_dir($path) && !is_link($path) ? @rmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function file(string $name): string
    {
        return rtrim($this->dataDir, '/') . '/' . $name;
    }
}
