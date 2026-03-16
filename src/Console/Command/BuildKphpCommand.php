<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Console\Command;

use LPhenom\LPhenom\Application;
use LPhenom\LPhenom\Build\KphpEntrypointGenerator;
use LPhenom\LPhenom\Console\CommandInterface;

/**
 * Build a KPHP binary.
 *
 * Automatically scans all source files for build annotations,
 * generates a temporary entrypoint with correct require_once order, then compiles.
 *
 * The generated entrypoint (build/kphp-entrypoint.generated.php) is a temporary
 * build artifact and MUST NOT be committed to the repository.
 *
 * Usage:
 *   lphenom build:kphp
 *   lphenom build:kphp --output=build/kphp-out
 *   lphenom build:kphp --no-generate   (skip generation, use existing entrypoint)
 */
final class BuildKphpCommand implements CommandInterface
{
    /** Generated entrypoint filename (gitignored) */
    private const GENERATED_ENTRYPOINT = '/build/kphp-entrypoint.generated.php';

    public function getName(): string
    {
        return 'build:kphp';
    }

    public function getDescription(): string
    {
        return 'Build a KPHP compiled binary (auto-generates entrypoint from annotations)';
    }

    public function execute(Application $app, array $args): int
    {
        $basePath   = $app->getBasePath();
        $entrypoint = $basePath . self::GENERATED_ENTRYPOINT;
        $outputDir  = $basePath . '/build/kphp-out';
        $noGenerate = false;

        foreach ($args as $arg) {
            if (substr($arg, 0, 14) === '--entrypoint=') {
                $entrypoint = substr($arg, 14);
            }
            if (substr($arg, 0, 9) === '--output=') {
                $outputDir = substr($arg, 9);
            }
            if ($arg === '--no-generate') {
                $noGenerate = true;
            }
        }

        // Step 1: Auto-generate the entrypoint
        if (!$noGenerate) {
            echo 'Scanning @lphenom-build annotations...' . PHP_EOL;

            $generator = KphpEntrypointGenerator::createDefault($basePath);
            $fileCount = $generator->generate($entrypoint);

            echo 'Generated entrypoint: ' . $entrypoint . PHP_EOL;
            echo 'Files included: ' . $fileCount . PHP_EOL;
            echo '' . PHP_EOL;
        } else {
            echo 'Skipping entrypoint generation (--no-generate).' . PHP_EOL;
        }

        if (!is_file($entrypoint)) {
            echo 'KPHP entrypoint not found: ' . $entrypoint . PHP_EOL;
            echo 'Run without --no-generate to auto-create it.' . PHP_EOL;
            return 1;
        }

        // Step 2: Compile with KPHP
        echo 'Building KPHP binary...' . PHP_EOL;
        echo 'Entrypoint: ' . $entrypoint . PHP_EOL;
        echo 'Output: ' . $outputDir . PHP_EOL;

        $cmd = sprintf(
            'kphp -d %s -M cli %s',
            escapeshellarg($outputDir),
            escapeshellarg($entrypoint)
        );

        /** @var int $result */
        $result = 0;
        passthru($cmd, $result);

        if ($result !== 0) {
            echo 'KPHP build failed.' . PHP_EOL;
            return 1;
        }

        echo 'KPHP binary built successfully.' . PHP_EOL;
        return 0;
    }
}
