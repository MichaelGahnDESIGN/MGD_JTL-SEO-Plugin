<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Src\Storage;

use JTL\DB\DbInterface;

final class AuditHistoryRepository
{
    public function __construct(private readonly DbInterface $db) {}

    /** @param array<string,mixed> $payload */
    public function save(string $kind, string $url, array $payload, ?string $strategy = null): void
    {
        $kind = preg_match('/^[a-z0-9_-]{1,32}$/D', $kind) === 1 ? $kind : 'audit';
        $strategy = $strategy !== null && preg_match('/^[a-z0-9_-]{1,16}$/D', $strategy) === 1 ? $strategy : null;
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!is_string($encoded) || strlen($encoded) > 4_000_000) {
            return;
        }
        $this->db->getAffectedRows(
            'INSERT INTO `xplugin_mgd_seo_history` (`kind`,`strategy`,`url_hash`,`url`,`payload`) VALUES (:kind,:strategy,:url_hash,:url,:payload)',
            [
                'kind' => $kind,
                'strategy' => $strategy,
                'url_hash' => hash('sha256', $url),
                'url' => mb_substr($url, 0, 2048),
                'payload' => $encoded,
            ],
        );
        $this->prune($kind, 100);
    }

    /** @return list<array<string,mixed>> */
    public function latest(string $kind, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $rows = $this->db->getObjects(
            'SELECT `id`,`kind`,`strategy`,`url`,`payload`,`created_at` FROM `xplugin_mgd_seo_history` WHERE `kind` = :kind ORDER BY `id` DESC LIMIT ' . $limit,
            ['kind' => $kind],
        );
        $result = [];
        foreach ($rows as $row) {
            $payload = json_decode((string)($row->payload ?? ''), true);
            if (!is_array($payload)) {
                continue;
            }
            $result[] = [
                'id' => (int)($row->id ?? 0),
                'kind' => (string)($row->kind ?? ''),
                'strategy' => isset($row->strategy) ? (string)$row->strategy : null,
                'url' => (string)($row->url ?? ''),
                'created_at' => (string)($row->created_at ?? ''),
                'payload' => $payload,
            ];
        }
        return $result;
    }

    private function prune(string $kind, int $keep): void
    {
        $keep = max(20, min(500, $keep));
        try {
            $threshold = $this->db->getSingleObject(
                'SELECT `id` FROM `xplugin_mgd_seo_history` WHERE `kind` = :kind ORDER BY `id` DESC LIMIT 1 OFFSET ' . $keep,
                ['kind' => $kind],
            );
            $id = (int)($threshold->id ?? 0);
            if ($id > 0) {
                $this->db->getAffectedRows(
                    'DELETE FROM `xplugin_mgd_seo_history` WHERE `kind` = :kind AND `id` <= :id',
                    ['kind' => $kind, 'id' => $id],
                );
            }
        } catch (\Throwable) {
            // Historienbereinigung darf nie einen Auditlauf abbrechen.
        }
    }
}
