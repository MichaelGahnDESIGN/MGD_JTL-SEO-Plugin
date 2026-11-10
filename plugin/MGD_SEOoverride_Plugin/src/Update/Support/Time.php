<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update\Support;

// Zeiten der Update-API: immer UTC, 2026-11-03T10:00:00Z
final class Time
{
    public static function format(int $ts): string
    {
        return gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    public static function parse(string $s): ?int
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $s) !== 1) {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $s, new \DateTimeZone('UTC'));

        return $d === false || self::format($d->getTimestamp()) !== $s ? null : $d->getTimestamp();
    }
}
