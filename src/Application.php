<?php

declare(strict_types=1);

namespace LPhenom\Lphenom;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;

/**
 * Application kernel — holds the DI container, config and service providers.
 *
 * KPHP-compatible: no reflection, no dynamic class loading, no magic.
 */
final class Application
{
    /** @var Container */
    private Container $container;

    /** @var Config */
    private Config $config;

    /** @var string */
    private string $basePath;

    /** @var bool */
    private bool $booted;

    /** @var ServiceProviderInterface[] */
    private array $providers;

    public function __construct(Container $container, Config $config, string $basePath)
    {
        $this->container = $container;
        $this->config    = $config;
        $this->basePath  = $basePath;
        $this->booted    = false;
        $this->providers = [];
    }

    /**
     * Register a service provider.
     *
     * Calls register() immediately. boot() is deferred until boot().
     */
    public function addProvider(ServiceProviderInterface $provider): void
    {
        $provider->register($this->container, $this->config);
        $this->providers[] = $provider;
    }

    /**
     * Boot all registered providers.
     *
     * Must be called after all addProvider() calls.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->boot($this->container, $this->config);
        }

        $this->booted = true;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Resolve a path relative to the base path.
     */
    public function path(string $sub = ''): string
    {
        if ($sub === '') {
            return $this->basePath;
        }

        return $this->basePath . '/' . ltrim($sub, '/');
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }
}
