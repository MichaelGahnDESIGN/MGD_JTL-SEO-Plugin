<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Http;

use RuntimeException;

final class SafeHttpClient
{
    public const MAX_HTML_BYTES = 3_145_728;
    public const MAX_TEXT_BYTES = 1_048_576;

    /**
     * @return array{url:string,status:int,headers:array<string,string>,body:string,total_time:float,ttfb:float,http_version:string,redirects:list<array{from:string,to:string,status:int}>}
     */
    public function get(string $url, bool $sameShopHost = true, int $timeoutSeconds = 10, int $maxBytes = self::MAX_HTML_BYTES, int $maxRedirects = 5): array
    {
        $current = UrlGuard::normalize($url);
        if ($current === null) {
            throw new RuntimeException('Ungültige URL.');
        }
        if ($sameShopHost && !UrlGuard::isSameShopHost($current)) {
            throw new RuntimeException('Aus Sicherheitsgründen dürfen Audits nur die eigene Shop-Domain abrufen.');
        }

        $redirects = [];
        for ($i = 0; $i <= $maxRedirects; ++$i) {
            $response = $this->request($current, 'GET', $timeoutSeconds, $maxBytes);
            $location = $response['headers']['location'] ?? '';
            if ($response['status'] < 300 || $response['status'] >= 400 || $location === '') {
                $response['redirects'] = $redirects;
                return $response;
            }
            $next = UrlGuard::resolve($current, $location);
            if ($next === null || ($sameShopHost && !UrlGuard::isSameShopHost($next))) {
                throw new RuntimeException('Weiterleitung verlässt die erlaubte Shop-Domain oder enthält eine ungültige URL.');
            }
            $redirects[] = ['from' => $current, 'to' => $next, 'status' => $response['status']];
            $current = $next;
        }
        throw new RuntimeException('Zu viele Weiterleitungen.');
    }

    /**
     * @return array{url:string,status:int,headers:array<string,string>,body:string,total_time:float,ttfb:float,http_version:string,redirects:list<array{from:string,to:string,status:int}>}
     */
    public function head(string $url, bool $sameShopHost = true, int $timeoutSeconds = 6, int $maxRedirects = 3): array
    {
        $current = UrlGuard::normalize($url);
        if ($current === null || ($sameShopHost && !UrlGuard::isSameShopHost($current))) {
            throw new RuntimeException('Ungültige oder nicht erlaubte URL.');
        }
        $redirects = [];
        for ($i = 0; $i <= $maxRedirects; ++$i) {
            $response = $this->request($current, 'HEAD', $timeoutSeconds, 64_000);
            $location = $response['headers']['location'] ?? '';
            if ($response['status'] < 300 || $response['status'] >= 400 || $location === '') {
                $response['redirects'] = $redirects;
                return $response;
            }
            $next = UrlGuard::resolve($current, $location);
            if ($next === null || ($sameShopHost && !UrlGuard::isSameShopHost($next))) {
                throw new RuntimeException('Nicht erlaubte Weiterleitung.');
            }
            $redirects[] = ['from' => $current, 'to' => $next, 'status' => $response['status']];
            $current = $next;
        }
        throw new RuntimeException('Zu viele Weiterleitungen.');
    }

    /**
     * @return array{url:string,status:int,headers:array<string,string>,body:string,total_time:float,ttfb:float,http_version:string,redirects:list<array{from:string,to:string,status:int}>}
     */
    private function request(string $url, string $method, int $timeoutSeconds, int $maxBytes): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Die PHP-cURL-Erweiterung ist nicht verfügbar.');
        }
        $headers = [];
        $body = '';
        $tooLarge = false;
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('HTTP-Anfrage konnte nicht initialisiert werden.');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(4, $timeoutSeconds),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_USERAGENT => 'MGD-JTL-SEO/2.0 (+https://github.com/MichaelGahnDESIGN/MGD_JTL-SEO-Plugin)',
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOBODY => $method === 'HEAD',
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.6',
                'Accept-Language: de-DE,de;q=0.9,en;q=0.5',
                'Cache-Control: no-cache',
            ],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                $length = strlen($line);
                $line = trim($line);
                if ($line === '' || !str_contains($line, ':')) {
                    return $length;
                }
                [$name, $value] = array_map('trim', explode(':', $line, 2));
                $key = strtolower($name);
                $headers[$key] = isset($headers[$key]) ? $headers[$key] . ', ' . $value : $value;
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $effectiveUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $totalTime = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        $ttfb = (float)curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME);
        $httpVersion = $this->httpVersionLabel((int)curl_getinfo($ch, CURLINFO_HTTP_VERSION));
        curl_close($ch);

        if ($tooLarge) {
            throw new RuntimeException('Die Antwort überschreitet das Sicherheitslimit von ' . $maxBytes . ' Bytes.');
        }
        if ($ok === false && $method !== 'HEAD') {
            throw new RuntimeException('HTTP-Abruf fehlgeschlagen: ' . ($error !== '' ? $error : 'unbekannter Fehler'));
        }
        if ($status < 100) {
            throw new RuntimeException('Der Server lieferte keinen gültigen HTTP-Status.');
        }
        return [
            'url' => $effectiveUrl !== '' ? $effectiveUrl : $url,
            'status' => $status,
            'headers' => $headers,
            'body' => $body,
            'total_time' => $totalTime,
            'ttfb' => $ttfb,
            'http_version' => $httpVersion,
            'redirects' => [],
        ];
    }

    private function httpVersionLabel(int $version): string
    {
        return match ($version) {
            defined('CURL_HTTP_VERSION_3') ? CURL_HTTP_VERSION_3 : -100 => 'HTTP/3',
            CURL_HTTP_VERSION_2_0 => 'HTTP/2',
            CURL_HTTP_VERSION_1_1 => 'HTTP/1.1',
            CURL_HTTP_VERSION_1_0 => 'HTTP/1.0',
            default => 'unbekannt',
        };
    }
}
