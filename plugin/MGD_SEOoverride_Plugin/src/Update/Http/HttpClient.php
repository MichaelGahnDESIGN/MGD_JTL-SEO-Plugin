<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Update\Http;

// HTTP-Adapter. Implementierungen: nur https, nur erlaubte Hosts, keine Token in Meldungen
interface HttpClient
{
    /** @param array<string,string> $headers */
    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $maxBytes = 1048576): HttpResponse;

    /** Lädt in eine Datei (Streaming, hartes Limit). @param array<string,string> $headers */
    public function download(string $url, array $headers, string $targetFile, int $maxBytes): HttpResponse;
}
