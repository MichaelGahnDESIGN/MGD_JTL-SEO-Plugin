<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\PageSpeed;

use Plugin\MGD_SEOoverride_Plugin\Src\Http\UrlGuard;
use RuntimeException;

final class PageSpeedClient
{
    private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    private const MAX_BYTES = 8_388_608;

    /** @return array<string,mixed> */
    public function run(string $url, string $strategy, string $apiKey = ''): array
    {
        $url = UrlGuard::normalize($url) ?? '';
        if ($url === '' || !UrlGuard::isSameShopHost($url)) {
            throw new RuntimeException('PageSpeed darf nur für die eigene Shop-Domain gestartet werden.');
        }
        $strategy = strtolower($strategy) === 'desktop' ? 'desktop' : 'mobile';
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP-cURL ist nicht verfügbar.');
        }
        $query = 'url=' . rawurlencode($url)
            . '&strategy=' . rawurlencode($strategy)
            . '&category=performance&category=seo&locale=de';
        if (trim($apiKey) !== '') {
            $query .= '&key=' . rawurlencode(trim($apiKey));
        }
        $body = '';
        $tooLarge = false;
        $ch = curl_init(self::ENDPOINT . '?' . $query);
        if ($ch === false) {
            throw new RuntimeException('PageSpeed-Anfrage konnte nicht initialisiert werden.');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'MGD-JTL-SEO/2.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$tooLarge): int {
                if (strlen($body) + strlen($chunk) > self::MAX_BYTES) {
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
        curl_close($ch);
        if ($tooLarge) {
            throw new RuntimeException('Die PageSpeed-Antwort war unerwartet groß.');
        }
        if ($ok === false || $status !== 200) {
            $message = $this->apiError($body);
            if ($message === '' && $error !== '') {
                $message = $error;
            }
            if ($message === '') {
                $message = 'HTTP ' . $status;
            }
            throw new RuntimeException('PageSpeed Insights konnte nicht ausgeführt werden: ' . $message);
        }
        try {
            $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Ungültige Antwort der PageSpeed API: ' . $e->getMessage());
        }
        if (!is_array($data)) {
            throw new RuntimeException('PageSpeed lieferte keine auswertbaren Daten.');
        }
        return $this->normalize($data, $url, $strategy);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function normalize(array $data, string $url, string $strategy): array
    {
        $lighthouse = is_array($data['lighthouseResult'] ?? null) ? $data['lighthouseResult'] : [];
        $categories = is_array($lighthouse['categories'] ?? null) ? $lighthouse['categories'] : [];
        $audits = is_array($lighthouse['audits'] ?? null) ? $lighthouse['audits'] : [];
        $metricIds = [
            'first-contentful-paint' => 'fcp',
            'largest-contentful-paint' => 'lcp',
            'total-blocking-time' => 'tbt',
            'cumulative-layout-shift' => 'cls',
            'speed-index' => 'speed_index',
            'interactive' => 'tti',
        ];
        $metrics = [];
        foreach ($metricIds as $id => $key) {
            $audit = is_array($audits[$id] ?? null) ? $audits[$id] : [];
            $metrics[$key] = [
                'numeric' => isset($audit['numericValue']) && is_numeric($audit['numericValue']) ? (float)$audit['numericValue'] : null,
                'display' => is_string($audit['displayValue'] ?? null) ? $audit['displayValue'] : '',
                'score' => isset($audit['score']) && is_numeric($audit['score']) ? (float)$audit['score'] : null,
            ];
        }
        $opportunityIds = [
            'render-blocking-resources', 'unused-javascript', 'unused-css-rules',
            'modern-image-formats', 'uses-optimized-images', 'uses-responsive-images',
            'offscreen-images', 'server-response-time', 'uses-long-cache-ttl',
            'third-party-summary', 'mainthread-work-breakdown', 'bootup-time',
            'largest-contentful-paint-element', 'lcp-discovery-insight', 'image-delivery-insight',
        ];
        $opportunities = [];
        foreach ($opportunityIds as $id) {
            $audit = is_array($audits[$id] ?? null) ? $audits[$id] : null;
            if ($audit === null) {
                continue;
            }
            $score = $audit['score'] ?? null;
            if (is_numeric($score) && (float)$score >= 1.0) {
                continue;
            }
            $opportunities[] = [
                'id' => $id,
                'title' => is_string($audit['title'] ?? null) ? $audit['title'] : $id,
                'description' => is_string($audit['description'] ?? null) ? strip_tags($audit['description']) : '',
                'display' => is_string($audit['displayValue'] ?? null) ? $audit['displayValue'] : '',
                'score' => is_numeric($score) ? (float)$score : null,
                'savings_ms' => isset($audit['details']['overallSavingsMs']) && is_numeric($audit['details']['overallSavingsMs']) ? (float)$audit['details']['overallSavingsMs'] : null,
                'savings_bytes' => isset($audit['details']['overallSavingsBytes']) && is_numeric($audit['details']['overallSavingsBytes']) ? (int)$audit['details']['overallSavingsBytes'] : null,
            ];
        }
        usort($opportunities, static function (array $a, array $b): int {
            $aSavings = (float)($a['savings_ms'] ?? 0) + ((float)($a['savings_bytes'] ?? 0) / 1000);
            $bSavings = (float)($b['savings_ms'] ?? 0) + ((float)($b['savings_bytes'] ?? 0) / 1000);
            return $bSavings <=> $aSavings;
        });

        $field = [];
        $loading = is_array($data['loadingExperience']['metrics'] ?? null) ? $data['loadingExperience']['metrics'] : [];
        foreach (['LARGEST_CONTENTFUL_PAINT_MS' => 'lcp', 'INTERACTION_TO_NEXT_PAINT' => 'inp', 'CUMULATIVE_LAYOUT_SHIFT_SCORE' => 'cls', 'FIRST_CONTENTFUL_PAINT_MS' => 'fcp'] as $source => $key) {
            $metric = is_array($loading[$source] ?? null) ? $loading[$source] : [];
            if ($metric !== []) {
                $field[$key] = [
                    'percentile' => $metric['percentile'] ?? null,
                    'category' => $metric['category'] ?? '',
                ];
            }
        }

        return [
            'url' => $url,
            'strategy' => $strategy,
            'fetched_at' => date(DATE_ATOM),
            'performance_score' => $this->categoryScore($categories, 'performance'),
            'seo_score' => $this->categoryScore($categories, 'seo'),
            'metrics' => $metrics,
            'field' => $field,
            'opportunities' => array_slice($opportunities, 0, 20),
            'lighthouse_version' => is_string($lighthouse['lighthouseVersion'] ?? null) ? $lighthouse['lighthouseVersion'] : '',
            'fetch_time' => is_string($lighthouse['fetchTime'] ?? null) ? $lighthouse['fetchTime'] : '',
        ];
    }

    /** @param array<string,mixed> $categories */
    private function categoryScore(array $categories, string $key): ?int
    {
        $category = is_array($categories[$key] ?? null) ? $categories[$key] : [];
        $score = $category['score'] ?? null;
        return is_numeric($score) ? (int)round((float)$score * 100) : null;
    }

    private function apiError(string $body): string
    {
        try {
            $data = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return '';
        }
        $message = $data['error']['message'] ?? '';
        return is_string($message) ? mb_substr($message, 0, 500) : '';
    }
}
