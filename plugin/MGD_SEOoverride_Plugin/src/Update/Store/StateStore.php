<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update\Store;

// Kleiner Zustand: enabled, last_seen_seq, keys_version, last_check, last_result
interface StateStore
{
    /** @return array<string,mixed> */
    public function load(): array;

    /** @param array<string,mixed> $state */
    public function save(array $state): void;
}
