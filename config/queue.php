<?php

declare(strict_types=1);

/**
 * Queue configuration.
 *
 * @return array<string, mixed>
 */
return [
    'driver'       => 'database',
    'table'        => 'jobs',
    'redis_key'    => 'queue:jobs',
    'max_attempts' => 3,
    'retry_delay'  => 1,
];

