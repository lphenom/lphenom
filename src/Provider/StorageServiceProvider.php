<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Provider;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\Container\ServiceFactoryInterface;
use LPhenom\LPhenom\ServiceProviderInterface;
use LPhenom\Storage\LocalFilesystemStorage;
use LPhenom\Storage\StorageInterface;

/**
 * Registers storage from lphenom/storage.
 *
 * Config (config/storage.php shape):
 *   storage.driver — 'local', default 'local'
 *   storage.root   — string, root path
 */
final class StorageServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, Config $config): void
    {
        $root    = $config->get('storage.root', '/tmp/lphenom-storage');
        $rootStr = is_string($root) ? $root : '/tmp/lphenom-storage';

        $container->set(StorageInterface::class, new LocalStorageFactory($rootStr));
    }

    public function boot(Container $container, Config $config): void
    {
        // no-op
    }
}

/**
 * @internal
 */
final class LocalStorageFactory implements ServiceFactoryInterface
{
    /** @var string */
    private string $root;

    public function __construct(string $root)
    {
        $this->root = $root;
    }

    public function create(Container $container): mixed
    {
        return new LocalFilesystemStorage($this->root);
    }
}
