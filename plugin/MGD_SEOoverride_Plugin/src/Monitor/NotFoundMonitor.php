<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Monitor;

use JTL\DB\DbInterface;
use JTL\Shop;

final class NotFoundMonitor
{
    public function __construct(private readonly DbInterface $db) {}

    /** Wird im Frontend-Outputfilter aufgerufen und speichert ausschließlich 404-Pfade, niemals IPs oder Query-Parameter. */
    public function capture(): void
    {
        if (http_response_code() !== 404) {
            return;
        }
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET' && $method !== 'HEAD') {
            return;
        }
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = parse_url($requestUri, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || strlen($path) > 2048) {
            return;
        }
        if (str_starts_with($path, '/admin') || str_starts_with($path, '/includes') || str_starts_with($path, '/plugins/')) {
            return;
        }
        $referrerPath = $this->sameHostReferrerPath((string)($_SERVER['HTTP_REFERER'] ?? ''));
        try {
            $this->db->getAffectedRows(
                <<<'SQL'
                    INSERT INTO `xplugin_mgd_seo_notfound`
                        (`path_hash`, `path`, `referrer_path`, `hits`, `first_seen`, `last_seen`)
                    VALUES
                        (:path_hash, :path, :referrer_path, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                    ON DUPLICATE KEY UPDATE
                        `hits` = `hits` + 1,
                        `last_seen` = CURRENT_TIMESTAMP,
                        `referrer_path` = COALESCE(VALUES(`referrer_path`), `referrer_path`)
                    SQL,
                [
                    'path_hash' => hash('sha256', $path),
                    'path' => $path,
                    'referrer_path' => $referrerPath,
                ],
            );
        } catch (\Throwable) {
            // Monitoring darf den Shopbetrieb niemals beeinflussen.
        }
    }

    /** @return list<array{path:string,referrer_path:?string,hits:int,first_seen:string,last_seen:string}> */
    public function latest(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $rows = $this->db->getObjects(
            'SELECT `path`,`referrer_path`,`hits`,`first_seen`,`last_seen` FROM `xplugin_mgd_seo_notfound` ORDER BY `last_seen` DESC LIMIT ' . $limit,
        );
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'path' => (string)($row->path ?? ''),
                'referrer_path' => isset($row->referrer_path) ? (string)$row->referrer_path : null,
                'hits' => (int)($row->hits ?? 0),
                'first_seen' => (string)($row->first_seen ?? ''),
                'last_seen' => (string)($row->last_seen ?? ''),
            ];
        }
        return $result;
    }

    public function clear(): void
    {
        $this->db->getAffectedRows('TRUNCATE TABLE `xplugin_mgd_seo_notfound`');
    }

    private function sameHostReferrerPath(string $referrer): ?string
    {
        if ($referrer === '') {
            return null;
        }
        $host = strtolower((string)parse_url($referrer, PHP_URL_HOST));
        $shopHost = strtolower((string)parse_url(Shop::getURL(), PHP_URL_HOST));
        if ($host === '' || $host !== $shopHost) {
            return null;
        }
        $path = parse_url($referrer, PHP_URL_PATH);
        return is_string($path) && $path !== '' && strlen($path) <= 2048 ? $path : null;
    }
}
