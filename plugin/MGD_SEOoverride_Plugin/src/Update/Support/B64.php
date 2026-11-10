<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update\Support;

// Base64url ohne Padding, nur kanonische Kodierung (wie im Server src/Update/B64.php)
final class B64
{
    public static function encode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function decode(string $text): string
    {
        if (preg_match('/^[A-Za-z0-9_-]*$/', $text) !== 1 || strlen($text) % 4 === 1) {
            throw new SignatureException('Ungültige Base64url-Kodierung.');
        }
        $bin = base64_decode(strtr($text, '-_', '+/') . str_repeat('=', (4 - strlen($text) % 4) % 4), true);
        if ($bin === false || self::encode($bin) !== $text) {
            throw new SignatureException('Ungültige Base64url-Kodierung.');
        }

        return $bin;
    }
}
