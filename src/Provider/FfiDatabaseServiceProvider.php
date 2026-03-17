<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Provider;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\Container\ServiceFactoryInterface;
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Driver\FfiMySqlConnection;
use LPhenom\LPhenom\ServiceProviderInterface;

/**
 * Registers a database connection via FFI MySQL driver.
 *
 * Uses FfiMySqlConnection — a pure FFI-based MySQL driver that requires
 * libmysqlclient.so but NOT the ext-pdo_mysql PHP extension.
 * Works in both PHP and KPHP environments.
 *
 * Config (config/database.php shape):
 *   database.host     — string, default '127.0.0.1'
 *   database.port     — int, default 3306
 *   database.dbname   — string
 *   database.user     — string
 *   database.password — string
 *
 * @see DatabaseServiceProvider for the ext-pdo_mysql variant (shared-only)
 *
 * @lphenom-build shared,kphp
 */
final class FfiDatabaseServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, Config $config): void
    {
        $host     = $config->get('database.host', '127.0.0.1');
        $port     = $config->get('database.port', 3306);
        $dbname   = $config->get('database.dbname', '');
        $user     = $config->get('database.user', '');
        $password = $config->get('database.password', '');

        $hostStr = is_string($host) ? $host : '127.0.0.1';
        $portInt = is_int($port) ? $port : 3306;
        $dbStr   = is_string($dbname) ? $dbname : '';
        $userStr = is_string($user) ? $user : '';
        $passStr = is_string($password) ? $password : '';

        $container->set(
            ConnectionInterface::class,
            new FfiDatabaseConnectionFactory($hostStr, $portInt, $dbStr, $userStr, $passStr)
        );
    }

    public function boot(Container $container, Config $config): void
    {
        // no-op
    }
}

/**
 * @internal
 */
final class FfiDatabaseConnectionFactory implements ServiceFactoryInterface
{
    /** @var string */
    private string $host;
    /** @var int */
    private int $port;
    /** @var string */
    private string $dbname;
    /** @var string */
    private string $user;
    /** @var string */
    private string $password;

    public function __construct(string $host, int $port, string $dbname, string $user, string $password)
    {
        $this->host     = $host;
        $this->port     = $port;
        $this->dbname   = $dbname;
        $this->user     = $user;
        $this->password = $password;
    }

    public function create(Container $container): mixed
    {
        return new FfiMySqlConnection(
            $this->host,
            $this->user,
            $this->password,
            $this->dbname,
            $this->port
        );
    }
}

