<?php

declare(strict_types=1);

namespace LPhenom\Lphenom;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\EnvLoader\EnvLoader;
use LPhenom\Db\Migration\MigrationInterface;
use LPhenom\Lphenom\Provider\CacheServiceProvider;
use LPhenom\Lphenom\Provider\DatabaseServiceProvider;
use LPhenom\Lphenom\Provider\HttpServiceProvider;
use LPhenom\Lphenom\Provider\LogServiceProvider;
use LPhenom\Lphenom\Provider\MigrateServiceProvider;
use LPhenom\Lphenom\Provider\QueueServiceProvider;
use LPhenom\Lphenom\Provider\RealtimeServiceProvider;
use LPhenom\Lphenom\Provider\RedisServiceProvider;
use LPhenom\Lphenom\Provider\StorageServiceProvider;

/**
 * Application factory — builds a fully wired Application.
 *
 * Provides static helpers for web and console bootstrap.
 *
 * KPHP-compatible: no reflection, no dynamic class loading.
 */
final class AppFactory
{
    private function __construct()
    {
    }

    /**
     * Create a web application with all providers.
     *
     * @param string               $basePath        Root directory of the application
     * @param Config               $config          Application configuration
     * @param MigrationInterface[] $userMigrations  Extra user-defined migrations
     * @param ServiceProviderInterface[] $extraProviders Extra custom providers
     */
    public static function create(
        string $basePath,
        Config $config,
        array $userMigrations = [],
        array $extraProviders = []
    ): Application {
        $container = new Container();
        $app       = new Application($container, $config, $basePath);

        // Register core providers in dependency order
        $app->addProvider(new LogServiceProvider());
        $app->addProvider(new DatabaseServiceProvider());
        $app->addProvider(new RedisServiceProvider());
        $app->addProvider(new CacheServiceProvider());
        $app->addProvider(new StorageServiceProvider());
        $app->addProvider(new QueueServiceProvider());
        $app->addProvider(new RealtimeServiceProvider());
        $app->addProvider(new MigrateServiceProvider($userMigrations));
        $app->addProvider(new HttpServiceProvider());

        // Register user-provided extra providers
        foreach ($extraProviders as $provider) {
            $app->addProvider($provider);
        }

        $app->boot();

        return $app;
    }

    /**
     * Create a console application (no HTTP provider).
     *
     * @param string               $basePath
     * @param Config               $config
     * @param MigrationInterface[] $userMigrations
     * @param ServiceProviderInterface[] $extraProviders
     */
    public static function createForConsole(
        string $basePath,
        Config $config,
        array $userMigrations = [],
        array $extraProviders = []
    ): Application {
        $container = new Container();
        $app       = new Application($container, $config, $basePath);

        $app->addProvider(new LogServiceProvider());
        $app->addProvider(new DatabaseServiceProvider());
        $app->addProvider(new RedisServiceProvider());
        $app->addProvider(new CacheServiceProvider());
        $app->addProvider(new StorageServiceProvider());
        $app->addProvider(new QueueServiceProvider());
        $app->addProvider(new RealtimeServiceProvider());
        $app->addProvider(new MigrateServiceProvider($userMigrations));

        foreach ($extraProviders as $provider) {
            $app->addProvider($provider);
        }

        $app->boot();

        return $app;
    }

    /**
     * Load config from a base path.
     *
     * Loads .env if present, then merges config arrays from config/*.php.
     */
    public static function loadConfig(string $basePath): Config
    {
        $envFile = $basePath . '/.env';
        $envLoader = new EnvLoader();

        if (is_file($envFile)) {
            $envLoader->load($envFile);
        }

        /** @var array<string, mixed> $merged */
        $merged = [];

        /** @var string[] $configFiles */
        $configFiles = [
            'app',
            'database',
            'cache',
            'redis',
            'queue',
            'realtime',
            'storage',
            'log'
        ];

        foreach ($configFiles as $name) {
            $file = $basePath . '/config/' . $name . '.php';
            if (is_file($file)) {
                /** @var array<string, mixed> $data */
                $data = require $file;
                if (is_array($data)) {
                    $merged[$name] = $data;
                }
            }
        }

        return new Config($merged);
    }
}
