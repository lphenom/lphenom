<?php

declare(strict_types=1);

/**
 * Logging configuration.
 *
 * @return array<string, mixed>
 */
return [
    'driver'  => $_ENV['LOG_DRIVER'] ?? 'null',
    'channel' => $_ENV['LOG_CHANNEL'] ?? 'app',
    'path'    => $_ENV['LOG_PATH'] ?? '/tmp/lphenom.log',
];
