<?php

declare(strict_types=1);

namespace LPhenom\LPhenom;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\EnvLoader\EnvLoader;
use LPhenom\Db\Migration\MigrationInterface;
use LPhenom\LPhenom\Build\MigrationLoader;
use LPhenom\LPhenom\Provider\AuthServiceProvider;
use LPhenom\LPhenom\Provider\CacheServiceProvider;
use LPhenom\LPhenom\Provider\DatabaseServiceProvider;
use LPhenom\LPhenom\Provider\HttpServiceProvider;
use LPhenom\LPhenom\Provider\LogServiceProvider;
use LPhenom\LPhenom\Provider\MediaServiceProvider;
use LPhenom\LPhenom\Provider\MigrateServiceProvider;
use LPhenom\LPhenom\Provider\QueueServiceProvider;
use LPhenom\LPhenom\Provider\RealtimeServiceProvider;
use LPhenom\LPhenom\Provider\RedisServiceProvider;
use LPhenom\LPhenom\Provider\StorageServiceProvider;

/**
 * Application factory — builds a fully wired Application for PHP runtime.
 *
 * Uses providers that leverage PHP extensions for best performance:
 *   - DatabaseServiceProvider  (ext-pdo_mysql via ConnectionFactory)
 *   - RedisServiceProvider     (ext-redis via RedisConnector)
 *   - MediaServiceProvider     (ext-gd via GdImageProcessor when available)
 *
 * @see KphpAppFactory for the KPHP-compatible variant (FFI MySQL, RESP Redis, CLI media)
 *
 * @lphenom-build shared
 */
final class AppFactory
{
    private function __construct()
    {
    }

    /**
     * Create a web application with all providers.
     *
     * Migrations from database/migrations/ are auto-discovered and merged
     * with any extra $userMigrations passed by the caller.
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

        $allMigrations = self::loadMigrations($basePath, $userMigrations);

        // Register core providers in dependency order
        $app->addProvider(new LogServiceProvider());
        $app->addProvider(new DatabaseServiceProvider());
        $app->addProvider(new RedisServiceProvider());
        $app->addProvider(new CacheServiceProvider());
        $app->addProvider(new AuthServiceProvider());
        $app->addProvider(new StorageServiceProvider());
        $app->addProvider(new MediaServiceProvider());
        $app->addProvider(new QueueServiceProvider());
        $app->addProvider(new RealtimeServiceProvider());
        $app->addProvider(new MigrateServiceProvider($allMigrations));
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

        $allMigrations = self::loadMigrations($basePath, $userMigrations);

        $app->addProvider(new LogServiceProvider());
        $app->addProvider(new DatabaseServiceProvider());
        $app->addProvider(new RedisServiceProvider());
        $app->addProvider(new CacheServiceProvider());
        $app->addProvider(new AuthServiceProvider());
        $app->addProvider(new StorageServiceProvider());
        $app->addProvider(new MediaServiceProvider());
        $app->addProvider(new QueueServiceProvider());
        $app->addProvider(new RealtimeServiceProvider());
        $app->addProvider(new MigrateServiceProvider($allMigrations));

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
            'auth',
            'database',
            'cache',
            'redis',
            'queue',
            'realtime',
            'storage',
            'media',
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

    /**
     * Auto-discover migrations from database/migrations/ and merge with extras.
     *
     * Uses MigrationLoader to scan the directory, require each PHP file,
     * instantiate the migration class by naming convention, and return
     * MigrationInterface[] instances.
     *
     * @param string               $basePath
     * @param MigrationInterface[] $extraMigrations
     * @return MigrationInterface[]
     */
    private static function loadMigrations(string $basePath, array $extraMigrations): array
    {
        $loader     = new MigrationLoader($basePath . '/database/migrations');
        $discovered = $loader->loadAll();

        return array_merge($discovered, $extraMigrations);
    }
}
