<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Console\Command;

use LPhenom\LPhenom\Application;
use LPhenom\LPhenom\Build\BuildAnnotationScanner;
use LPhenom\LPhenom\Build\DependencyResolver;
use LPhenom\LPhenom\Console\CommandInterface;

/**
 * Display the build manifest — shows all files, their @lphenom-build annotations,
 * and which targets (shared/kphp) they are included in.
 *
 * Build-time tool only — not included in KPHP or PHAR binaries.
 *
 * @lphenom-build none
 *
 * Usage:
 *   lphenom build:manifest
 *   lphenom build:manifest --target=kphp
 *   lphenom build:manifest --target=shared
 */
final class BuildManifestCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'build:manifest';
    }

    public function getDescription(): string
    {
        return 'Show build manifest — files and their @lphenom-build annotations';
    }

    public function execute(Application $app, array $args): int
    {
        $basePath   = $app->getBasePath();
        $targetFilter = '';

        foreach ($args as $arg) {
            if (substr($arg, 0, 9) === '--target=') {
                $targetFilter = substr($arg, 9);
            }
        }

        $scanner  = BuildAnnotationScanner::createDefault($basePath);
        $resolver = DependencyResolver::createFromComposer($basePath);
        $all      = $scanner->scan();

        // Sort by filename for stable output
        ksort($all);

        if ($targetFilter !== '') {
            echo 'Build manifest — target: ' . $targetFilter . PHP_EOL;
            echo str_repeat('=', 60) . PHP_EOL;
            echo '' . PHP_EOL;

            $files = $scanner->scanForTarget($targetFilter);
            $sorted = $resolver->topologicalSort($files);

            echo 'Files (' . count($sorted) . ') in dependency order:' . PHP_EOL;
            echo '' . PHP_EOL;

            $i = 1;
            foreach ($sorted as $file) {
                $relative = $this->relativePath($file, $basePath);
                $tag = $this->formatTargets($all[$file] ?? ['all']);
                echo sprintf('  %3d. %-70s %s', $i, $relative, $tag) . PHP_EOL;
                $i++;
            }

            echo '' . PHP_EOL;

            // Show excluded files
            $excluded = [];
            foreach ($all as $file => $targets) {
                if (!$scanner->matchesTarget($targets, $targetFilter)) {
                    $excluded[] = $file;
                }
            }

            if (count($excluded) > 0) {
                echo 'Excluded from ' . $targetFilter . ' (' . count($excluded) . '):' . PHP_EOL;
                echo '' . PHP_EOL;
                foreach ($excluded as $file) {
                    $relative = $this->relativePath($file, $basePath);
                    $tag = $this->formatTargets($all[$file] ?? []);
                    echo sprintf('  ✗  %-70s %s', $relative, $tag) . PHP_EOL;
                }
                echo '' . PHP_EOL;
            }
        } else {
            echo 'Build manifest — all files' . PHP_EOL;
            echo str_repeat('=', 60) . PHP_EOL;
            echo '' . PHP_EOL;

            $stats = ['all' => 0, 'shared,kphp' => 0, 'shared' => 0, 'kphp' => 0, 'none' => 0];

            foreach ($all as $file => $targets) {
                $relative = $this->relativePath($file, $basePath);
                $tag = $this->formatTargets($targets);
                $key = $this->statsKey($targets);
                $stats[$key] = ($stats[$key] ?? 0) + 1;

                $inShared = $scanner->matchesTarget($targets, 'shared') ? '✓' : '✗';
                $inKphp   = $scanner->matchesTarget($targets, 'kphp') ? '✓' : '✗';

                echo sprintf('  [S:%s K:%s] %-65s %s', $inShared, $inKphp, $relative, $tag) . PHP_EOL;
            }

            echo '' . PHP_EOL;
            echo 'Summary:' . PHP_EOL;
            echo '  Total files:     ' . count($all) . PHP_EOL;
            foreach ($stats as $key => $count) {
                if ($count > 0) {
                    echo '  @lphenom-build ' . str_pad($key, 12) . ': ' . $count . PHP_EOL;
                }
            }
            echo '' . PHP_EOL;
        }

        return 0;
    }

    /**
     * @param string[] $targets
     */
    private function formatTargets(array $targets): string
    {
        if (count($targets) === 0) {
            return '@lphenom-build none';
        }
        if (count($targets) === 1 && $targets[0] === 'all') {
            return '(no annotation — builds everywhere)';
        }
        return '@lphenom-build ' . implode(',', $targets);
    }

    /**
     * @param string[] $targets
     */
    private function statsKey(array $targets): string
    {
        if (count($targets) === 0) {
            return 'none';
        }
        if (count($targets) === 1 && $targets[0] === 'all') {
            return 'all';
        }
        $sorted = $targets;
        sort($sorted);
        return implode(',', $sorted);
    }

    private function relativePath(string $file, string $basePath): string
    {
        if (strpos($file, $basePath) === 0) {
            return substr($file, strlen($basePath) + 1);
        }
        return $file;
    }
}
