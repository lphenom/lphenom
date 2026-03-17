<?php

declare(strict_types=1);

use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Migration\MigrationInterface;

/**
 * Migration: create the jobs table for the queue system.
 *
 * Schema matches lphenom/queue DbSchema::createTable() output.
 *
 *   - id            VARCHAR(36)   — UUID v4 job identifier
 *   - name          VARCHAR(255)  — job type name
 *   - payload_json  TEXT          — serialized job payload
 *   - attempts      INT           — number of attempts made so far
 *   - available_at  INT           — Unix timestamp: when the job becomes available
 *   - reserved_at   INT           — Unix timestamp: when the job was reserved (NULL = not reserved)
 */
final class Migration20260313000002CreateJobsTable implements MigrationInterface
{
    public function up(ConnectionInterface $conn): void
    {
        $conn->execute(
            'CREATE TABLE IF NOT EXISTS `jobs` ('
            . ' `id`           VARCHAR(36)  NOT NULL,'
            . ' `name`         VARCHAR(255) NOT NULL,'
            . ' `payload_json` TEXT         NOT NULL,'
            . ' `attempts`     INT          NOT NULL DEFAULT 0,'
            . ' `available_at` INT          NOT NULL,'
            . ' `reserved_at`  INT          DEFAULT NULL,'
            . ' PRIMARY KEY (`id`),'
            . ' INDEX `idx_queue_available` (`available_at`, `reserved_at`)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            []
        );
    }

    public function down(ConnectionInterface $conn): void
    {
        $conn->execute('DROP TABLE IF EXISTS `jobs`', []);
    }

    public function getVersion(): string
    {
        return '20260313000002';
    }
}

