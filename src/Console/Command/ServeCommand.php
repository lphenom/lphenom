<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Console\Command;

use LPhenom\LPhenom\Application;
use LPhenom\LPhenom\Console\CommandInterface;

/**
 * Start the PHP built-in development server.
 *
 * Usage:
 *   lphenom serve               # default 0.0.0.0:8080
 *   lphenom serve --port=9000   # custom port
 */
final class ServeCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'serve';
    }

    public function getDescription(): string
    {
        return 'Start the PHP built-in development server';
    }

    public function execute(Application $app, array $args): int
    {
        $port = 8080;
        $host = '0.0.0.0';

        foreach ($args as $arg) {
            if (substr($arg, 0, 7) === '--port=') {
                $port = (int) substr($arg, 7);
            }
            if (substr($arg, 0, 7) === '--host=') {
                $host = substr($arg, 7);
            }
        }

        $publicDir = $app->path('public');
        if (!is_dir($publicDir)) {
            $publicDir = $app->getBasePath();
        }

        echo 'LPhenom development server started on ' . $host . ':' . $port . PHP_EOL;
        echo 'Document root: ' . $publicDir . PHP_EOL;
        echo 'Press Ctrl+C to stop.' . PHP_EOL;

        $cmd = sprintf(
            'php -S %s:%d -t %s',
            $host,
            $port,
            escapeshellarg($publicDir)
        );

        /** @var int $result */
        $result = 0;
        passthru($cmd, $result);

        return $result;
    }
}
