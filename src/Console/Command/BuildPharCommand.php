<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Console\Command;

use LPhenom\LPhenom\Application;
use LPhenom\LPhenom\Console\CommandInterface;

/**
 * Build a PHAR archive for shared hosting deployment.
 *
 * Usage:
 *   lphenom build:phar
 *   lphenom build:phar --output=myapp.phar
 */
final class BuildPharCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'build:phar';
    }

    public function getDescription(): string
    {
        return 'Build a PHAR archive for shared hosting';
    }

    public function execute(Application $app, array $args): int
    {
        $basePath = $app->getBasePath();
        $output   = $basePath . '/build/lphenom.phar';

        foreach ($args as $arg) {
            if (substr($arg, 0, 9) === '--output=') {
                $output = substr($arg, 9);
            }
        }

        $buildScript = $basePath . '/build/build-phar.php';
        if (!is_file($buildScript)) {
            echo 'Build script not found: ' . $buildScript . PHP_EOL;
            echo 'Create build/build-phar.php first.' . PHP_EOL;
            return 1;
        }

        echo 'Building PHAR...' . PHP_EOL;
        echo 'Output: ' . $output . PHP_EOL;

        /** @var int $result */
        $result = 0;
        $cmd    = sprintf(
            'LPHENOM_PHAR_OUTPUT=%s php -d phar.readonly=0 %s',
            escapeshellarg($output),
            escapeshellarg($buildScript)
        );
        passthru($cmd, $result);

        if ($result !== 0) {
            echo 'PHAR build failed.' . PHP_EOL;
            return 1;
        }

        echo 'PHAR built successfully: ' . $output . PHP_EOL;
        return 0;
    }
}
