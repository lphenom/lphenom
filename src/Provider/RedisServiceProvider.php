<?php

declare(strict_types=1);

namespace LPhenom\Lphenom\Provider;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\Container\ServiceFactoryInterface;
use LPhenom\Lphenom\ServiceProviderInterface;
use LPhenom\Redis\Client\RedisClientInterface;
use LPhenom\Redis\Connection\RedisConnectionConfig;
use LPhenom\Redis\Connection\RedisConnector;

/**
 * Registers a Redis client from lphenom/redis.
 *
 * Config (config/redis.php shape):
 *   redis.host     — string, default '127.0.0.1'
 *   redis.port     — int, default 6379
 *   redis.password — string, default ''
 *   redis.database — int, default 0
 */
final class RedisServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, Config $config): void
    {
        $host     = $config->get('redis.host', '127.0.0.1');
        $port     = $config->get('redis.port', 6379);
        $password = $config->get('redis.password', '');
        $database = $config->get('redis.database', 0);

        $hostStr = is_string($host) ? $host : '127.0.0.1';
        $portInt = is_int($port) ? $port : 6379;
        $passStr = is_string($password) ? $password : '';
        $dbInt   = is_int($database) ? $database : 0;

        $container->set(
            RedisClientInterface::class,
            new RedisClientFactory($hostStr, $portInt, $passStr, $dbInt)
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
final class RedisClientFactory implements ServiceFactoryInterface
{
    /** @var string */
    private string $host;
    /** @var int */
    private int $port;
    /** @var string */
    private string $password;
    /** @var int */
    private int $database;

    public function __construct(string $host, int $port, string $password, int $database)
    {
        $this->host     = $host;
        $this->port     = $port;
        $this->password = $password;
        $this->database = $database;
    }

    public function create(Container $container): mixed
    {
        $redisConfig = new RedisConnectionConfig(
            $this->host,
            $this->port,
            $this->password,
            $this->database
        );
        return RedisConnector::connect($redisConfig);
    }
}
