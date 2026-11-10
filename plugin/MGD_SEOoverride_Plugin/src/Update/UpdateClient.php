<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update;

use Plugin\MGD_SEOoverride_Plugin\Update\Crypto\{Envelope, KeyRing};
use Plugin\MGD_SEOoverride_Plugin\Update\Http\HttpClient;
use Plugin\MGD_SEOoverride_Plugin\Update\License\LicenseProvider;
use Plugin\MGD_SEOoverride_Plugin\Update\Store\StateStore;
use Plugin\MGD_SEOoverride_Plugin\Update\Support\{SignatureException, Time, UpdateException};

// Gemeinsamer Update-Client für JTL-Shop 5 (Update-API v1). Prüft nur, installiert nichts
final class UpdateClient
{
    private const STALE_TOLERANCE = 300;
    private const MAX_RESPONSE = 1048576;

    /** @var callable():int */
    private $clock;

    public function __construct(
        private readonly ClientConfig $cfg,
        private readonly HttpClient $http,
        private readonly LicenseProvider $license,
        private readonly StateStore $state,
        ?callable $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    public function check(): UpdateResult
    {
        if ($this->cfg->rootKeys->isEmpty()) {
            // ohne Schlüssel: einfacher Modus mit Prüfsumme, falls Adresse gesetzt
            if ($this->cfg->simpleUrl !== null && $this->cfg->edition === 'free') {
                try {
                    return $this->checkSimple($this->cfg->simpleUrl);
                } catch (UpdateException $e) {
                    return new UpdateResult('error', $e->getMessage());
                }
            }

            return new UpdateResult('not_configured', 'Update-Kanal noch nicht eingerichtet (Schlüssel fehlen).');
        }
        try {
            $ring = $this->loadKeys();

            return $this->cfg->edition === 'pro' ? $this->checkPro($ring) : $this->checkFree($ring);
        } catch (SignatureException $e) {
            return new UpdateResult('error', 'Signaturprüfung fehlgeschlagen: ' . $e->getMessage());
        } catch (UpdateException $e) {
            return new UpdateResult('error', $e->getMessage());
        }
    }

    /**
     * Lädt das Paket in die Quarantäne und prüft es. Packt NICHTS aus und fasst das Plugin-Verzeichnis nie an.
     *
     * @return string Pfad der geprüften ZIP-Datei
     */
    public function downloadVerified(UpdateResult $r): string
    {
        if (!$r->hasPackage() || $r->manifest === null || $r->downloadUrl === null) {
            throw new UpdateException('Kein Paket zum Herunterladen.');
        }
        $pkg = $r->manifest['package'];
        $dir = rtrim($this->cfg->dataDir, '/') . '/quarantine';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new UpdateException('Quarantäne-Ordner nicht anlegbar.');
        }
        $part = $dir . '/' . bin2hex(random_bytes(8)) . '.part';
        $res = $this->http->download($r->downloadUrl, [], $part, (int) $pkg['size']);
        if ($res->status !== 200) {
            @unlink($part);
            throw new UpdateException('Download fehlgeschlagen (HTTP ' . $res->status . ').');
        }
        try {
            PackageInspector::inspect($part, $pkg, $this->cfg->requiredFiles);
        } catch (UpdateException $e) {
            @unlink($part);
            throw $e;
        }
        $final = $dir . '/' . $pkg['filename'];
        if (!@rename($part, $final)) {
            @unlink($part);
            throw new UpdateException('Paket nicht ablegbar.');
        }
        @chmod($final, 0600);

        return $final;
    }

    private function now(): int
    {
        return ($this->clock)();
    }

    private function url(string $path): string
    {
        return rtrim($this->cfg->baseUrl, '/') . $path;
    }

    private function loadKeys(): KeyRing
    {
        $res = $this->http->request('GET', $this->url('/api/v1/updates/keys.json'), [], null, self::MAX_RESPONSE);
        if ($res->status === 503) {
            throw new UpdateException('Update-Kanal ist gerade nicht eingerichtet.');
        }
        if ($res->status !== 200) {
            throw new UpdateException('Schlüsselliste nicht abrufbar (HTTP ' . $res->status . ').');
        }
        $v = Envelope::verify($res->body, Envelope::TYP_KEYS, $this->cfg->rootKeys, $this->now());
        $exp = is_string($v['data']['expires'] ?? null) ? Time::parse($v['data']['expires']) : null;
        if ($exp === null || $this->now() >= $exp) {
            throw new UpdateException('Schlüsselliste ist abgelaufen.');
        }
        $ver = $v['data']['version'] ?? null;
        $st = $this->state->load();
        if (!is_int($ver) || $ver < (int) ($st['keys_version'] ?? 0)) {
            throw new SignatureException('Schlüsselliste ist älter als die bekannte.');
        }
        $this->state->save(['keys_version' => $ver] + $st);

        return KeyRing::fromKeysPayload($v['data']);
    }

