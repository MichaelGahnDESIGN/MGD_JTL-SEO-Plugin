<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Audit;

use Plugin\MGD_SEOoverride_Plugin\Src\Http\SafeHttpClient;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\UrlGuard;

final class TechnicalSeoAudit
{
    public function __construct(
        private readonly SafeHttpClient $http,
        private readonly HtmlAnalyzer $analyzer,
    ) {}

    /** @return array<string,mixed> */
    public function audit(string $url): array
    {
        $normalized = UrlGuard::normalize($url);
        if ($normalized === null || !UrlGuard::isSameShopHost($normalized)) {
            throw new \RuntimeException('Die Prüf-URL muss zur eigenen Shop-Domain gehören.');
        }
        $response = $this->http->get($normalized, true, 12);
        $page = $this->analyzer->analyze($response['body'], $response['url']);
        $issues = $this->issues($response, $page);
        return [
            'requested_url' => $normalized,
            'final_url' => $response['url'],
            'status' => $response['status'],
            'headers' => $response['headers'],
            'redirects' => $response['redirects'],
            'ttfb_ms' => (int)round($response['ttfb'] * 1000),
            'total_ms' => (int)round($response['total_time'] * 1000),
            'http_version' => $response['http_version'],
            'page' => $page,
            'issues' => $issues,
            'score' => $this->score($issues),
        ];
    }

    /** @return array<string,mixed> */
    public function auditInfrastructure(): array
    {
        $origin = UrlGuard::shopOrigin();
        $issues = [];
        $robots = ['status' => 0, 'body' => '', 'sitemaps' => []];
        try {
            $response = $this->http->get($origin . '/robots.txt', true, 8, SafeHttpClient::MAX_TEXT_BYTES, 2);
            $robots['status'] = $response['status'];
            $robots['body'] = $response['body'];
            if ($response['status'] !== 200) {
                $issues[] = $this->issue('warning', 'robots.txt', 'robots.txt ist nicht mit HTTP 200 erreichbar.');
            } else {
                if (preg_match_all('/^\s*Sitemap\s*:\s*(\S+)\s*$/mi', $response['body'], $matches)) {
                    $robots['sitemaps'] = array_values(array_unique($matches[1] ?? []));
                }
                if (preg_match('/^\s*Disallow\s*:\s*\/\s*$/mi', $response['body']) === 1) {
                    $issues[] = $this->issue('error', 'robots.txt blockiert Root', 'Eine Disallow: / Regel kann den gesamten Shop für Crawler sperren.');
                }
            }
        } catch (\Throwable $e) {
            $issues[] = $this->issue('warning', 'robots.txt', 'robots.txt konnte nicht geprüft werden: ' . $e->getMessage());
        }

        $sitemapReader = new SitemapReader($this->http);
        $sitemap = $sitemapReader->read(2500);
        if (!$sitemap['found']) {
            $issues[] = $this->issue('warning', 'Sitemap', 'Keine Standard-Sitemap gefunden. JTL kann sitemap_index.xml erzeugen.');
        }
        foreach ($sitemap['errors'] as $error) {
            $issues[] = $this->issue('warning', 'Sitemap', $error);
        }
        if ($sitemap['found'] && count($sitemap['urls']) === 0) {
            $issues[] = $this->issue('warning', 'Sitemap ohne URLs', 'Die Sitemap wurde gefunden, enthält in der geprüften Auswahl aber keine Seiten-URLs.');
        }
        return [
            'robots' => $robots,
            'sitemap' => $sitemap,
            'issues' => $issues,
            'score' => $this->score($issues),
        ];
    }

