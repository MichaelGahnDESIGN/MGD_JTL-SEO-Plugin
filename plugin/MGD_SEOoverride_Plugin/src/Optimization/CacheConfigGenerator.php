<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Optimization;

final class CacheConfigGenerator
{
    public static function apacheSnippet(): string
    {
        return <<<'HTACCESS'
# MGD JTL SEO & PageSpeed – Vorschlag für versionierte statische Assets
# Vor produktiver Nutzung Backup der .htaccess anlegen und Hosting-Kompatibilität prüfen.

<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType text/javascript "access plus 1 year"
    ExpiresByType image/avif "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType font/woff2 "access plus 1 year"
</IfModule>

<IfModule mod_headers.c>
    <FilesMatch "\.(?:css|js|mjs|avif|webp|jpe?g|png|svg|woff2)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>
</IfModule>
HTACCESS;
    }

    /** @return list<string> */
    public static function notes(): array
    {
        return [
            'Der Vorschlag verändert keine Serverdateien automatisch.',
            'Nur für versionierte Assets verwenden. JTL ergänzt vielen gebündelten Assets bereits Versionsparameter beziehungsweise Hashes.',
            'Drittanbieter wie Google Tag Manager oder Smarketer können vom eigenen Shop aus nicht mit längeren Cache-Headern versehen werden.',
            'Bei CDN, Reverse Proxy oder abweichender Apache-Konfiguration müssen die Regeln angepasst werden.',
        ];
    }
}
