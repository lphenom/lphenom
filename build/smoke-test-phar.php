#!/usr/bin/env php
<?php

/**
 * PHAR smoke-test: require the built PHAR autoloader and verify classes load.
 *
 * Usage: php build/smoke-test-phar.php [/path/to/lphenom.phar]
 */

declare(strict_types=1);

$pharFile = $argv[1] ?? dirname(__DIR__) . '/build/lphenom.phar';

if (!file_exists($pharFile)) {
    fwrite(STDERR, "PHAR not found: {$pharFile}" . PHP_EOL);
    exit(1);
}

// Load only the autoloader, not the CLI entrypoint
require 'phar://' . $pharFile . '/vendor/autoload.php';

// Test Application creation
$config = new \LPhenom\Core\Config\Config([
    'app' => ['name' => 'lphenom-test']
]);

$container = new \LPhenom\Core\Container\Container();
$app = new \LPhenom\LPhenom\Application($container, $config, '/tmp');
assert($app->getConfig()->getString('app.name') === 'lphenom-test', 'Config failed');
echo 'smoke-test: application ok' . PHP_EOL;

// Test that ServiceProviderInterface exists
assert(interface_exists(\LPhenom\LPhenom\ServiceProviderInterface::class), 'ServiceProviderInterface not loaded');
echo 'smoke-test: provider interface ok' . PHP_EOL;

echo '=== PHAR smoke-test: OK ===' . PHP_EOL;
