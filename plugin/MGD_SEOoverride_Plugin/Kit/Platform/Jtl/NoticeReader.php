<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Kit\Platform\Jtl;

use Plugin\MGD_SEOoverride_Plugin\Kit\Install\{InstallClient, Store};
use Plugin\MGD_SEOoverride_Plugin\Kit\Update\UpdateInfo;

// Rohes Update-JSON für den notice-Hinweis, über install-client fetchUpdates() (zählt mit, Rückfall feste Datei)
// Der signierte/einfache Update-Client bleibt unangetastet, wir lesen nur nebenher
// Netz nur wenn Update-Prüfung an ODER Installation melden an, sonst bleibt der Shop still (Opt-in wie UpdatesPage)
final class NoticeReader
{
    public const CACHE_OK = 43200; // 12 Std wie WP-Updater
    public const CACHE_FAIL = 1800;

    /** @var \Closure():int */
    private \Closure $clock;

    /**
     * @param Store $cache eigener Zwischenspeicher (z. B. <datenordner>/kit-notice.json)
     * @param Store|null $updateState state.json der UpdatesPage, nur lesen (Schalter "Update-Prüfung")
     */
    public function __construct(
        private readonly ?InstallClient $client,
        private readonly Store $cache,
        private readonly string $product,
        private readonly ?Store $updateState = null,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /** null = aus, Server weg oder Datei ungültig. Wirft nie */
    public function info(): ?UpdateInfo
    {
        try {
            return UpdateInfo::parse($this->raw(), $this->product);
        } catch (\Throwable) {
            return null;
        }
    }

    public function allowed(): bool
    {
        if ($this->client === null) {
            return false;
        }
        try {
            $st = $this->updateState?->load() ?? [];

            return ($st['enabled'] ?? false) === true || $this->client->isEnabled();
        } catch (\Throwable) {
            return false;
        }
    }

    private function raw(): string
    {
        $now = ($this->clock)();
        $c = $this->cache->load();
        $at = (int) ($c['at'] ?? 0);
        $raw = is_string($c['raw'] ?? null) ? $c['raw'] : '';
        $ttl = $raw !== '' ? self::CACHE_OK : self::CACHE_FAIL;
        if ($at > 0 && $at <= $now && $now - $at < $ttl) {
            return $raw;
        }
        if (!$this->allowed()) {
            return '';
        }
        $fresh = (string) ($this->client?->fetchUpdates() ?? '');
        $ok = UpdateInfo::parse($fresh, $this->product) !== null;
        $this->store(['at' => $now, 'raw' => $ok ? $fresh : '']);

        return $ok ? $fresh : '';
    }

    private function store(array $st): void
    {
        try {
            $this->cache->save($st);
        } catch (\Throwable) {
            // Datenordner zu, dann halt beim nächsten mal neu
        }
    }
}
