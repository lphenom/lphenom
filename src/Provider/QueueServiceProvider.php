<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Provider;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\Container\ServiceFactoryInterface;
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\LPhenom\ServiceProviderInterface;
use LPhenom\Queue\Driver\DbQueue;
use LPhenom\Queue\Driver\RedisQueue;
use LPhenom\Queue\QueueInterface;
use LPhenom\Queue\Retry\RetryPolicy;
use LPhenom\Queue\Worker;
use LPhenom\Redis\Client\RedisClientInterface;

/**
 * Registers queue services from lphenom/queue.
 *
 * Config (config/queue.php shape):
 *   queue.driver         — 'database' | 'redis', default 'database'
 *   queue.table          — string, table name for DB driver, default 'jobs'
 *   queue.redis_key      — string, Redis list key, default 'queue:jobs'
 *   queue.max_attempts   — int, default 3
 *   queue.retry_delay    — int, base delay seconds, default 1
 */
final class QueueServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, Config $config): void
    {
        $driver       = $config->get('queue.driver', 'database');
        $table        = $config->get('queue.table', 'jobs');
        $redisKey     = $config->get('queue.redis_key', 'queue:jobs');
        $maxAttempts  = $config->get('queue.max_attempts', 3);
        $retryDelay   = $config->get('queue.retry_delay', 1);

        $tableStr    = is_string($table) ? $table : 'jobs';
        $redisKeyStr = is_string($redisKey) ? $redisKey : 'queue:jobs';
        $maxInt      = is_int($maxAttempts) ? $maxAttempts : 3;
        $delayInt    = is_int($retryDelay) ? $retryDelay : 1;

        if ($driver === 'redis') {
            $container->set(
                QueueInterface::class,
                new RedisQueueFactory($redisKeyStr, $maxInt, $delayInt)
            );
        } else {
            $container->set(
                QueueInterface::class,
                new DbQueueFactory($tableStr, $maxInt, $delayInt)
            );
        }

        $container->set(Worker::class, new WorkerFactory());
    }

    public function boot(Container $container, Config $config): void
    {
        // no-op
    }
}

/**
 * @internal
 */
final class DbQueueFactory implements ServiceFactoryInterface
{
    /** @var string */
    private string $table;
    /** @var int */
    private int $maxAttempts;
    /** @var int */
    private int $retryDelay;

    public function __construct(string $table, int $maxAttempts, int $retryDelay)
    {
        $this->table       = $table;
        $this->maxAttempts = $maxAttempts;
        $this->retryDelay  = $retryDelay;
    }

    public function create(Container $container): mixed
    {
        /** @var ConnectionInterface $conn */
        $conn = $container->get(ConnectionInterface::class);
        $policy = new RetryPolicy($this->maxAttempts, $this->retryDelay);
        return new DbQueue($conn, $this->table, $policy);
    }
}

/**
 * @internal
 */
final class RedisQueueFactory implements ServiceFactoryInterface
{
    /** @var string */
    private string $queueKey;
    /** @var int */
    private int $maxAttempts;
    /** @var int */
    private int $retryDelay;

    public function __construct(string $queueKey, int $maxAttempts, int $retryDelay)
    {
        $this->queueKey    = $queueKey;
        $this->maxAttempts = $maxAttempts;
        $this->retryDelay  = $retryDelay;
    }

    public function create(Container $container): mixed
    {
        /** @var RedisClientInterface $redis */
        $redis  = $container->get(RedisClientInterface::class);
        $policy = new RetryPolicy($this->maxAttempts, $this->retryDelay);
        return new RedisQueue($redis, $this->queueKey, $policy);
    }
}

/**
 * @internal
 */
final class WorkerFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        /** @var QueueInterface $queue */
        $queue = $container->get(QueueInterface::class);
        return new Worker($queue);
    }
}
