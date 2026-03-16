<?php

declare(strict_types=1);

namespace LPhenom\Lphenom;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;

/**
 * Service provider contract.
 *
 * Each provider registers services into the Container
 * and optionally boots them after all providers are registered.
 *
 * KPHP-compatible: no reflection, no callable in arrays.
 */
interface ServiceProviderInterface
{
    /**
     * Register bindings into the container.
     *
     * Called once during application bootstrap.
     * Use this to set() factories on the container.
     */
    public function register(Container $container, Config $config): void;

    /**
     * Boot the provider after all providers have been registered.
     *
     * Called once after all register() calls are complete.
     * Use this for cross-provider wiring or initialization.
     */
    public function boot(Container $container, Config $config): void;
}
