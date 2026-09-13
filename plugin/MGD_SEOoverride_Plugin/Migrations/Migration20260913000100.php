<?php declare(strict_types=1);

namespace Plugin\MGD_SEOoverride_Plugin\Migrations;

use JTL\Plugin\Migration;
use JTL\Update\IMigration;

final class Migration20260913000100 extends Migration implements IMigration
{
    public function up(): void
    {
        $db = $this->getDB();
        $db->getAffectedRows(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `xplugin_mgd_seo_history` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `kind` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `strategy` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NULL,
                `url_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `url` VARCHAR(2048) NOT NULL,
                `payload` MEDIUMTEXT NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_mgd_seo_history_kind_created` (`kind`, `created_at`),
                KEY `idx_mgd_seo_history_url_hash` (`url_hash`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        $db->getAffectedRows(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `xplugin_mgd_seo_notfound` (
                `path_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                `path` VARCHAR(2048) NOT NULL,
                `referrer_path` VARCHAR(2048) NULL,
                `hits` INT UNSIGNED NOT NULL DEFAULT 1,
                `first_seen` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_seen` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`path_hash`),
                KEY `idx_mgd_seo_notfound_last_seen` (`last_seen`),
                KEY `idx_mgd_seo_notfound_hits` (`hits`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
    }

    public function down(): void
    {
        $db = $this->getDB();
        $db->getAffectedRows('DROP TABLE IF EXISTS `xplugin_mgd_seo_notfound`');
        $db->getAffectedRows('DROP TABLE IF EXISTS `xplugin_mgd_seo_history`');
    }
}