    // Einfacher Modus: Textdatei mit Version + SHA-256 (ohne Signatur). Download kommt von GitHub, Hash wird beim Download geprüft
    private function checkSimple(string $url): UpdateResult
    {
        $res = $this->http->request('GET', $url, [], null, self::MAX_RESPONSE);
        if ($res->status !== 200) {
            throw new UpdateException('Update-Datei nicht abrufbar (HTTP ' . $res->status . ').');
        }
        $d = json_decode($res->body, true);
        if (!is_array($d) || ($d['schema'] ?? null) !== 1 || ($d['product'] ?? null) !== $this->cfg->product) {
            throw new UpdateException('Update-Datei gehört nicht zu diesem Plugin.');
        }
        $ver = $d['version'] ?? null;
        $file = $d['filename'] ?? null;
        $size = $d['size'] ?? null;
        $sha = $d['sha256'] ?? null;
        $root = $d['root_dir'] ?? null;
        $dl = $d['download_url'] ?? null;
        $ok = is_string($ver) && Manifest::isVersion($ver)
            && is_string($file) && preg_match('/^[A-Za-z0-9._-]{1,120}\.zip$/', $file) === 1
            && is_int($size) && $size > 0 && $size <= 52428800
            && is_string($sha) && preg_match('/^[a-f0-9]{64}$/', $sha) === 1
            && is_string($root) && preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $root) === 1
            && is_string($dl) && $this->downloadUrlAllowed($dl)
            && str_starts_with((string) parse_url($dl, PHP_URL_PATH), '/MichaelGahnDESIGN/');
        if (!$ok) {
            throw new UpdateException('Update-Datei ist ungültig.');
        }
        if (Manifest::compare($ver, $this->cfg->installedVersion) <= 0) {
            return new UpdateResult('up_to_date', 'Kein neueres Update verfügbar.');
        }
        $m = [
            'version' => $ver,
            'changelog_short' => is_string($d['changelog'] ?? null) ? mb_substr($d['changelog'], 0, 300) : null,
            'package' => [
                'filename' => $file, 'size' => $size, 'sha256' => $sha, 'root_dir' => $root,
                'max_entries' => 2000, 'max_uncompressed' => 52428800,
            ],
        ];

