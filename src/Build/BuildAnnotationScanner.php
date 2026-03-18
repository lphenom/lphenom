<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Build;

/**
 * Scans PHP files for @lphenom-build annotations.
 *
 * Reads file content as plain text (no Reflection) — KPHP-compatible.
 *
 * Build-time tool only — not included in KPHP or PHAR binaries.
 *
 * Annotation rules:
 *   - No annotation found      → file builds EVERYWHERE (all targets)
 *   - @lphenom-build shared,kphp → builds for shared + kphp
 *   - @lphenom-build shared      → builds only for shared (PHAR)
 *   - @lphenom-build kphp        → builds only for kphp binary
 *   - @lphenom-build none        → never builds
 *
 * @lphenom-build none
 */
final class BuildAnnotationScanner
{
    /** @var string[] Known valid target values */
    private const VALID_TARGETS = ['shared', 'kphp', 'none'];

    /** @var string[] */
    private array $scanDirs;

    /**
     * @param string[] $scanDirs  Absolute paths to scan
     */
    public function __construct(array $scanDirs)
    {
        $this->scanDirs = $scanDirs;
    }

    /**
     * Create a scanner for the default project layout.
     *
     * Scans src/ and all vendor/lphenom/{package}/src/ directories.
     */
    public static function createDefault(string $basePath): self
    {
        $dirs = [];
        $dirs[] = $basePath . '/src';

        $vendorLphenom = $basePath . '/vendor/lphenom';
        if (is_dir($vendorLphenom)) {
            $entries = scandir($vendorLphenom);
            if ($entries !== false) {
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $srcDir = $vendorLphenom . '/' . $entry . '/src';
                    if (is_dir($srcDir)) {
                        $dirs[] = $srcDir;
                    }
                }
            }
        }

        return new self($dirs);
    }

    /**
     * Scan all directories and return build targets per file.
     *
     * @return array<string, string[]> filepath → list of targets ('shared','kphp') or ['all'] or []
     */
    public function scan(): array
    {
        /** @var array<string, string[]> $result */
        $result = [];

        foreach ($this->scanDirs as $dir) {
            $this->scanDirectory($dir, $result);
        }

        return $result;
    }

    /**
     * Scan files and return only those matching a given target.
     *
     * A file matches if:
     *   - It has no annotation (builds everywhere)
     *   - Its annotation contains the target (e.g. 'kphp' in 'shared,kphp')
     *
     * @param string $target 'kphp' or 'shared'
     * @return string[] Absolute file paths
     */
    public function scanForTarget(string $target): array
    {
        $all = $this->scan();

        /** @var string[] $matched */
        $matched = [];

        foreach ($all as $file => $targets) {
            if ($this->matchesTarget($targets, $target)) {
                $matched[] = $file;
            }
        }

        return $matched;
    }

    /**
     * Check if a file's targets include a given target.
     *
     * @param string[] $targets
     * @param string   $target
     */
    public function matchesTarget(array $targets, string $target): bool
    {
        if (count($targets) === 0) {
            // 'none' → empty array → no targets
            return false;
        }

        if (count($targets) === 1 && $targets[0] === 'all') {
            return true;
        }

        return in_array($target, $targets, true);
    }

    /**
     * Parse a lphenom-build annotation from a PHP file.
     *
     * Only annotations inside docblock comments are recognized.
     * The value must consist of valid targets: shared, kphp, none.
     * If a file contains no valid annotation → ['all'] (builds everywhere).
     *
     * @return string[] targets, e.g. ['shared','kphp'], []  (for none), or ['all'] if missing
     */
    public function parseAnnotation(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return ['all'];
        }

        // Extract all docblock comments (/** ... */) from the file.
        // We only check the first 4KB to keep it fast.
        $header = substr($content, 0, 4096);

        $matches = [];
        // Match docblock comments: /** ... */
        if (!preg_match_all('/\/\*\*.*?\*\//s', $header, $matches)) {
            return ['all'];
        }

        foreach ($matches[0] as $docblock) {
            // Look for the annotation inside this docblock.
            // Matches two formats:
            //   Multi-line:  * @lphenom-build shared,kphp
            //   Single-line: /** @lphenom-build shared,kphp */
            // Does NOT match prose: * This mentions @lphenom-build in text
            $tagMatches = [];
            if (preg_match('/^\s*(?:\/\*)?\*+\s*@lphenom-build\s+([\w, ]+)/m', $docblock, $tagMatches)) {
                $value = trim($tagMatches[1]);
                return $this->parseTargetValue($value);
            }
        }

        return ['all'];
    }

    /**
     * Parse and validate the target value string.
     *
     * @return string[] validated targets, or [] for 'none'
     */
    private function parseTargetValue(string $value): array
    {
        if ($value === 'none') {
            return [];
        }

        /** @var string[] $targets */
        $targets = [];
        $parts = explode(',', $value);
        foreach ($parts as $part) {
            $t = trim($part);
            if ($t !== '' && in_array($t, self::VALID_TARGETS, true) && $t !== 'none') {
                $targets[] = $t;
            }
        }

        return count($targets) > 0 ? $targets : ['all'];
    }

    /**
     * @param string $dir
     * @param array<string, string[]> $result
     */
    private function scanDirectory(string $dir, array &$result): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            if (substr($path, -4) !== '.php') {
                continue;
            }

            $result[$path] = $this->parseAnnotation($path);
        }
    }
}
