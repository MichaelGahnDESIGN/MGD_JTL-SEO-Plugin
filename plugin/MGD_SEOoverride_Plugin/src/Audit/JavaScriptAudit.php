<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Audit;

use Plugin\MGD_SEOoverride_Plugin\Src\Http\SafeHttpClient;
use Plugin\MGD_SEOoverride_Plugin\Src\Http\UrlGuard;

final class JavaScriptAudit
{
    private const RISKY_PATTERNS = [
        'jquery', 'jtl', 'bootstrap', 'consent', 'cookie', 'checkout', 'basket', 'warenkorb',
        'paypal', 'payment', 'klarna', 'amazon-pay', 'recaptcha', 'captcha', 'opc', 'slick',
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
            if (!$sameHost) {
                $host = strtolower((string)parse_url($src, PHP_URL_HOST));
                if ($host !== '') {
                    $thirdParty[$host] = true;
                }
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
                    $candidates[] = ['pattern' => $basename, 'src' => $src, 'reason' => 'Blockierendes First-Party-Skript ohne erkannten JTL-/Checkout-Risikobegriff. Vor Aktivierung vollständig testen.'];
                }
            }
            $rows[] = [
                'src' => $src,
                'same_host' => $sameHost,
                'async' => $async,
                'defer' => $defer,
                'module' => $module,
                'blocking' => $isBlocking,
                'bytes' => $bytes,
                'cache_control' => $cacheControl,
                'status' => $status,
                'risk' => $riskReason,
                'candidate' => $candidate,
            ];
        }

        if ($blocking > 0) {
            $issues[] = $this->issue('warning', 'Synchron geladene Skripte', $blocking . ' externe script-Element(e) besitzen weder async noch defer und sind keine Module. Ob sie tatsächlich renderblockierend sind, hängt auch von ihrer Position im Dokument ab.');
        }
        if ($totalKnownBytes > 500_000) {
            $issues[] = $this->issue('warning', 'Großes First-Party-JavaScript', 'Die bekannten Größen der geprüften lokalen JavaScript-Dateien summieren sich auf rund ' . number_format($totalKnownBytes / 1024, 0, ',', '.') . ' KB.');
        }
        if (count($thirdParty) > 3) {
            $issues[] = $this->issue('warning', 'Mehrere JavaScript-Drittanbieter', count($thirdParty) . ' externe Script-Domains wurden erkannt. Drittanbieter können Main-Thread-Arbeit, INP und Datenschutz beeinflussen.');
        }
        if ($candidates !== []) {
            $issues[] = $this->issue('info', 'defer-Prüfkandidaten', count($candidates) . ' lokale blockierende Skripte wurden als mögliche Testkandidaten erkannt. Das Plugin aktiviert defer bewusst nicht automatisch.');
        }

        return [
            'rows' => $rows,
            'issues' => $issues,
            'blocking_count' => $blocking,
            'third_party_hosts' => array_keys($thirdParty),
            'known_bytes' => $totalKnownBytes,
            'defer_candidates' => $candidates,
        ];
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
