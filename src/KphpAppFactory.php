<?php

declare(strict_types=1);

namespace LPhenom\LPhenom;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Db\Migration\MigrationInterface;
use LPhenom\LPhenom\Provider\CacheServiceProvider;
use LPhenom\LPhenom\Provider\CliMediaServiceProvider;
use LPhenom\LPhenom\Provider\FfiDatabaseServiceProvider;
use LPhenom\LPhenom\Provider\HttpServiceProvider;
use LPhenom\LPhenom\Provider\LogServiceProvider;
use LPhenom\LPhenom\Provider\MigrateServiceProvider;
use LPhenom\LPhenom\Provider\QueueServiceProvider;
use LPhenom\LPhenom\Provider\RealtimeServiceProvider;
use LPhenom\LPhenom\Provider\RespRedisServiceProvider;
use LPhenom\LPhenom\Provider\StorageServiceProvider;

/**
 * KPHP-compatible application factory.
 *
 * Uses only providers that do NOT require PHP extensions:
 *   - FfiDatabaseServiceProvider  (FFI MySQL, no ext-pdo_mysql)
 *   - RespRedisServiceProvider    (RESP over TCP, no ext-redis)
 *   - CliMediaServiceProvider     (ImageMagick CLI + FFmpeg, no ext-gd)
 *
 * All other providers (Log, Cache, Storage, Queue, Realtime, Migrate, Http)
 * are already KPHP-compatible and shared between both factories.
 *
 * @see AppFactory for the PHP-native variant (uses PDO, ext-redis, GD)
 *
 * @lphenom-build shared,kphp
 */
final class KphpAppFactory
{
    private function __construct()
    {
    }

    /**
     * Create a web application with all KPHP-compatible providers.
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
        $app->addProvider(new FfiDatabaseServiceProvider());
        $app->addProvider(new RespRedisServiceProvider());
        $app->addProvider(new CacheServiceProvider());
        $app->addProvider(new StorageServiceProvider());
        $app->addProvider(new CliMediaServiceProvider());
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
        $app->addProvider(new FfiDatabaseServiceProvider());
        $app->addProvider(new RespRedisServiceProvider());
        $app->addProvider(new CacheServiceProvider());
        $app->addProvider(new StorageServiceProvider());
        $app->addProvider(new CliMediaServiceProvider());
        $app->addProvider(new QueueServiceProvider());
        $app->addProvider(new RealtimeServiceProvider());
        $app->addProvider(new MigrateServiceProvider($userMigrations));

        foreach ($extraProviders as $provider) {
            $app->addProvider($provider);
        }

        $app->boot();

        return $app;
    }
}

