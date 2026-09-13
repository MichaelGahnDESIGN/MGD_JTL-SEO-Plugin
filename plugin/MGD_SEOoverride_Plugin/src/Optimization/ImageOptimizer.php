<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Optimization;

use JTL\Shop;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\UrlGuard;
use RuntimeException;

final class ImageOptimizer
{
    private const MAX_BYTES = 20_000_000;
    private const MIN_SAVING_RATIO = 0.03;

    /** @return array<string,mixed> */
    public function optimize(string $url, int $quality = 82): array
    {
        $quality = max(55, min(92, $quality));
        $path = $this->localFile($url);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, ['webp', 'jpg', 'jpeg'], true)) {
            throw new RuntimeException('Der integrierte Bildoptimierer bearbeitet aus Sicherheitsgründen derzeit nur WebP und JPEG. PNG/AVIF werden analysiert, aber nicht überschrieben.');
        }

        $before = filesize($path);
        if ($before === false || $before < 1 || $before > self::MAX_BYTES) {
            throw new RuntimeException('Die Bilddatei ist leer oder überschreitet das Sicherheitslimit.');
        }

        $backupDir = $this->backupDirectory();
        $token = gmdate('YmdHis') . '-' . substr(hash('sha256', $path . '|' . microtime(true)), 0, 16) . '-' . basename($path) . '.bak';
        $backup = $backupDir . DIRECTORY_SEPARATOR . $token;
        if (!copy($path, $backup)) {
            throw new RuntimeException('Das Originalbild konnte nicht gesichert werden.');
        }

        $tmp = tempnam(dirname($path), '.mgd-seo-image-');
        if ($tmp === false) {
            @unlink($backup);
            throw new RuntimeException('Temporäre Bilddatei konnte nicht angelegt werden.');
        }

        try {
            $this->encode($path, $tmp, $extension, $quality);
            $after = filesize($tmp);
            if ($after === false || $after < 1) {
                throw new RuntimeException('Die optimierte Datei ist ungültig.');
            }

            $saving = $before - $after;
            if ($saving <= 0 || ($saving / $before) < self::MIN_SAVING_RATIO) {
                @unlink($tmp);
                @unlink($backup);
                return [
                    'changed' => false,
                    'url' => $url,
                    'before_bytes' => $before,
                    'after_bytes' => $before,
                    'saved_bytes' => 0,
                    'message' => 'Keine Ersetzung: Die Neuberechnung spart weniger als 3 % oder wäre größer als das Original.',
                ];
            }

            $permissions = fileperms($path);
            if (!@rename($tmp, $path)) {
                throw new RuntimeException('Die optimierte Datei konnte nicht atomar übernommen werden.');
            }
            if (is_int($permissions)) {
                @chmod($path, $permissions & 0777);
            }

            clearstatcache(true, $path);
            return [
                'changed' => true,
                'url' => $url,
                'before_bytes' => $before,
                'after_bytes' => $after,
                'saved_bytes' => $saving,
                'backup_token' => $token,
                'quality' => $quality,
                'message' => 'Bild wurde optimiert. Das Original liegt geschützt im MGD-Backupverzeichnis.',
            ];
        } catch (\Throwable $e) {
            @unlink($tmp);
            if (is_file($backup) && is_file($path)) {
                @copy($backup, $path);
            }
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    public function restore(string $url, string $backupToken): array
    {
        if ($backupToken === '' || basename($backupToken) !== $backupToken || !preg_match('/^[A-Za-z0-9._-]+$/D', $backupToken)) {
            throw new RuntimeException('Ungültiger Backup-Schlüssel.');
        }
        $path = $this->localFile($url);
        $backup = $this->backupDirectory() . DIRECTORY_SEPARATOR . $backupToken;
        if (!is_file($backup) || !is_readable($backup)) {
            throw new RuntimeException('Das angeforderte Bildbackup ist nicht vorhanden.');
        }
        if (!copy($backup, $path)) {
            throw new RuntimeException('Das Originalbild konnte nicht wiederhergestellt werden.');
        }
        @unlink($backup);
        clearstatcache(true, $path);
        return ['restored' => true, 'url' => $url, 'bytes' => filesize($path) ?: null];
    }

    private function encode(string $source, string $target, string $extension, int $quality): void
    {
        if (class_exists('Imagick')) {
            $image = new \Imagick($source);
            try {
                if (method_exists($image, 'autoOrient')) {
                    $image->autoOrient();
                } elseif (method_exists($image, 'autoOrientImage')) {
                    $image->autoOrientImage();
                }
                if ($extension === 'webp') {
                    $image->setImageFormat('webp');
                    $image->setOption('webp:method', '6');
                } else {
                    $image->setImageFormat('jpeg');
                    $image->setInterlaceScheme(\Imagick::INTERLACE_PLANE);
                }
                $image->setImageCompressionQuality($quality);
                if (!$image->writeImage($target)) {
                    throw new RuntimeException('Imagick konnte die optimierte Datei nicht schreiben.');
                }
            } finally {
                $image->clear();
                $image->destroy();
            }
            return;
        }

        if ($extension === 'webp' && function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
            $image = @imagecreatefromwebp($source);
            if ($image === false || !imagewebp($image, $target, $quality)) {
                if (is_resource($image) || $image instanceof \GdImage) {
                    imagedestroy($image);
                }
                throw new RuntimeException('GD konnte das WebP-Bild nicht optimieren.');
            }
            imagedestroy($image);
            return;
        }

        if (in_array($extension, ['jpg', 'jpeg'], true) && function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) {
            $image = @imagecreatefromjpeg($source);
            if ($image === false || !imagejpeg($image, $target, $quality)) {
                if (is_resource($image) || $image instanceof \GdImage) {
                    imagedestroy($image);
                }
                throw new RuntimeException('GD konnte das JPEG-Bild nicht optimieren.');
            }
            imagedestroy($image);
            return;
        }

        throw new RuntimeException('Für die Bildoptimierung wird Imagick oder die passende GD-Unterstützung benötigt.');
    }

    private function localFile(string $url): string
    {
        $normalized = UrlGuard::normalize($url);
        if ($normalized === null || !UrlGuard::isSameShopHost($normalized)) {
            throw new RuntimeException('Es dürfen nur Bilder der eigenen Shop-Domain optimiert werden.');
        }
        $urlPath = rawurldecode((string)parse_url($normalized, PHP_URL_PATH));
        if ($urlPath === '' || str_contains($urlPath, "\0")) {
            throw new RuntimeException('Ungültiger Bildpfad.');
        }
        $shopBase = rtrim((string)parse_url(Shop::getURL(), PHP_URL_PATH), '/');
        if ($shopBase !== '' && str_starts_with($urlPath, $shopBase . '/')) {
            $urlPath = substr($urlPath, strlen($shopBase));
        }

        $root = realpath(PFAD_ROOT);
        $candidate = $root === false ? false : realpath(PFAD_ROOT . ltrim($urlPath, '/'));
        if ($root === false || $candidate === false || !is_file($candidate) || !is_writable($candidate)) {
            throw new RuntimeException('Die lokale Bilddatei wurde nicht gefunden oder ist nicht beschreibbar.');
        }
        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($candidate, $prefix)) {
            throw new RuntimeException('Der Bildpfad liegt außerhalb des Shop-Verzeichnisses.');
        }
        return $candidate;
    }

    private function backupDirectory(): string
    {
        $root = realpath(PFAD_ROOT);
        if ($root === false) {
            throw new RuntimeException('Shop-Stammverzeichnis konnte nicht ermittelt werden.');
        }
        $directory = $root . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . 'mgd-jtl-seo-backups';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Backupverzeichnis konnte nicht angelegt werden.');
        }
        $denyFile = $directory . DIRECTORY_SEPARATOR . '.htaccess';
        if (!is_file($denyFile)) {
            @file_put_contents($denyFile, "Require all denied\nDeny from all\n", LOCK_EX);
        }
        return $directory;
    }
}
