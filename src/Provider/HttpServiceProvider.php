<?php

declare(strict_types=1);

namespace LPhenom\Lphenom\Provider;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\Container\ServiceFactoryInterface;
use LPhenom\Http\MiddlewareStack;
use LPhenom\Http\Router;
use LPhenom\Lphenom\Http\HttpKernel;
use LPhenom\Lphenom\ServiceProviderInterface;

/**
 * Registers HTTP components: Router, MiddlewareStack, HttpKernel.
 */
final class HttpServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, Config $config): void
    {
        $container->set(Router::class, new RouterFactory());
        $container->set(MiddlewareStack::class, new MiddlewareStackFactory());
        $container->set(HttpKernel::class, new HttpKernelFactory());
    }

    public function boot(Container $container, Config $config): void
    {
        // no-op
    }
}

/**
 * @internal
 */
final class RouterFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        return new Router();
    }
}

/**
 * @internal
 */
final class MiddlewareStackFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        return new MiddlewareStack();
    }
}

/**
 * @internal
 */
final class HttpKernelFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        /** @var Router $router */
        $router = $container->get(Router::class);
        /** @var MiddlewareStack $stack */
        $stack = $container->get(MiddlewareStack::class);
        return new HttpKernel($router, $stack);
    }
}
