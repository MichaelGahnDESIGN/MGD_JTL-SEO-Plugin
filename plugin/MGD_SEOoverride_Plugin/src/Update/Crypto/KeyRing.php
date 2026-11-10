<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update\Crypto;

use Plugin\MGD_SEOoverride_Plugin\Update\Support\{B64, SignatureException, Time};

// Öffentliche Ed25519-Schlüssel mit Zweckbindung (root, package, index), Ablauf und Widerruf
final class KeyRing
{
    public const PURPOSES = ['root', 'package', 'index'];
    public const KID_PATTERN = '/^[a-z0-9][a-z0-9._-]{2,63}$/';

    /** @var array<string,array{purpose:string,public:string,not_after:?int}> */
    private array $keys = [];
    /** @var array<string,true> */
    private array $revoked = [];

    public function add(string $kid, string $purpose, string $publicKey, ?int $notAfter = null): self
    {
        if (preg_match(self::KID_PATTERN, $kid) !== 1 || !in_array($purpose, self::PURPOSES, true)) {
            throw new SignatureException('Schlüsseleintrag ungültig.');
        }
        if (strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new SignatureException('Öffentlicher Schlüssel hat nicht 32 Byte.');
        }
        if (isset($this->keys[$kid])) {
            throw new SignatureException('kid doppelt vergeben.');
        }
        $this->keys[$kid] = ['purpose' => $purpose, 'public' => $publicKey, 'not_after' => $notAfter];

        return $this;
    }

    public function revoke(string $kid): self
    {
        $this->revoked[$kid] = true;

        return $this;
    }

    public function find(string $kid, string $purpose, int $now): ?string
    {
        $k = $this->keys[$kid] ?? null;
        if ($k === null || $k['purpose'] !== $purpose || isset($this->revoked[$kid])) {
            return null;
        }
        if ($k['not_after'] !== null && $now > $k['not_after']) {
            return null;
        }

        return $k['public'];
    }

    public function isEmpty(): bool
    {
        return $this->keys === [];
    }

    /**
     * Gepinnte Root-Schlüssel aus root-keys.json des Plugins. Leere Liste = Schlüsselzeremonie noch nicht erfolgt.
     *
     * @param array<string,mixed> $data
     */
    public static function fromRootFile(array $data): self
    {
        $ring = new self();
        $entries = $data['keys'] ?? [];
        if (!is_array($entries) || !array_is_list($entries) || count($entries) > 10) {
            throw new SignatureException('root-keys.json ungültig.');
        }
        foreach ($entries as $e) {
            if (!is_array($e) || !is_string($e['kid'] ?? null) || !is_string($e['public'] ?? null)) {
                throw new SignatureException('root-keys.json ungültig.');
            }
            $ring->add($e['kid'], 'root', B64::decode($e['public']));
        }

        return $ring;
    }

    /**
     * Ring aus einer geprüften Schlüsselliste (mt.keys). Root-Einträge aus der Liste werden NIE übernommen (nur gepinnt).
     *
     * @param array<string,mixed> $payload
     */
    public static function fromKeysPayload(array $payload): self
    {
        $ring = new self();
        $entries = $payload['keys'] ?? null;
        if (!is_array($entries) || !array_is_list($entries) || count($entries) > 50) {
            throw new SignatureException('Schlüsselliste ungültig.');
        }
        foreach ($entries as $e) {
            if (!is_array($e) || !is_string($e['kid'] ?? null) || !is_string($e['purpose'] ?? null) || !is_string($e['public'] ?? null)
                || ($e['alg'] ?? 'ed25519') !== 'ed25519') {
                throw new SignatureException('Eintrag der Schlüsselliste ungültig.');
            }
            if ($e['purpose'] === 'root') {
                continue;
            }
            $notAfter = null;
            if (isset($e['not_after'])) {
                $notAfter = is_string($e['not_after']) ? Time::parse($e['not_after']) : null;
                if ($notAfter === null) {
                    throw new SignatureException('not_after ungültig.');
                }
            }
            $ring->add($e['kid'], $e['purpose'], B64::decode($e['public']), $notAfter);
        }
        foreach (is_array($payload['revoked_kids'] ?? null) ? $payload['revoked_kids'] : [] as $kid) {
            if (is_string($kid)) {
                $ring->revoke($kid);
            }
        }

        return $ring;
    }
}
