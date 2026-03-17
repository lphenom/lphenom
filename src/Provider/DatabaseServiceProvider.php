<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Provider;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\Container\ServiceFactoryInterface;
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Driver\ConnectionFactory;
use LPhenom\LPhenom\ServiceProviderInterface;

/**
 * Registers a database connection from lphenom/db.
 *
 * Config (config/database.php shape):
 *   database.driver   — 'pdo_mysql' | 'ffi_mysql'
 *   database.host     — string
 *   database.port     — int
 *   database.dbname   — string
 *   database.user     — string
 *   database.password — string
 *
 * PHP-only: uses ConnectionFactory which internally references PdoMySqlConnection
 * (requires ext-pdo_mysql) — not available in KPHP.
 *
 * @see FfiDatabaseServiceProvider for the KPHP-compatible variant (FFI MySQL)
 *
 * @lphenom-build shared
 */
final class DatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, Config $config): void
    {
        $container->set(ConnectionInterface::class, new DatabaseConnectionFactory($config));
    }

    public function boot(Container $container, Config $config): void
    {
        // no-op
    }
}

/**
 * @internal
 */
final class DatabaseConnectionFactory implements ServiceFactoryInterface
{
    /** @var Config */
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function create(Container $container): mixed
    {
        $driver   = $this->config->get('database.driver', 'pdo_mysql');
        $host     = $this->config->get('database.host', '127.0.0.1');
        $port     = $this->config->get('database.port', 3306);
        $dbname   = $this->config->get('database.dbname', '');
        $user     = $this->config->get('database.user', '');
        $password = $this->config->get('database.password', '');

        /** @var array<string, mixed> $dbConfig */
        $dbConfig = [
            'driver'   => $driver,
            'host'     => $host,
            'port'     => $port,
            'dbname'   => $dbname,
            'user'     => $user,
            'password' => $password
        ];

        return ConnectionFactory::create($dbConfig);
    }
}