    /** @param array<string,mixed> $response @param array<string,mixed> $page @return list<array{severity:string,title:string,message:string}> */
    private function issues(array $response, array $page): array
    {
        $issues = [];
        $status = (int)$response['status'];
        if ($status >= 400) {
            $issues[] = $this->issue('error', 'HTTP-Status', 'Die Seite liefert HTTP ' . $status . '.');
        } elseif ($status !== 200) {
            $issues[] = $this->issue('warning', 'HTTP-Status', 'Die finale Seite liefert HTTP ' . $status . ' statt 200.');
        }
        if (($response['redirects'] ?? []) !== []) {
            $count = count($response['redirects']);
            $issues[] = $this->issue($count > 1 ? 'warning' : 'info', 'Weiterleitung', $count . ' Weiterleitungsstufe(n) vor der finalen URL.');
        }

        $title = trim((string)($page['title'] ?? ''));
        $titles = $page['titles'] ?? [];
        if ($title === '') {
            $issues[] = $this->issue('error', 'Seitentitel fehlt', 'Es wurde kein <title> gefunden.');
        } else {
            $length = mb_strlen($title);
            if ($length < 15) {
                $issues[] = $this->issue('warning', 'Sehr kurzer Seitentitel', 'Der Titel hat nur ' . $length . ' Zeichen.');
            } elseif ($length > 70) {
                $issues[] = $this->issue('warning', 'Langer Seitentitel', 'Der Titel hat ' . $length . ' Zeichen und kann in Suchergebnissen gekürzt werden.');
            }
        }
        if (is_array($titles) && count($titles) > 1) {
            $issues[] = $this->issue('warning', 'Mehrere title-Elemente', 'Im HTML wurden ' . count($titles) . ' title-Elemente gefunden.');
        }

        $description = trim((string)($page['description'] ?? ''));
        if ($description === '') {
            $issues[] = $this->issue('warning', 'Meta-Description fehlt', 'Für diese Seite ist keine Meta-Description im HTML vorhanden.');
        } elseif (mb_strlen($description) > 180) {
            $issues[] = $this->issue('warning', 'Lange Meta-Description', 'Die Description hat ' . mb_strlen($description) . ' Zeichen und kann gekürzt dargestellt werden.');
        }
        if (count($page['descriptions'] ?? []) > 1) {
            $issues[] = $this->issue('warning', 'Mehrere Meta-Descriptions', 'Mehrere description-Meta-Tags können uneindeutig sein.');
        }

        $robots = strtolower((string)($page['robots'] ?? ''));
        $noindex = str_contains($robots, 'noindex');
        if ($robots === '') {
            $issues[] = $this->issue('info', 'Robots-Meta fehlt', 'Ohne robots-Meta gilt grundsätzlich Indexierung als erlaubt; ein explizites Tag kann die Absicht klarer machen.');
        } elseif ($noindex) {
            $issues[] = $this->issue('info', 'noindex aktiv', 'Diese Seite bittet Suchmaschinen ausdrücklich, sie nicht zu indexieren.');
        }

        $canonical = trim((string)($page['canonical'] ?? ''));
        if (!$noindex && $canonical === '') {
            $issues[] = $this->issue('warning', 'Canonical fehlt', 'Für eine indexierbare Seite wurde kein Canonical-Link gefunden.');
        }
        if (count($page['canonicals'] ?? []) > 1) {
            $issues[] = $this->issue('error', 'Mehrere Canonicals', 'Es wurden mehrere canonical-Link-Elemente gefunden.');
        }
        if ($canonical !== '') {
            if (!UrlGuard::isSameShopHost($canonical)) {
                $issues[] = $this->issue('warning', 'Canonical auf fremde Domain', 'Der Canonical verweist auf eine andere Domain: ' . $canonical);
            } elseif (UrlGuard::withoutQuery($canonical) !== UrlGuard::withoutQuery((string)$response['url'])) {
                $issues[] = $this->issue('info', 'Canonical verweist auf andere URL', 'Das kann bei Varianten beabsichtigt sein. Ziel: ' . $canonical);
            }
        }

        $h1 = $page['h1'] ?? [];
        if (!is_array($h1) || $h1 === []) {
            $issues[] = $this->issue('warning', 'H1 fehlt', 'Auf der Seite wurde keine H1-Überschrift gefunden.');
        } elseif (count($h1) > 1) {
            $issues[] = $this->issue('warning', 'Mehrere H1', 'Es wurden ' . count($h1) . ' H1-Überschriften gefunden. Das ist technisch erlaubt, sollte aber semantisch geprüft werden.');
        }
        if (trim((string)($page['html_lang'] ?? '')) === '') {
            $issues[] = $this->issue('warning', 'Sprachangabe fehlt', 'Das html-Element besitzt kein lang-Attribut.');
        }
        if ((int)($page['invalid_jsonld'] ?? 0) > 0) {
            $issues[] = $this->issue('error', 'Ungültiges JSON-LD', (int)$page['invalid_jsonld'] . ' strukturierte Datenblöcke konnten nicht als JSON gelesen werden.');
        }
        return $issues;
    }

    /** @return array{severity:string,title:string,message:string} */
    private function issue(string $severity, string $title, string $message): array
    {
        return ['severity' => $severity, 'title' => $title, 'message' => $message];
    }

    /** @param list<array{severity:string,title:string,message:string}> $issues */
    private function score(array $issues): int
    {
        $score = 100;
        foreach ($issues as $issue) {
            $score -= match ($issue['severity']) {
                'error' => 15,
                'warning' => 6,
                default => 1,
            };
        }
        return max(0, min(100, $score));
    }
}
