<?php

declare(strict_types=1);

use LPhenom\Auth\Migrations\CreateAuthTokensTable;
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Migration\MigrationInterface;

/**
 * Migration: create auth_tokens table.
 *
 * Delegates to lphenom/auth package migration.
 *
 * Schema:
 *   - token_id    VARCHAR(64)  — opaque token identifier
 *   - user_id     VARCHAR(255) — owning user
 *   - token_hash  VARCHAR(64)  — SHA-256 hash of the token secret
 *   - created_at  DATETIME
 *   - expires_at  DATETIME
 *   - revoked_at  DATETIME     — NULL until revoked
 *   - meta_json   TEXT         — optional metadata
 */
final class Migration20260317000001CreateAuthTokensTable implements MigrationInterface
{
    /** @var CreateAuthTokensTable */
    private CreateAuthTokensTable $inner;

    public function __construct()
    {
        $this->inner = new CreateAuthTokensTable();
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

