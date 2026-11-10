<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update;

use Plugin\MGD_SEOoverride_Plugin\Update\Support\Time;

// Prüft das signierte Manifest (typ mt.manifest) gegen Produkt, Plattform, Kanal, Ablauf, Paketangaben
final class Manifest
{
    public const PLATFORM = 'jtl5';
    public const CHANNEL = 'stable';
    public const VERSION_PATTERN = '/^\d{1,4}\.\d{1,4}\.\d{1,4}$/';
    public const MAX_PACKAGE_SIZE = 209715200;
    public const MAX_UNCOMPRESSED = 524288000;
    public const MAX_ENTRIES = 20000;

    /** @return list<string> Fehler, leer = gültig */
    public static function validate(mixed $m, string $product): array
    {
        if (!is_array($m) || array_is_list($m)) {
            return ['Manifest ist kein Objekt.'];
        }
        $e = [];
        if (($m['product'] ?? null) !== $product) {
            $e[] = 'Produkt passt nicht.';
        }
        if (($m['platform'] ?? null) !== self::PLATFORM) {
            $e[] = 'Plattform passt nicht.';
        }
        if (($m['channel'] ?? null) !== self::CHANNEL) {
            $e[] = 'Kanal passt nicht.';
        }
        if (!self::isVersion($m['version'] ?? null)) {
            $e[] = 'Version ungültig.';
        }
        if (!is_string($m['released_at'] ?? null) || Time::parse($m['released_at']) === null) {
            $e[] = 'released_at ungültig.';
        }
        if (!in_array($m['edition'] ?? null, ['free', 'pro'], true)) {
            $e[] = 'edition ungültig.';
        }
        if (!is_bool($m['security'] ?? null) || !is_bool($m['rollback_safe'] ?? null)) {
            $e[] = 'security oder rollback_safe ungültig.';
        }
        if (!self::isVersion($m['min_client'] ?? null)) {
            $e[] = 'min_client ungültig.';
        }
        $cl = $m['changelog_short'] ?? null;
        if (!is_string($cl) || trim($cl) === '' || mb_strlen($cl) > 500 || preg_match('/[\x00-\x09\x0B-\x1F\x7F]|<[a-zA-Z\/!?]/u', $cl) === 1) {
            $e[] = 'changelog_short ungültig.';
        }
        if (array_key_exists('expires', $m) && $m['expires'] !== null && (!is_string($m['expires']) || Time::parse($m['expires']) === null)) {
            $e[] = 'expires ungültig.';
        }

        return array_merge($e, self::validatePackage($m['package'] ?? null));
    }

    public static function isExpired(array $m, int $now): bool
    {
        $exp = isset($m['expires']) && is_string($m['expires']) ? Time::parse($m['expires']) : null;

        return $exp !== null && $now >= $exp;
    }

    public static function isVersion(mixed $v): bool
    {
        return is_string($v) && preg_match(self::VERSION_PATTERN, $v) === 1;
    }

    public static function compare(string $a, string $b): int
    {
        return array_map('intval', explode('.', $a)) <=> array_map('intval', explode('.', $b));
    }

    /** @return list<string> */
    private static function validatePackage(mixed $p): array
    {
        if (!is_array($p) || array_is_list($p)) {
            return ['package fehlt.'];
        }
        $e = [];
        if (!is_string($p['filename'] ?? null) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,150}\.zip$/', $p['filename']) !== 1) {
            $e[] = 'package.filename ungültig.';
        }
        if (!is_string($p['sha256'] ?? null) || preg_match('/^[0-9a-f]{64}$/', $p['sha256']) !== 1) {
            $e[] = 'package.sha256 ungültig.';
        }
        if (!is_int($p['size'] ?? null) || $p['size'] < 1 || $p['size'] > self::MAX_PACKAGE_SIZE) {
            $e[] = 'package.size ungültig.';
        }
        $root = $p['root_dir'] ?? null;
        if (!is_string($root) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,79}$/', $root) !== 1 || str_contains($root, '..')) {
            $e[] = 'package.root_dir ungültig.';
        }
        if (!is_int($p['max_uncompressed'] ?? null) || $p['max_uncompressed'] < 1 || $p['max_uncompressed'] > self::MAX_UNCOMPRESSED) {
            $e[] = 'package.max_uncompressed ungültig.';
        }
        if (!is_int($p['max_entries'] ?? null) || $p['max_entries'] < 1 || $p['max_entries'] > self::MAX_ENTRIES) {
            $e[] = 'package.max_entries ungültig.';
        }

        return $e;
    }
}
