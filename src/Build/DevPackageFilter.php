<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Build;

/**
 * Reads vendor/composer/installed.php to determine which packages are
 * production dependencies and which are dev-only.
 *
 * Used by PHAR and KPHP builders to exclude dev packages (phpunit, phpstan, etc.).
 *
 * KPHP-compatible: no Reflection.
 */
final class DevPackageFilter
{
    /**
     * Install paths of dev-only packages (absolute).
     * @var array<string, bool>
     */
    private array $devPaths;

    /**
     * Install paths of production packages (absolute).
     * @var array<string, bool>
     */
    private array $prodPaths;

    /**
     * @param array<string, bool> $devPaths  dev package install paths → true
     * @param array<string, bool> $prodPaths prod package install paths → true
     */
    public function __construct(array $devPaths, array $prodPaths)
    {
        $this->devPaths  = $devPaths;
        $this->prodPaths = $prodPaths;
    }

    /**
     * Build from Composer's installed.php.
     */
    public static function createFromComposer(string $basePath): self
    {
        $installedFile = $basePath . '/vendor/composer/installed.php';

        /** @var array<string, bool> $devPaths */
        $devPaths = [];

        /** @var array<string, bool> $prodPaths */
        $prodPaths = [];

        if (!is_file($installedFile)) {
            return new self($devPaths, $prodPaths);
        }

        /** @var array{root: array<string, mixed>, versions: array<string, array<string, mixed>>} $installed */
        $installed = require $installedFile;

        foreach ($installed['versions'] as $packageName => $info) {
            // Skip root package
            if ($packageName === ($installed['root']['name'] ?? '')) {
                continue;
            }

            $installPath = $info['install_path'] ?? null;
            if ($installPath === null || !is_string($installPath)) {
                continue;
            }

            $realPath = realpath($installPath);
            if ($realPath === false) {
                continue;
            }

            $isDev = (bool) ($info['dev_requirement'] ?? false);

            if ($isDev) {
                $devPaths[$realPath] = true;
            } else {
                $prodPaths[$realPath] = true;
            }
        }

        return new self($devPaths, $prodPaths);
    }

    /**
     * Check if a file path belongs to a dev-only package.
     *
     * Files inside dev package install paths are considered dev-only.
     * Files outside any known package path are considered NOT dev (safe to include).
     */
    public function isDevFile(string $filePath): bool
    {
        foreach ($this->devPaths as $devPath => $_) {
            if (strpos($filePath, $devPath) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a file path belongs to a production package.
     */
    public function isProdFile(string $filePath): bool
    {
        foreach ($this->prodPaths as $prodPath => $_) {
            if (strpos($filePath, $prodPath) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all dev package install paths.
     *
     * @return string[]
     */
    public function getDevPaths(): array
    {
        return array_keys($this->devPaths);
    }

    /**
     * Get all production package install paths.
     *
     * @return string[]
     */
    public function getProdPaths(): array
    {
        return array_keys($this->prodPaths);
    }
}

