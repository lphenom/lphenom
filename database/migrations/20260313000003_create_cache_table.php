<?php

declare(strict_types=1);

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Migration\MigrationInterface;

/**
 * Migration: create the cache table for database-backed cache driver.
 *
 * Schema:
 *   - cache_key    VARCHAR(64)  — cache entry key (primary key)
 *   - cache_value  TEXT         — serialized cached value
 *   - expires_at   INT          — Unix timestamp expiration (0 = no expiry)
 */
final class Migration20260313000003CreateCacheTable implements MigrationInterface
{
    public function up(ConnectionInterface $conn): void
    {
        $conn->execute(
            'CREATE TABLE IF NOT EXISTS `cache` ('
            . ' `cache_key`   VARCHAR(64) NOT NULL PRIMARY KEY,'
            . ' `cache_value` TEXT        NOT NULL,'
            . ' `expires_at`  INT         NOT NULL DEFAULT 0'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            []
        );
    }

    public function down(ConnectionInterface $conn): void
    {
        $conn->execute('DROP TABLE IF EXISTS `cache`', []);
    }

    public function getVersion(): string
    {
        return '20260313000003';
    }
}

