<?php

/**
 * KPHP entrypoint for lphenom/lphenom.
 *
 * All files must be required explicitly in dependency order.
 * KPHP does not support Composer PSR-4 autoloading.
 *
 * This entrypoint is a minimal smoke test — it proves that all
 * classes compile under KPHP without errors.
 */

declare(strict_types=1);

// === lphenom/core ===
require_once __DIR__ . '/../vendor/lphenom/core/src/Exception/LPhenomException.php';
require_once __DIR__ . '/../vendor/lphenom/core/src/Config/ConfigException.php';
require_once __DIR__ . '/../vendor/lphenom/core/src/Container/ContainerException.php';
require_once __DIR__ . '/../vendor/lphenom/core/src/Container/ServiceFactoryInterface.php';
require_once __DIR__ . '/../vendor/lphenom/core/src/Utils/Arr.php';
require_once __DIR__ . '/../vendor/lphenom/core/src/Utils/Str.php';
require_once __DIR__ . '/../vendor/lphenom/core/src/Config/Config.php';
require_once __DIR__ . '/../vendor/lphenom/core/src/Container/Container.php';
require_once __DIR__ . '/../vendor/lphenom/core/src/Clock/ClockInterface.php';
require_once __DIR__ . '/../vendor/lphenom/core/src/Clock/SystemClock.php';
require_once __DIR__ . '/../vendor/lphenom/core/src/EnvLoader/EnvLoader.php';

// === lphenom/lphenom (kernel) ===
require_once __DIR__ . '/../src/Exception/KernelException.php';
require_once __DIR__ . '/../src/ServiceProviderInterface.php';
require_once __DIR__ . '/../src/Application.php';

// === Smoke test ===
$config = new \LPhenom\Core\Config\Config([
    'app' => ['name' => 'lphenom']
]);

$container = new \LPhenom\Core\Container\Container();
$app = new \LPhenom\Lphenom\Application($container, $config, '/tmp');

echo 'LPhenom kernel KPHP smoke test: OK' . PHP_EOL;

