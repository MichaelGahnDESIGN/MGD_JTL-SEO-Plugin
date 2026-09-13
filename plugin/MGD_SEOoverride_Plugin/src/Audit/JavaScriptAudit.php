<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Audit;

use Plugin\MGD_SEOoverride_Plugin\Src\Http\SafeHttpClient;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\UrlGuard;

final class JavaScriptAudit
{
    private const RISKY_PATTERNS = [
        'jquery', 'jtl', 'bootstrap', 'consent', 'cookie', 'checkout', 'basket', 'warenkorb',
        'paypal', 'payment', 'klarna', 'amazon-pay', 'stripe', 'mollie', 'recaptcha', 'captcha',
        'opc', 'slick',
    ];

    public function __construct(private readonly SafeHttpClient $http) {}

    /** @param array<string,mixed> $page @return array<string,mixed> */
    public function analyze(array $page, int $probeLimit = 40): array
    {
        $scripts = is_array($page['scripts'] ?? null) ? $page['scripts'] : [];
        $rows = [];
        $issues = [];
        $blocking = 0;
        $thirdParty = [];
        $totalKnownBytes = 0;
        $probed = 0;
        $candidates = [];
        $tracking = ['gtm' => 0, 'gtag' => 0, 'smarketer' => 0];
        $delayPatterns = [];

        foreach ($scripts as $script) {
            if (!is_array($script)) {
                continue;
            }
            $src = (string)($script['src'] ?? '');
            if ($src === '') {
                continue;
            }
            $async = ($script['async'] ?? false) === true;
            $defer = ($script['defer'] ?? false) === true;
            $module = ($script['module'] ?? false) === true;
            $isBlocking = !$async && !$defer && !$module;
            if ($isBlocking) {
                ++$blocking;
            }

            $sameHost = UrlGuard::isSameShopHost($src);
            $host = strtolower((string)parse_url($src, PHP_URL_HOST));
            if (!$sameHost && $host !== '') {
                $thirdParty[$host] = true;
            }

            $provider = $this->provider($src);
            if ($provider === 'Google Tag Manager') {
                ++$tracking['gtm'];
            } elseif ($provider === 'Google gtag') {
                ++$tracking['gtag'];
            } elseif ($provider === 'Smarketer') {
                ++$tracking['smarketer'];
            }

            $bytes = null;
            $cacheControl = '';
            $status = null;
            if ($sameHost && $probed < $probeLimit) {
                ++$probed;
                try {
                    $meta = $this->http->head($src, true, 5, 2);
                    $status = $meta['status'];
                    $length = trim((string)($meta['headers']['content-length'] ?? ''));
                    if ($length !== '' && ctype_digit($length)) {
                        $bytes = (int)$length;
                        $totalKnownBytes += $bytes;
                    }
                    $cacheControl = (string)($meta['headers']['cache-control'] ?? '');
                } catch (\Throwable) {
                    // Größenprüfung ist optional.
                }
            }

            $riskReason = $this->riskReason($src);
            $candidate = $isBlocking && $sameHost && $riskReason === '';
            if ($candidate) {
                $path = (string)parse_url($src, PHP_URL_PATH);
                $basename = basename($path);
                if ($basename !== '' && count($candidates) < 20) {
                    $candidates[] = [
                        'pattern' => $basename,
                        'src' => $src,
                        'reason' => 'Blockierendes First-Party-Skript ohne erkannten JTL-/Checkout-Risikobegriff. Vor Aktivierung vollständig testen.',
                    ];
                }
            }

            $delayCandidate = !$sameHost && $riskReason === '' && in_array($provider, ['Google Tag Manager', 'Google gtag', 'Google Analytics', 'Smarketer'], true);
            if ($delayCandidate && $host !== '') {
                $delayPatterns[$host] = true;
            }

            $rows[] = [
                'src' => $src,
                'same_host' => $sameHost,
                'host' => $host,
                'provider' => $provider,
                'async' => $async,
                'defer' => $defer,
                'module' => $module,
                'blocking' => $isBlocking,
                'bytes' => $bytes,
                'cache_control' => $cacheControl,
                'status' => $status,
                'risk' => $riskReason,
                'candidate' => $candidate,
                'delay_candidate' => $delayCandidate,
            ];
        }

        if ($blocking > 0) {
            $issues[] = $this->issue('warning', 'Synchron geladene Skripte', $blocking . ' script-Element(e) besitzen weder async noch defer und sind keine Module. Ob sie tatsächlich renderblockierend sind, hängt auch von ihrer Position im Dokument ab.');
        }
        if ($totalKnownBytes > 500_000) {
            $issues[] = $this->issue('warning', 'Großes First-Party-JavaScript', 'Die bekannten Größen der geprüften lokalen JavaScript-Dateien summieren sich auf rund ' . number_format($totalKnownBytes / 1024, 0, ',', '.') . ' KB.');
        }
        if (count($thirdParty) > 3) {
            $issues[] = $this->issue('warning', 'Mehrere JavaScript-Drittanbieter', count($thirdParty) . ' externe Script-Domains wurden erkannt. Drittanbieter können Main-Thread-Arbeit, INP und Datenschutz beeinflussen.');
        }
        if ($tracking['gtm'] > 0 && $tracking['gtag'] > 0) {
            $issues[] = $this->issue('warning', 'GTM und gtag parallel erkannt', 'Google Tag Manager und mindestens ein direktes gtag.js werden gleichzeitig geladen. Das ist nicht automatisch falsch, sollte aber auf doppelte GA4-/Ads-Konfigurationen geprüft werden.');
        }
        if ($tracking['smarketer'] > 0) {
            $issues[] = $this->issue('info', 'Smarketer erkannt', 'Smarketer-JavaScript ist Drittanbieter-Code. Dessen Cache-TTL kann der Shop nicht ändern; nach fachlicher Prüfung kann ein verzögerter Start nach Interaktion oder Browser-Idle getestet werden.');
        }
        if ($candidates !== []) {
            $issues[] = $this->issue('info', 'defer-Prüfkandidaten', count($candidates) . ' lokale blockierende Skripte wurden als mögliche Testkandidaten erkannt. Das Plugin aktiviert defer bewusst nicht automatisch.');
        }
        if ($delayPatterns !== []) {
            $issues[] = $this->issue('info', 'Drittanbieter-Verzögerung möglich', 'Für ' . count($delayPatterns) . ' Tracking-Domain(s) kann der optionale Delay-Modus getestet werden. Zahlungs-, Consent-, CAPTCHA- und Checkout-Skripte bleiben blockiert.');
        }

        return [
            'rows' => $rows,
            'issues' => $issues,
            'blocking_count' => $blocking,
            'third_party_hosts' => array_keys($thirdParty),
            'known_bytes' => $totalKnownBytes,
            'defer_candidates' => $candidates,
            'tracking' => $tracking,
            'recommended_delay_patterns' => array_keys($delayPatterns),
        ];
    }

    private function provider(string $src): string
    {
        $lower = strtolower($src);
        $host = strtolower((string)parse_url($src, PHP_URL_HOST));
        $path = strtolower((string)parse_url($src, PHP_URL_PATH));
        if (str_contains($host, 'googletagmanager.com')) {
            if (str_contains($path, '/gtm.js')) {
                return 'Google Tag Manager';
            }
            if (str_contains($path, '/gtag/js')) {
                return 'Google gtag';
            }
            return 'Google Tag Manager';
        }
        if (str_contains($host, 'google-analytics.com')) {
            return 'Google Analytics';
        }
        if (str_contains($host, 'smarketer.de') || str_contains($lower, 'smarketer')) {
            return 'Smarketer';
        }
        return $host !== '' ? $host : 'lokal';
    }

    private function riskReason(string $src): string
    {
        $lower = strtolower($src);
        foreach (self::RISKY_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return 'enthält „' . $pattern . '“';
            }
        }
        return '';
    }

    /** @return array{severity:string,title:string,message:string} */
    private function issue(string $severity, string $title, string $message): array
    {
        return ['severity' => $severity, 'title' => $title, 'message' => $message];
    }
}
