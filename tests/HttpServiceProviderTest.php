<?php

declare(strict_types=1);

namespace LPhenom\Lphenom\Tests;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Http\MiddlewareStack;
use LPhenom\Http\Router;
use LPhenom\Lphenom\Http\HttpKernel;
use LPhenom\Lphenom\Provider\HttpServiceProvider;
use PHPUnit\Framework\TestCase;

final class HttpServiceProviderTest extends TestCase
{
    public function testRegistersHttpComponents(): void
    {
        $container = new Container();
        $config    = new Config([]);

        $provider = new HttpServiceProvider();
        $provider->register($container, $config);

        self::assertTrue($container->has(Router::class));
        self::assertTrue($container->has(MiddlewareStack::class));
        self::assertTrue($container->has(HttpKernel::class));

        $kernel = $container->get(HttpKernel::class);
        self::assertInstanceOf(HttpKernel::class, $kernel);
    }
}
