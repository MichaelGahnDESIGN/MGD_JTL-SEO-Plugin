<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update\Http;

use Plugin\MGD_SEOoverride_Plugin\Update\Support\UpdateException;

// cURL-Adapter: nur https, Host-Allowlist (auch bei Weiterleitungen), Zertifikatsprüfung an, Größenlimit
final class CurlHttpClient implements HttpClient
{
    public const MAX_REDIRECTS = 3;

    /** @param list<string> $allowedHosts exakte Hostnamen (klein) */
    public function __construct(private readonly array $allowedHosts, private readonly string $userAgent, private readonly int $timeout = 20)
    {
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $maxBytes = 1048576): HttpResponse
    {
        $buf = '';
        $res = $this->run($method, $url, $headers, $body, $maxBytes, static function (string $chunk) use (&$buf): void {
            $buf .= $chunk;
        });

        return new HttpResponse($res, $buf);
    }

    public function download(string $url, array $headers, string $targetFile, int $maxBytes): HttpResponse
    {
        $fh = fopen($targetFile, 'wb');
        if ($fh === false) {
            throw new UpdateException('Zieldatei nicht beschreibbar.');
        }
        try {
            $status = $this->run('GET', $url, $headers, null, $maxBytes, static function (string $chunk) use ($fh): void {
                fwrite($fh, $chunk);
            });
        } finally {
            fclose($fh);
        }
        if ($status !== 200) {
            @unlink($targetFile);
        }

        return new HttpResponse($status);
    }

    public function isAllowed(string $url): bool
    {
        $p = parse_url($url);

        return is_array($p) && ($p['scheme'] ?? '') === 'https' && !isset($p['user']) && !isset($p['pass'])
            && in_array(strtolower((string) ($p['host'] ?? '')), $this->allowedHosts, true)
            && (!isset($p['port']) || $p['port'] === 443);
    }

    /** @param array<string,string> $headers */
    private function run(string $method, string $url, array $headers, ?string $body, int $maxBytes, callable $sink): int
    {
        if (!function_exists('curl_init')) {
            throw new UpdateException('PHP-Erweiterung curl fehlt.');
        }
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; ++$hop) {
            if (!$this->isAllowed($url)) {
                throw new UpdateException('Adresse nicht erlaubt (nur https und freigegebene Hosts).');
            }
            $loc = null;
            $got = 0;
            $ch = curl_init($url);
            $h = [];
            foreach ($headers as $k => $v) {
                $h[] = $k . ': ' . $v;
            }
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $h,
                CURLOPT_USERAGENT => $this->userAgent,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$loc): int {
                    if (stripos($line, 'location:') === 0) {
                        $loc = trim(substr($line, 9));
                    }

                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => static function ($c, string $chunk) use (&$got, $maxBytes, $sink): int {
                    $got += strlen($chunk);
                    if ($got > $maxBytes) {
                        return -1;
                    }
                    $sink($chunk);

                    return strlen($chunk);
                },
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $ok = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $errno = curl_errno($ch);
            if ($status >= 300 && $status < 400 && $loc !== null) {
                // Token nie zu einem anderen Host mitschicken
                $next = $this->absolute($url, $loc);
                if (parse_url($next, PHP_URL_HOST) !== parse_url($url, PHP_URL_HOST)) {
                    unset($headers['Authorization']);
                }
                $url = $next;
                continue;
            }
            if ($ok === false && $errno !== 0) {
                throw new UpdateException($errno === CURLE_WRITE_ERROR ? 'Antwort zu groß.' : 'Verbindung fehlgeschlagen (Code ' . $errno . ').');
            }

            return $status;
        }

        throw new UpdateException('Zu viele Weiterleitungen.');
    }

    private function absolute(string $base, string $loc): string
    {
        if (preg_match('#^https?://#i', $loc) === 1) {
            return $loc;
        }
        $p = parse_url($base);

        return 'https://' . ($p['host'] ?? '') . '/' . ltrim($loc, '/');
    }
}
