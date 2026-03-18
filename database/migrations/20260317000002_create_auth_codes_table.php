<?php

declare(strict_types=1);

use LPhenom\Auth\Migrations\CreateAuthCodesTable;
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Migration\MigrationInterface;

/**
 * Migration: create auth_codes table for SMS/email one-time codes.
 *
 * Delegates to lphenom/auth package migration.
 *
 * Schema:
 *   - channel     VARCHAR(16)  — 'sms' or 'email'
 *   - recipient   VARCHAR(255) — phone number or email
 *   - code_hash   VARCHAR(64)  — SHA-256 hash of the code
 *   - expires_at  DATETIME
 *   - used_at     DATETIME     — NULL until consumed
 *   - created_at  DATETIME
 */
final class Migration20260317000002CreateAuthCodesTable implements MigrationInterface
{
    /** @var CreateAuthCodesTable */
    private CreateAuthCodesTable $inner;

    public function __construct()
    {
        $this->inner = new CreateAuthCodesTable();
    }

    public function up(ConnectionInterface $conn): void
    {
        $this->inner->up($conn);
    }

    public function down(ConnectionInterface $conn): void
    {
        $this->inner->down($conn);
    }

    public function getVersion(): string
    {
        return $this->inner->getVersion();
    }
}

