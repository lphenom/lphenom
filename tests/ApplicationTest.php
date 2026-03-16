<?php

declare(strict_types=1);

namespace LPhenom\Lphenom\Tests;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Lphenom\Application;
use LPhenom\Lphenom\ServiceProviderInterface;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $container = new Container();
        $config    = new Config(['app' => ['name' => 'test']]);
        $app       = new Application($container, $config, '/tmp/test');

        self::assertSame($container, $app->getContainer());
        self::assertSame($config, $app->getConfig());
        self::assertSame('/tmp/test', $app->getBasePath());
        self::assertFalse($app->isBooted());
    }

    public function testPath(): void
    {
        $container = new Container();
        $config    = new Config([]);
        $app       = new Application($container, $config, '/srv/app');

        self::assertSame('/srv/app', $app->path());
        self::assertSame('/srv/app/config', $app->path('config'));
        self::assertSame('/srv/app/config', $app->path('/config'));
    }

    public function testAddProviderCallsRegister(): void
    {
        $container = new Container();
        $config    = new Config(['foo' => 'bar']);
        $app       = new Application($container, $config, '/tmp');

        $registered = false;
        $booted     = false;

        $provider = new class ($registered, $booted) implements ServiceProviderInterface {
            /** @var bool */
            private bool $registered;
            /** @var bool */
            private bool $booted;

            public function __construct(bool &$registered, bool &$booted)
            {
                $this->registered = &$registered;
                $this->booted     = &$booted;
            }

            public function register(Container $container, Config $config): void
            {
                $this->registered = true;
            }

            public function boot(Container $container, Config $config): void
            {
                $this->booted = true;
            }
        };

        $app->addProvider($provider);
        self::assertTrue($registered);
        self::assertFalse($booted);

        $app->boot();
        self::assertTrue($booted);
        self::assertTrue($app->isBooted());
    }

    public function testBootIsIdempotent(): void
    {
        $container = new Container();
        $config    = new Config([]);
        $app       = new Application($container, $config, '/tmp');

        $bootCount = 0;

        $provider = new class ($bootCount) implements ServiceProviderInterface {
            /** @var int */
            private int $count;

            public function __construct(int &$count)
            {
                $this->count = &$count;
            }

            public function register(Container $container, Config $config): void
            {
            }

            public function boot(Container $container, Config $config): void
            {
                $this->count++;
            }
        };

        $app->addProvider($provider);
        $app->boot();
        $app->boot();

        self::assertSame(1, $bootCount);
    }
}
