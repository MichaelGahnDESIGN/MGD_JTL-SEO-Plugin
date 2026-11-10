<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update\Store;

use Plugin\MGD_SEOoverride_Plugin\Update\Support\UpdateException;

// Zustand als JSON-Datei im Plugin-Datenordner (atomar geschrieben, nur Besitzer)
final class JsonFileStateStore implements StateStore
{
    public function __construct(private readonly string $file)
    {
    }

    public function load(): array
    {
        if (!is_file($this->file)) {
            return [];
        }
        $raw = @file_get_contents($this->file);
        $d = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($d) ? $d : [];
    }

    public function save(array $state): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new UpdateException('Datenordner nicht anlegbar.');
        }
        $tmp = $this->file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            throw new UpdateException('Zustand nicht speicherbar.');
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $this->file)) {
            @unlink($tmp);
            throw new UpdateException('Zustand nicht speicherbar.');
        }
    }
}
