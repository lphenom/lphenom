<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Provider;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\Container\ServiceFactoryInterface;
use LPhenom\LPhenom\ServiceProviderInterface;
use LPhenom\Redis\Client\RedisClientInterface;
use LPhenom\Redis\Client\RespRedisClient;
use LPhenom\Redis\Exception\RedisConnectionException;
use LPhenom\Redis\Resp\RespClient;

/**
 * Registers a Redis client via raw RESP protocol over TCP.
 *
 * Uses RespRedisClient — a pure TCP/RESP implementation that does NOT
 * require the ext-redis PHP extension. Works in both PHP and KPHP.
 *
 * Config (config/redis.php shape):
 *   redis.host     — string, default '127.0.0.1'
 *   redis.port     — int, default 6379
 *   redis.password — string, default ''
 *   redis.database — int, default 0
 *   redis.timeout  — float, seconds, default 2.0
 *
 * @see RedisServiceProvider for the ext-redis variant (shared-only)
 *
 * @lphenom-build shared,kphp
 */
final class RespRedisServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, Config $config): void
    {
        $host     = $config->get('redis.host', '127.0.0.1');
        $port     = $config->get('redis.port', 6379);
        $password = $config->get('redis.password', '');
        $database = $config->get('redis.database', 0);
        $timeout  = $config->get('redis.timeout', 2.0);

        $hostStr    = is_string($host) ? $host : '127.0.0.1';
        $portInt    = is_int($port) ? $port : 6379;
        $passStr    = is_string($password) ? $password : '';
        $dbInt      = is_int($database) ? $database : 0;
        $timeoutFlt = is_float($timeout) ? $timeout : (is_int($timeout) ? (float) $timeout : 2.0);

        $container->set(
            RedisClientInterface::class,
            new RespRedisClientFactory($hostStr, $portInt, $passStr, $dbInt, $timeoutFlt)
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
final class RespRedisClientFactory implements ServiceFactoryInterface
{
    /** @var string */
    private string $host;
    /** @var int */
    private int $port;
    /** @var string */
    private string $password;
    /** @var int */
    private int $database;
    /** @var float */
    private float $timeout;

    public function __construct(string $host, int $port, string $password, int $database, float $timeout)
    {
        $this->host     = $host;
        $this->port     = $port;
        $this->password = $password;
        $this->database = $database;
        $this->timeout  = $timeout;
    }

    public function create(Container $container): mixed
    {
        $resp = new RespClient($this->host, $this->port, $this->timeout);
        $resp->connect();

        // AUTH if password is set
        if ($this->password !== '') {
            /** @var mixed $authReply */
            $authReply = $resp->command(['AUTH', $this->password]);
            if ($authReply !== 'OK') {
                throw new RedisConnectionException('Redis AUTH failed');
            }
        }

        // SELECT database
        if ($this->database !== 0) {
            /** @var mixed $selectReply */
            $selectReply = $resp->command(['SELECT', (string) $this->database]);
            if ($selectReply !== 'OK') {
                throw new RedisConnectionException('Redis SELECT db=' . $this->database . ' failed');
            }
        }

        return new RespRedisClient($resp);
    }
}

