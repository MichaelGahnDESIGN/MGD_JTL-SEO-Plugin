<?php

declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Kit\Install;

// HTTP für den Install-Client. Implementierung: nur https, nur unser Host, kurze Zeitgrenzen
interface Transport
{
    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers): Response;
}
