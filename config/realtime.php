<?php

declare(strict_types=1);

/**
 * Realtime configuration.
 *
 * @return array<string, mixed>
 */
return [
    'driver'  => $_ENV['REALTIME_DRIVER'] ?? 'database',
    'enabled' => (bool) ($_ENV['REALTIME_ENABLED'] ?? true),
];
