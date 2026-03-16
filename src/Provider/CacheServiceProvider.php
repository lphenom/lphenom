<?php

declare(strict_types=1);

namespace LPhenom\Lphenom\Provider;

use LPhenom\Cache\CacheInterface;
use LPhenom\Cache\Driver\FileCache;
use LPhenom\Cache\Driver\RedisCache;
use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\Container\ServiceFactoryInterface;
use LPhenom\Lphenom\ServiceProviderInterface;
use LPhenom\Redis\Client\RedisClientInterface;
use LPhenom\Storage\LocalFilesystemStorage;

/**
 * Registers a cache driver from lphenom/cache.
 *
 * Config (config/cache.php shape):
 *   cache.driver   — 'file' | 'redis', default 'file'
 *   cache.path     — string, path for file cache
 */
final class CacheServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, Config $config): void
    {
        $driver = $config->get('cache.driver', 'file');

        if ($driver === 'redis') {
            $container->set(CacheInterface::class, new RedisCacheFactory());
        } else {
            $cachePath = $config->get('cache.path', '/tmp/lphenom-cache');
            $pathStr   = is_string($cachePath) ? $cachePath : '/tmp/lphenom-cache';
            $container->set(CacheInterface::class, new FileCacheFactory($pathStr));
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
final class FileCacheFactory implements ServiceFactoryInterface
{
    /** @var string */
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function create(Container $container): mixed
    {
        $storage = new LocalFilesystemStorage($this->path);
        return new FileCache($storage);
    }
}

/**
 * @internal
 */
final class RedisCacheFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        /** @var RedisClientInterface $redis */
        $redis = $container->get(RedisClientInterface::class);
        return new RedisCache($redis);
    }
}
