<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Build;

use LPhenom\Db\Migration\MigrationInterface;

/**
 * Loads migration classes from a directory at runtime.
 *
 * Scans the given directory for PHP files, requires them, derives the
 * class name from the filename, and returns MigrationInterface instances.
 *
 * Naming convention:
 *   Filename:  20260313000001_create_realtime_events_table.php
 *   Class:     Migration20260313000001CreateRealtimeEventsTable
 *
 * The class must:
 *   - be in the global namespace (no `namespace` declaration)
 *   - implement LPhenom\Db\Migration\MigrationInterface
 *   - have a zero-argument constructor
 *
 * @lphenom-build none
 */
final class MigrationLoader
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
     * Scan the directory, require all PHP files, and return migration instances.
     *
     * Files are sorted alphabetically (timestamp-prefixed) to guarantee
     * deterministic order.
     *
     * @return MigrationInterface[]
     */
    public function loadAll(): array
    {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }

        $files = scandir($this->migrationsDir);
        if ($files === false) {
            return [];
        }

        /** @var MigrationInterface[] $migrations */
        $migrations = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (substr($file, -4) !== '.php') {
                continue;
            }

            $fullPath = $this->migrationsDir . '/' . $file;
            require_once $fullPath;

            $className = self::filenameToClassName($file);

            if (!class_exists($className, false)) {
                continue;
            }

            /** @var object $instance */
            $instance = new $className();

            if ($instance instanceof MigrationInterface) {
                $migrations[] = $instance;
            }
        }

        return $migrations;
    }

    /**
     * Convert a migration filename to its expected class name.
     *
     * Example:
     *   "20260313000001_create_realtime_events_table.php"
     *   → "Migration20260313000001CreateRealtimeEventsTable"
     *
     * Algorithm:
     *   1. Strip ".php" extension
     *   2. Split by "_"
     *   3. Capitalize first letter of each part
     *   4. Concatenate with "Migration" prefix
     */
    public static function filenameToClassName(string $filename): string
    {
        $base = substr($filename, 0, -4); // strip .php
        $parts = explode('_', $base);

        $className = 'Migration';
        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }

        return $className;
    }

    /**
     * Get the migrations directory path.
     */
    public function getMigrationsDir(): string
    {
        return $this->migrationsDir;
    }
}

