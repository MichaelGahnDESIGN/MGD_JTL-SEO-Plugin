<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update;

use Plugin\MGD_SEOoverride_Plugin\Update\Support\UpdateException;

// Prüft das heruntergeladene ZIP, OHNE etwas auszupacken: Größe, SHA-256, Zip Slip, Symlinks, Zip Bomb, Root-Ordner
final class PackageInspector
{
    public const MAX_RATIO = 200;
    private const S_IFMT = 0170000;
    private const S_IFREG = 0100000;
    private const S_IFDIR = 0040000;

    /**
     * @param array<string,mixed> $package package-Block des signierten Manifests
     * @param list<string> $requiredFiles Dateien relativ zum Root-Ordner, z. B. ['info.xml']
     */
    public static function inspect(string $zipFile, array $package, array $requiredFiles = []): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new UpdateException('PHP-Erweiterung zip fehlt.');
        }
        $size = filesize($zipFile);
        if ($size === false || $size !== $package['size']) {
            throw new UpdateException('Paketgröße stimmt nicht mit dem Manifest überein.');
        }
        $hash = hash_file('sha256', $zipFile);
        if ($hash === false || !hash_equals((string) $package['sha256'], $hash)) {
            throw new UpdateException('SHA-256 stimmt nicht mit dem Manifest überein.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($zipFile, \ZipArchive::RDONLY) !== true) {
            throw new UpdateException('ZIP nicht lesbar.');
        }
        try {
            self::checkEntries($zip, $package, $requiredFiles);
        } finally {
            $zip->close();
        }
    }

    /** @param array<string,mixed> $package @param list<string> $requiredFiles */
    private static function checkEntries(\ZipArchive $zip, array $package, array $requiredFiles): void
    {
        $count = $zip->numFiles;
        if ($count < 1 || $count > (int) $package['max_entries']) {
            throw new UpdateException('ZIP hat zu viele oder keine Einträge.');
        }
        $root = (string) $package['root_dir'];
        $max = (int) $package['max_uncompressed'];
        $total = 0;
        $names = [];
        for ($i = 0; $i < $count; ++$i) {
            $st = $zip->statIndex($i);
            if ($st === false) {
                throw new UpdateException('ZIP-Eintrag nicht lesbar.');
            }
            $name = (string) $st['name'];
            self::checkName($name, $root);
            $isDir = str_ends_with($name, '/');
            $zip->getExternalAttributesIndex($i, $opsys, $attr);
            if ($opsys === \ZipArchive::OPSYS_UNIX) {
                $mode = ($attr >> 16) & self::S_IFMT;
                if ($mode !== 0 && $mode !== self::S_IFREG && $mode !== self::S_IFDIR) {
                    throw new UpdateException('ZIP enthält Symlink oder Spezialdatei.');
                }
            }
            $total += (int) $st['size'];
            if ($total > $max) {
                throw new UpdateException('ZIP ist entpackt zu groß.');
            }
            $comp = max(1, (int) $st['comp_size']);
            if (!$isDir && (int) $st['size'] > 1048576 && (int) $st['size'] / $comp > self::MAX_RATIO) {
                throw new UpdateException('ZIP hat ein verdächtiges Kompressionsverhältnis.');
            }
            $names[$name] = $i;
        }
        // Echte Größe beim Lesen prüfen (Header können lügen), mit Gesamtbudget
        $budget = $max;
        foreach ($names as $name => $i) {
            if (str_ends_with($name, '/')) {
                continue;
            }
            $stream = $zip->getStream($name);
            if ($stream === false) {
                throw new UpdateException('ZIP-Eintrag nicht lesbar.');
            }
            while (!feof($stream)) {
                $chunk = fread($stream, 65536);
                if ($chunk === false) {
                    break;
                }
                $budget -= strlen($chunk);
                if ($budget < 0) {
                    fclose($stream);
                    throw new UpdateException('ZIP ist entpackt zu groß.');
                }
            }
            fclose($stream);
        }
        foreach ($requiredFiles as $f) {
            if (!isset($names[$root . '/' . $f])) {
                throw new UpdateException('Pflichtdatei fehlt im Paket: ' . $f);
            }
        }
    }

    private static function checkName(string $name, string $root): void
    {
        if ($name === '' || strlen($name) > 300 || str_contains($name, "\0") || str_contains($name, '\\')
            || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name) === 1) {
            throw new UpdateException('ZIP enthält einen ungültigen Pfad.');
        }
        foreach (explode('/', rtrim($name, '/')) as $part) {
            if ($part === '..' || $part === '.' || $part === '') {
                throw new UpdateException('ZIP enthält einen ungültigen Pfad (Zip Slip).');
            }
        }
        if ($name !== $root . '/' && !str_starts_with($name, $root . '/')) {
            throw new UpdateException('ZIP enthält ein fremdes Root-Verzeichnis.');
        }
    }
}
