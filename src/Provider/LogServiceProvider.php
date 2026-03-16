<?php

declare(strict_types=1);

namespace LPhenom\Lphenom\Provider;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\Container\ServiceFactoryInterface;
use LPhenom\Log\Contract\LoggerInterface;
use LPhenom\Log\Logger\FileLogger;
use LPhenom\Log\Logger\NullLogger;
use LPhenom\Lphenom\ServiceProviderInterface;

/**
 * Registers a logger from lphenom/log.
 *
 * Config (config/log.php shape):
 *   log.channel  — string, default 'app'
 *   log.driver   — 'file' | 'null', default 'null'
 *   log.path     — string, log file path (for file driver)
 */
final class LogServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, Config $config): void
    {
        $driver  = $config->get('log.driver', 'null');
        $channel = $config->get('log.channel', 'app');
        $path    = $config->get('log.path', '');

        if ($driver === 'file' && is_string($path) && $path !== '') {
            $channelStr = is_string($channel) ? $channel : 'app';
            $container->set(LoggerInterface::class, new LogFileFactory($path, $channelStr));
        } else {
            $container->set(LoggerInterface::class, new LogNullFactory());
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
final class LogFileFactory implements ServiceFactoryInterface
{
    /** @var string */
    private string $path;
    /** @var string */
    private string $channel;

    public function __construct(string $path, string $channel)
    {
        $this->path    = $path;
        $this->channel = $channel;
    }

    public function create(Container $container): mixed
    {
        return new FileLogger($this->path, 10 * 1024 * 1024, 5, $this->channel);
    }
}

/**
 * @internal
 */
final class LogNullFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        return new NullLogger();
    }
}
