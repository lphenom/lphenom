<?php

declare(strict_types=1);

/**
 * Storage configuration.
 *
 * @return array<string, mixed>
 */
return [
    'driver' => $_ENV['STORAGE_DRIVER'] ?? 'local',
    'root'   => $_ENV['STORAGE_ROOT'] ?? '/tmp/lphenom-storage',
];
