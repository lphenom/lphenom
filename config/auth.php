<?php

declare(strict_types=1);

/**
 * Authentication configuration.
 *
 * @return array<string, mixed>
 */
return [
    // --- Token settings ---
    'token_ttl'           => (int) ($_ENV['AUTH_TOKEN_TTL'] ?? 86400),
    'max_attempts'        => (int) ($_ENV['AUTH_MAX_ATTEMPTS'] ?? 5),
    'throttle_decay'      => (int) ($_ENV['AUTH_THROTTLE_DECAY'] ?? 60),
    'password_iterations' => (int) ($_ENV['AUTH_PASSWORD_ITERATIONS'] ?? 10000),

    // --- Drivers ---
    'token_driver'    => $_ENV['AUTH_TOKEN_DRIVER'] ?? 'database',
    'throttle_driver' => $_ENV['AUTH_THROTTLE_DRIVER'] ?? 'cache',

    // --- MirSMS integration ---
    'mirsms' => [
        'enabled'  => (bool) ($_ENV['AUTH_MIRSMS_ENABLED'] ?? false),
        'api_url'  => $_ENV['AUTH_MIRSMS_API_URL'] ?? 'https://api.mirsms.ru/message/send',
        'login'    => $_ENV['AUTH_MIRSMS_LOGIN'] ?? '',
        'password' => $_ENV['AUTH_MIRSMS_PASSWORD'] ?? '',
        'sender'   => $_ENV['AUTH_MIRSMS_SENDER'] ?? '',
    ],

    // --- SMS code settings ---
    'sms_code' => [
        'length' => (int) ($_ENV['AUTH_SMS_CODE_LENGTH'] ?? 6),
        'ttl'    => (int) ($_ENV['AUTH_SMS_CODE_TTL'] ?? 300),
    ],

    // --- UniSender email integration ---
    'unisender' => [
        'enabled'      => (bool) ($_ENV['AUTH_UNISENDER_ENABLED'] ?? false),
        'api_key'      => $_ENV['AUTH_UNISENDER_API_KEY'] ?? '',
        'sender_email' => $_ENV['AUTH_UNISENDER_SENDER_EMAIL'] ?? '',
        'sender_name'  => $_ENV['AUTH_UNISENDER_SENDER_NAME'] ?? '',
        'subject'      => $_ENV['AUTH_UNISENDER_SUBJECT'] ?? 'Код подтверждения',
        'api_url'      => $_ENV['AUTH_UNISENDER_API_URL'] ?? 'https://api.unisender.com/ru/api/sendEmail',
    ],

    // --- Email code settings ---
    'email_code' => [
        'length' => (int) ($_ENV['AUTH_EMAIL_CODE_LENGTH'] ?? 6),
        'ttl'    => (int) ($_ENV['AUTH_EMAIL_CODE_TTL'] ?? 300),
    ],
];

