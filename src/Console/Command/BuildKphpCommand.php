<?php

declare(strict_types=1);

namespace LPhenom\Lphenom\Console\Command;

use LPhenom\Lphenom\Application;
use LPhenom\Lphenom\Console\CommandInterface;

/**
 * Build a KPHP binary.
 *
 * Usage:
 *   lphenom build:kphp
 *   lphenom build:kphp --entrypoint=build/kphp-entrypoint.php
 */
final class BuildKphpCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'build:kphp';
    }

    public function getDescription(): string
    {
        return 'Build a KPHP compiled binary';
    }

    public function execute(Application $app, array $args): int
    {
        $basePath   = $app->getBasePath();
        $entrypoint = $basePath . '/build/kphp-entrypoint.php';
        $outputDir  = $basePath . '/build/kphp-out';

        foreach ($args as $arg) {
            if (substr($arg, 0, 14) === '--entrypoint=') {
                $entrypoint = substr($arg, 14);
            }
            if (substr($arg, 0, 9) === '--output=') {
                $outputDir = substr($arg, 9);
            }
        }

        if (!is_file($entrypoint)) {
            echo 'KPHP entrypoint not found: ' . $entrypoint . PHP_EOL;
            echo 'Create build/kphp-entrypoint.php first.' . PHP_EOL;
            return 1;
        }

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
