<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update\Crypto;

use Plugin\MGD_SEOoverride_Plugin\Update\Support\{B64, SignatureException};

// Signatur-Hülle der Update-API v1. Signiert wird "MTUPD1\n" . typ . "\n" . payload (Bytes), erst prüfen, dann parsen
final class Envelope
{
    public const DOMAIN = "MTUPD1\n";
    public const TYP_KEYS = 'mt.keys';
    public const TYP_MANIFEST = 'mt.manifest';
    public const TYP_INDEX = 'mt.index';
    public const PURPOSE = [self::TYP_KEYS => 'root', self::TYP_MANIFEST => 'package', self::TYP_INDEX => 'index'];
    public const MAX_PAYLOAD = 1048576;
    public const MAX_SIGNATURES = 4;

    /**
     * @param array<string,mixed>|string $envelope
     * @return array{bytes:string,data:array<string,mixed>,kid:string}
     */
    public static function verify(array|string $envelope, string $typ, KeyRing $keys, int $now): array
    {
        if (!isset(self::PURPOSE[$typ])) {
            throw new SignatureException('Unbekannter typ.');
        }
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            throw new SignatureException('PHP-Erweiterung sodium fehlt.');
        }
        if (is_string($envelope)) {
            if (strlen($envelope) > self::MAX_PAYLOAD * 2) {
                throw new SignatureException('Hülle zu groß.');
            }
            try {
                $envelope = json_decode($envelope, true, 16, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new SignatureException('Hülle ist kein JSON.');
            }
            if (!is_array($envelope)) {
                throw new SignatureException('Hülle ungültig.');
            }
        }
        $payloadB64 = $envelope['payload'] ?? null;
        $sigs = $envelope['signatures'] ?? null;
        if (!is_string($payloadB64) || !is_array($sigs) || !array_is_list($sigs) || $sigs === [] || count($sigs) > self::MAX_SIGNATURES) {
            throw new SignatureException('Hülle ungültig.');
        }
        if (strlen($payloadB64) > (int) ceil(self::MAX_PAYLOAD * 4 / 3) + 4) {
            throw new SignatureException('Payload zu groß.');
        }
        $bytes = B64::decode($payloadB64);
        $message = self::DOMAIN . $typ . "\n" . $bytes;
        $validKid = null;
        foreach ($sigs as $s) {
            if (!is_array($s) || !is_string($s['kid'] ?? null) || !is_string($s['sig'] ?? null)) {
                throw new SignatureException('Signatureintrag ungültig.');
            }
            $public = $keys->find($s['kid'], self::PURPOSE[$typ], $now);
            if ($public === null) {
                continue;
            }
            try {
                $sig = B64::decode($s['sig']);
            } catch (SignatureException) {
                continue;
            }
            if (strlen($sig) === SODIUM_CRYPTO_SIGN_BYTES && sodium_crypto_sign_verify_detached($sig, $message, $public)) {
                $validKid = $s['kid'];
                break;
            }
        }
        if ($validKid === null) {
            throw new SignatureException('Keine gültige Signatur eines berechtigten Schlüssels.');
        }
        try {
            $data = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new SignatureException('Payload ist kein JSON.');
        }
        if (!is_array($data) || array_is_list($data) || ($data['typ'] ?? null) !== $typ || ($data['schema'] ?? null) !== 1) {
            throw new SignatureException('Payload hat falschen typ oder falsches Schema.');
        }

        return ['bytes' => $bytes, 'data' => $data, 'kid' => $validKid];
    }
}