        return new UpdateResult('update_available', 'Update ' . $ver . ' verfügbar.', $m, $dl, ['mode' => 'simple']);
    }

    private function checkFree(KeyRing $ring): UpdateResult
    {
        $c = $this->cfg;
        $res = $this->http->request('GET', $this->url('/api/v1/updates/' . $c->product . '/jtl5/stable/index.json'), [], null, self::MAX_RESPONSE);
        if ($res->status === 503) {
            return new UpdateResult('error', 'Update-Kanal ist gerade nicht eingerichtet.');
        }
        if ($res->status !== 200) {
            throw new UpdateException('Update-Liste nicht abrufbar (HTTP ' . $res->status . ').');
        }
        $idx = Envelope::verify($res->body, Envelope::TYP_INDEX, $ring, $this->now())['data'];
        if (($idx['product'] ?? null) !== $c->product || ($idx['platform'] ?? null) !== 'jtl5' || ($idx['channel'] ?? null) !== 'stable') {
            throw new SignatureException('Index gehört zu einem anderen Produkt oder Kanal.');
        }
        $exp = is_string($idx['expires'] ?? null) ? Time::parse($idx['expires']) : null;
        if ($exp === null || $exp <= $this->now() - self::STALE_TOLERANCE) {
            return new UpdateResult('channel_stale', 'Update-Kanal veraltet. Es wird nichts angeboten.');
        }
        $st = $this->state->load();
        if (!is_int($idx['seq'] ?? null) || $idx['seq'] < (int) ($st['last_seen_seq'] ?? 0)) {
            throw new SignatureException('Index ist älter als der zuletzt gesehene (Replay).');
        }
        $this->state->save(['last_seen_seq' => $idx['seq']] + $st);
        $yanked = $this->yankedVersions($idx['yanked'] ?? []);
        $best = null;
        $bestUrl = null;
        foreach (is_array($idx['releases'] ?? null) ? $idx['releases'] : [] as $rel) {
            if (!is_array($rel) || !isset($rel['manifest'])) {
                continue;
            }
            $m = $this->acceptManifest($rel['manifest'], $ring, $yanked);
            $url = $rel['free_url'] ?? null;
            if ($m === null || !is_string($url) || !$this->downloadUrlAllowed($url)) {
                continue;
            }
            if ($best === null || Manifest::compare($m['version'], $best['version']) > 0) {
                $best = $m;
                $bestUrl = $url;
            }
        }
        if ($best === null) {
            return new UpdateResult('up_to_date', 'Kein neueres Update verfügbar.');
        }

        return new UpdateResult('update_available', 'Update ' . $best['version'] . ' verfügbar.', $best, $bestUrl);
    }

    private function checkPro(KeyRing $ring): UpdateResult
    {
        $c = $this->cfg;
        $token = $this->license->token();
        if ($token === null || $token === '') {
            return new UpdateResult('not_entitled', 'Keine Lizenz hinterlegt. Updates gibt es nur mit aktiver Lizenz.', null, null, ['reason' => 'license_missing']);
        }
        // Nur die Felder aus dem Vertrag, keine Shop-, PHP- oder Plugin-Daten
        $body = json_encode([
            'schema' => 1, 'product' => $c->product, 'platform' => 'jtl5', 'channel' => 'stable',
            'domain' => $c->domain, 'installed_version' => $c->installedVersion, 'client_version' => ClientConfig::CLIENT_VERSION,
        ], JSON_UNESCAPED_SLASHES);
        $res = $this->http->request('POST', $this->url('/api/v1/updates/check'), [
            'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json',
        ], $body, self::MAX_RESPONSE);
        if ($res->status === 401) {
            return new UpdateResult('not_entitled', 'Lizenz konnte nicht geprüft werden.', null, null, ['reason' => 'auth_invalid']);
        }
        if ($res->status !== 200) {
            throw new UpdateException('Lizenzprüfung nicht möglich (HTTP ' . $res->status . ').');
        }
        $d = json_decode($res->body, true);
        if (!is_array($d) || ($d['schema'] ?? null) !== 1 || !is_string($d['status'] ?? null)) {
            throw new UpdateException('Antwort des Update-Servers ungültig.');
        }
        $status = $d['status'];
        if ($status === 'not_entitled') {
            $reason = is_array($d['entitlement'] ?? null) && is_string($d['entitlement']['reason'] ?? null) ? $d['entitlement']['reason'] : 'unknown';

            return new UpdateResult('not_entitled', 'Für diese Lizenz gibt es keine Updates.', null, null, ['reason' => $reason]);
        }
        if ($status === 'up_to_date') {
            return new UpdateResult('up_to_date', 'Kein neueres Update verfügbar.');
        }
        if (!in_array($status, ['update_available', 'security_update_only'], true)) {
            throw new UpdateException('Unbekannter Status vom Update-Server.');
        }
        $m = $this->acceptManifest($d['release'] ?? null, $ring, []);
        $dl = is_array($d['download'] ?? null) && is_string($d['download']['url'] ?? null) ? $d['download']['url'] : null;
        if ($m === null || $dl === null || !$this->centralUrl($dl)) {
            throw new SignatureException('Release oder Download-Adresse ungültig.');
        }

        return new UpdateResult($status, 'Update ' . $m['version'] . ' verfügbar.', $m, $dl);
    }

    /** @param list<mixed>|mixed $y @return list<string> */
    private function yankedVersions(mixed $y): array
    {
        $out = [];
        foreach (is_array($y) ? $y : [] as $e) {
            $v = is_array($e) ? ($e['version'] ?? null) : $e;
            if (is_string($v)) {
                $out[] = $v;
            }
        }

        return $out;
    }

    /**
     * Signatur, Schema, Ablauf, Version (nur neuer), Client- und PHP-Kompatibilität. Null = nicht anbieten.
     *
     * @param list<string> $yanked
     * @return array<string,mixed>|null
     */
    private function acceptManifest(mixed $envelope, KeyRing $ring, array $yanked): ?array
    {
        if (!is_array($envelope)) {
            return null;
        }
        try {
            $m = Envelope::verify($envelope, Envelope::TYP_MANIFEST, $ring, $this->now())['data'];
        } catch (SignatureException) {
            return null;
        }
        if (Manifest::validate($m, $this->cfg->product) !== [] || Manifest::isExpired($m, $this->now())) {
            return null;
        }
        $edition = $this->cfg->edition;
        if ($m['edition'] !== $edition || in_array($m['version'], $yanked, true)) {
            return null;
        }
        if (Manifest::compare($m['version'], $this->cfg->installedVersion) <= 0) {
            return null;
        }
        if (Manifest::compare($m['min_client'], ClientConfig::CLIENT_VERSION) > 0) {
            return null;
        }
        $compat = is_array($m['compat'] ?? null) ? $m['compat'] : [];
        $phpMin = $compat['php_min'] ?? null;
        if (is_string($phpMin) && version_compare(PHP_VERSION, $phpMin, '<')) {
            return null;
        }
        $shopMin = $compat['shop_min'] ?? null;
        if (is_string($shopMin) && $this->cfg->shopVersion !== '' && version_compare($this->cfg->shopVersion, $shopMin, '<')) {
            return null;
        }
        foreach (is_array($compat['requires_ext'] ?? null) ? $compat['requires_ext'] : [] as $ext) {
            if (is_string($ext) && !extension_loaded($ext)) {
                return null;
            }
        }

        return $m;
    }

    private function centralUrl(string $url): bool
    {
        $p = parse_url($url);

        return is_array($p) && ($p['scheme'] ?? '') === 'https' && strtolower((string) ($p['host'] ?? '')) === $this->cfg->host()
            && !isset($p['user']) && (!isset($p['port']) || $p['port'] === 443);
    }

    private function downloadUrlAllowed(string $url): bool
    {
        $p = parse_url($url);

        return is_array($p) && ($p['scheme'] ?? '') === 'https' && !isset($p['user']) && (!isset($p['port']) || $p['port'] === 443)
            && in_array(strtolower((string) ($p['host'] ?? '')), $this->cfg->allowedHosts(), true);
    }
}
