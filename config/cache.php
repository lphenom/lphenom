<?php

declare(strict_types=1);

/**
 * Cache configuration.
 *
 * @return array<string, mixed>
 */
return [
    'driver' => $_ENV['CACHE_DRIVER'] ?? 'file',
    'path'   => $_ENV['CACHE_PATH'] ?? '/tmp/lphenom-cache',
];
