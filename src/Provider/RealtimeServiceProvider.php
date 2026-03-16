<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Provider;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\Container\ServiceFactoryInterface;
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\LPhenom\ServiceProviderInterface;
use LPhenom\Realtime\Bus\DbEventStoreBus;
use LPhenom\Realtime\Bus\WebSocketBus;
use LPhenom\Realtime\RealtimeBusInterface;
use LPhenom\Redis\Client\RedisClientInterface;
use LPhenom\Redis\PubSub\RedisPublisher;

/**
 * Registers realtime services from lphenom/realtime.
 *
 * Config (config/realtime.php shape):
 *   realtime.driver — 'database' | 'websocket', default 'database'
 */
final class RealtimeServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, Config $config): void
    {
        $driver = $config->get('realtime.driver', 'database');

        if ($driver === 'websocket') {
            $container->set(RealtimeBusInterface::class, new WebSocketBusFactory());
        } else {
            $container->set(RealtimeBusInterface::class, new DbBusFactory());
        }
    }

    public function boot(Container $container, Config $config): void
    {
        // no-op
    }
}

/**
 * @internal
 */
final class DbBusFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        /** @var ConnectionInterface $conn */
        $conn = $container->get(ConnectionInterface::class);
        return new DbEventStoreBus($conn);
    }
}

/**
 * @internal
 */
final class WebSocketBusFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        /** @var ConnectionInterface $conn */
        $conn = $container->get(ConnectionInterface::class);
        $dbBus = new DbEventStoreBus($conn);

        /** @var RedisClientInterface $redis */
        $redis = $container->get(RedisClientInterface::class);
        $publisher = new RedisPublisher($redis);

        return new WebSocketBus($dbBus, $publisher);
    }
}
