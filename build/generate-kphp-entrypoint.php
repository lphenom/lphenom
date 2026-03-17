<?php
/**
 * Generate the KPHP entrypoint file.
 *
 * Standalone script for CI/Docker builds.
 * Runs inside PHP 8.1 container (entrypoint-gen stage in Dockerfile.check).
 *
 * Scans all lphenom packages + database/migrations/ directory,
 * resolves dependencies, and generates static require_once list.
 *
 * Usage:
 *   php build/generate-kphp-entrypoint.php
 *   php build/generate-kphp-entrypoint.php --migrations-dir=/path/to/migrations
 *
 * Output: build/kphp-entrypoint.generated.php
 */

declare(strict_types=1);

$basePath = dirname(__DIR__);

require_once $basePath . '/vendor/autoload.php';

use LPhenom\LPhenom\Build\KphpEntrypointGenerator;
use LPhenom\LPhenom\Build\BuildAnnotationScanner;
use LPhenom\LPhenom\Build\DependencyResolver;
use LPhenom\LPhenom\Build\MigrationFileScanner;

$migrationsDir = $basePath . '/database/migrations';

// Parse --migrations-dir argument
foreach ($argv as $arg) {
    if (strpos($arg, '--migrations-dir=') === 0) {
        $migrationsDir = substr($arg, 17);
    }
}

$outputPath = $basePath . '/build/kphp-entrypoint.generated.php';

echo 'Generating KPHP entrypoint...' . PHP_EOL;

$generator = new KphpEntrypointGenerator(
    $basePath,
    BuildAnnotationScanner::createDefault($basePath),
    DependencyResolver::createFromComposer($basePath),
    new MigrationFileScanner($migrationsDir)
);
$fileCount = $generator->generate($outputPath);

echo 'Generated: ' . $outputPath . PHP_EOL;
echo 'Files included: ' . $fileCount . PHP_EOL;

if (is_dir($migrationsDir)) {
    $migrationScanner = new MigrationFileScanner($migrationsDir);
    $migrationCount = count($migrationScanner->scan());
    echo 'Migration files: ' . $migrationCount . PHP_EOL;
} else {
    echo 'Migration dir not found: ' . $migrationsDir . ' (skipped)' . PHP_EOL;
}
