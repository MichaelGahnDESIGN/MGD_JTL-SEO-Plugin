<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update;

// Ergebnis einer Prüfung. status: up_to_date | update_available | security_update_only | not_entitled | channel_stale | not_configured | error
final class UpdateResult
{
    /** @param array<string,mixed>|null $manifest geprüftes Manifest @param array<string,mixed> $extra */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?array $manifest = null,
        public readonly ?string $downloadUrl = null,
        public readonly array $extra = [],
    ) {
    }

    public function hasPackage(): bool
    {
        return $this->manifest !== null && $this->downloadUrl !== null
            && in_array($this->status, ['update_available', 'security_update_only'], true);
    }
}
