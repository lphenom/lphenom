<?php

declare(strict_types=1);

/**
 * Application configuration.
 *
 * @return array<string, mixed>
 */
return [
    'name'  => $_ENV['APP_NAME'] ?? 'LPhenom App',
    'env'   => $_ENV['APP_ENV'] ?? 'production',
    'debug' => (bool) ($_ENV['APP_DEBUG'] ?? false),
];
