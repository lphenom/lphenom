#!/usr/bin/env php
<?php

/**
 * PHAR smoke-test: require the built PHAR and verify autoloading works.
 *
 * Usage: php build/smoke-test-phar.php [/path/to/lphenom.phar]
 */

declare(strict_types=1);

$pharFile = $argv[1] ?? dirname(__DIR__) . '/build/lphenom.phar';

if (!file_exists($pharFile)) {
    fwrite(STDERR, "PHAR not found: {$pharFile}" . PHP_EOL);
    exit(1);
}

require $pharFile;

// Test Application creation
$config = new \LPhenom\Core\Config\Config([
    'app' => ['name' => 'lphenom-test']
]);

$container = new \LPhenom\Core\Container\Container();
$app = new \LPhenom\Lphenom\Application($container, $config, '/tmp');
assert($app->getConfig()->getString('app.name') === 'lphenom-test', 'Config failed');
echo 'smoke-test: application ok' . PHP_EOL;

// Test that ServiceProviderInterface exists
assert(interface_exists(\LPhenom\Lphenom\ServiceProviderInterface::class), 'ServiceProviderInterface not loaded');
echo 'smoke-test: provider interface ok' . PHP_EOL;

echo '=== PHAR smoke-test: OK ===' . PHP_EOL;

