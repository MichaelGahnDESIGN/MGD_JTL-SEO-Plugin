<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Kit\Platform\Jtl;

use Plugin\MGD_SEOoverride_Plugin\Kit\Install\{InstallClient, Settings};
use Plugin\MGD_SEOoverride_Plugin\Kit\Catalog\{MediaCache, Recommendations};
use Plugin\MGD_SEOoverride_Plugin\Kit\License\LicenseClient;
use Plugin\MGD_SEOoverride_Plugin\Kit\Support\Text;

// Tab "Übersicht": Info, Updates (vorhandene UpdatesPage), Weitere Plugins, Installation melden, Lizenz nur wenn an
// CSS lokal aus assets/kit-jtl.css, hell wie das JTL-Backend
final class Dashboard
{
    public const CSS = __DIR__ . '/assets/kit-jtl.css';
    public const FAIL = 'Das hat nicht geklappt. Bitte lade die Seite neu und versuche es noch einmal.';

    /**
     * @param \Closure(string,array):array{html:string,deliver:?string}|null $updates UpdatesPage::handle()
     */
    public function __construct(
        private readonly string $pluginName,
        private readonly string $version,
        private readonly string $product,
        private readonly Guard $guard,
        private readonly ?\Closure $updates = null,
        private readonly ?NoticeReader $notice = null,
        private readonly ?Recommendations $recommendations = null,
        private readonly ?LicenseClient $license = null,
        private readonly ?InstallClient $install = null,
        private readonly ?MediaCache $media = null,
        // Farbwähler: gemeinsame Datei für Meine Farben, '' = kein Farbwähler
        private readonly string $colorFile = '',
    ) {
    }

    /**
     * @param array<string,mixed> $post
     * @return array{html:string,deliver:?string} deliver = geprüfte ZIP zum Ausliefern (kommt von UpdatesPage)
     */
    public function handle(string $method, array $post, string $today): array
    {
        if (!$this->guard->canManage()) {
            return ['html' => '<p class="alert alert-danger">Dafür fehlt die Berechtigung.</p>', 'deliver' => null];
        }
        // Farbwähler speichert per fetch, Antwort ist egal
        if ($this->colorFile !== '' && ColorPicker::handle($method, $post, $this->guard, $this->colorFile)) {
            return ['html' => '', 'deliver' => null];
        }
        $msg = $this->installAction($method, $post) ?? $this->licenseAction($method, $post) ?? '';
        $upd = $this->updatesHtml($method, $post);
        if ($upd['deliver'] !== null) {
            return ['html' => '', 'deliver' => $upd['deliver']];
        }
        $html = $this->html($today, $upd['html'], $msg);
        // ping erst NACH der Update-Prüfung: die meldet schon (setzt last_ping) > sonst 2 Anfragen (Befund Codex-Review Suche)
        $this->ping();

        return ['html' => $html, 'deliver' => null];
    }

    public function html(string $today, string $updatesHtml = '', string $message = ''): string
    {
        if (!$this->guard->canManage()) {
            return '';
        }
        $out = self::style() . '<div class="mgd-kit-jtl"><h1>' . Text::e($this->pluginName) . '</h1>';
        if ($message !== '') {
            $out .= '<div class="alert alert-info mgd-kit-message" role="status"><p>' . Text::e($message) . '</p></div>';
        }
        $out .= self::section('info', 'Info', '<p>Plugin: ' . Text::e($this->pluginName) . '</p><p>Version: ' . Text::e($this->version) . '</p>');
        $notice = $this->notice !== null ? NoticeView::render($this->notice, $this->guard, $this->version, $today, $this->pluginName) : '';
        $out .= self::section('updates', 'Updates', $notice . ($updatesHtml !== '' ? $updatesHtml : '<p>Die Update-Prüfung ist für dieses Plugin nicht eingerichtet.</p>'));
        $rec = RecommendationsView::render($this->recommendations, $this->guard, $this->media);
        if ($rec !== '') {
            $out .= self::section('plugins', 'Weitere Plugins', $rec);
        }
        if ($this->install !== null) {
            $out .= self::section('install', 'Installation melden', Settings::render($this->install, $this->guard->tokenInput()));
        }
        if ($this->license !== null && $this->license->enabled()) {
            $out .= self::section('license', 'Lizenz', LicenseView::html($this->license, $this->product, $this->guard));
        }
        // Farbwähler gilt für die ganze Plugin-Seite (auch Tab Einstellungen)
        $color = $this->colorFile !== '' ? ColorPicker::html($this->colorFile) : '';

        return $out . '</div>' . $color;
    }

    // Schalter "Installation melden", CSRF + Recht vorher (Settings::handle prüft das nicht selbst)
    private function installAction(string $method, array $post): ?string
    {
        if ($this->install === null || ($post[Settings::actionName($this->install)] ?? '') !== '1') {
            return null;
        }
        if (!$this->guard->postValid($method, $post)) {
            return self::FAIL;
        }

        return Settings::handle($this->install, $post) ? 'Einstellung gespeichert.' : null;
    }

    private function licenseAction(string $method, array $post): ?string
    {
        return $this->license !== null ? LicenseView::handle($this->license, $this->product, $method, $post, $this->guard) : null;
    }

    // Aktiv-Meldung max 1x am Tag, macht der Install-Client slebst
    private function ping(): void
    {
        try {
            $this->install?->ping();
        } catch (\Throwable) {
        }
    }

    /** @return array{html:string,deliver:?string} */
    private function updatesHtml(string $method, array $post): array
    {
        if ($this->updates === null) {
            return ['html' => '', 'deliver' => null];
        }
        try {
            $r = ($this->updates)($method, $post);

            return ['html' => (string) ($r['html'] ?? ''), 'deliver' => is_string($r['deliver'] ?? null) ? $r['deliver'] : null];
        } catch (\Throwable) {
            // keine Details nach aussen
            return ['html' => '<p class="alert alert-danger">Der Update-Bereich hat einen Fehler gemeldet. Der Shop läuft normal weiter.</p>', 'deliver' => null];
        }
    }

    private static function section(string $id, string $title, string $body): string
    {
        $hid = 'mgd-kit-h-' . $id;

        return '<section class="mgd-kit-section" aria-labelledby="' . $hid . '"><h2 id="' . $hid . '">' . Text::e($title) . '</h2>' . $body . '</section>';
    }

    public static function style(): string
    {
        $css = is_file(self::CSS) ? (string) file_get_contents(self::CSS) : '';

        // eigene Datei, trotzdem kein </style> durchlassen
        return $css === '' ? '' : '<style>' . str_replace('</', '', $css) . '</style>';
    }
}
