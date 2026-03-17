<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Build;

/**
 * Scans a directory for migration PHP files.
 *
 * Used by KphpEntrypointGenerator to include user migration files
 * as static require_once statements in the KPHP entrypoint.
 *
 * KPHP cannot use MigrationLoader's dynamic require_once (scandir + concat path).
 * Instead, this scanner finds all migration files at build-time and produces
 * static paths that the entrypoint generator emits as require_once literals.
 *
 * Build-time tool only — not included in KPHP or PHAR binaries.
 *
 * @lphenom-build none
 */
final class MigrationFileScanner
{
    /** @var string */
    private string $migrationsDir;

    /**
     * @param string $migrationsDir Absolute path to migrations directory
     */
    public function __construct(string $migrationsDir)
    {
        $this->migrationsDir = $migrationsDir;
    }

    /**
     * Scan the migrations directory and return sorted list of PHP file paths.
     *
     * Files are sorted alphabetically (by filename) to ensure deterministic
     * order — migration files are typically named with a timestamp prefix
     * (e.g. 20260101000001_create_users.php).
     *
     * @return string[] Absolute paths to migration PHP files (sorted by name)
     */
    public function scan(): array
    {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }

        $files = scandir($this->migrationsDir);
        if ($files === false) {
            return [];
        }

        /** @var string[] $result */
        $result = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (substr($file, -4) !== '.php') {
                continue;
            }

            $fullPath = $this->migrationsDir . '/' . $file;
            $real = realpath($fullPath);
            $result[] = $real !== false ? $real : $fullPath;
        }

        return $result;
    }

    /**
     * Check if the migrations directory exists and contains PHP files.
     */
    public function hasMigrations(): bool
    {
        return count($this->scan()) > 0;
    }

    /**
     * Get the migrations directory path.
     */
    public function getMigrationsDir(): string
    {
        return $this->migrationsDir;
    }
}
