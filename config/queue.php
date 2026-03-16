<?php

declare(strict_types=1);

/**
 * Queue configuration.
 *
 * @return array<string, mixed>
 */
return [
    'driver'       => $_ENV['QUEUE_DRIVER'] ?? 'database',
    'table'        => $_ENV['QUEUE_TABLE'] ?? 'jobs',
    'redis_key'    => $_ENV['QUEUE_REDIS_KEY'] ?? 'queue:jobs',
    'max_attempts' => (int) ($_ENV['QUEUE_MAX_ATTEMPTS'] ?? 3),
    'retry_delay'  => (int) ($_ENV['QUEUE_RETRY_DELAY'] ?? 1),
];
