<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Build;

/**
 * Filters files for PHAR builds based on two criteria:
 *
 * 1. Dev-package exclusion — files from require-dev packages (phpunit, phpstan, etc.)
 *    are NEVER included in the PHAR.
 *
 * 2. Annotation-based filtering (@lphenom-build) for LPhenom source files:
 *    - No annotation     → include (builds everywhere)
 *    - shared,kphp       → include (has 'shared')
 *    - shared            → include
 *    - kphp              → EXCLUDE (kphp-only, not for PHP runtime)
 *    - none              → EXCLUDE (dev tools)
 *
 * Build-time tool only — not included in KPHP or PHAR binaries.
 *
 * @lphenom-build none
 */
final class PharFileFilter
{
    /** @var BuildAnnotationScanner */
    private BuildAnnotationScanner $scanner;

    /** @var DevPackageFilter */
    private DevPackageFilter $devFilter;

    public function __construct(BuildAnnotationScanner $scanner, DevPackageFilter $devFilter)
    {
        $this->scanner   = $scanner;
        $this->devFilter = $devFilter;
    }

    /**
     * Create a filter with default configuration.
     */
    public static function createDefault(string $basePath): self
    {
        return new self(
            BuildAnnotationScanner::createDefault($basePath),
            DevPackageFilter::createFromComposer($basePath)
        );
    }

    /**
     * Check if a file should be included in the PHAR (shared hosting build).
     *
     * Excludes:
     *   - Files from dev-only Composer packages
     *   - PHP files with @lphenom-build kphp or @lphenom-build none
     */
    public function shouldInclude(string $filePath): bool
    {
        // 1. Exclude dev package files
        if ($this->devFilter->isDevFile($filePath)) {
            return false;
        }

        // 2. Non-PHP files — always include (configs, etc.)
        if (substr($filePath, -4) !== '.php') {
            return true;
        }

        // 3. Check @lphenom-build annotation for PHP files
        $targets = $this->scanner->parseAnnotation($filePath);

        return $this->scanner->matchesTarget($targets, 'shared');
    }

    /**
     * Get the dev package filter (for stats/debugging).
     */
    public function getDevFilter(): DevPackageFilter
    {
        return $this->devFilter;
    }

    /**
     * Get all files matching the 'shared' target from the scanner.
     *
     * @return string[]
     */
    public function getSharedFiles(): array
    {
        return $this->scanner->scanForTarget('shared');
    }
}
