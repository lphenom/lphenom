<?php

declare(strict_types=1);

namespace LPhenom\Lphenom\Tests;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Log\Contract\LoggerInterface;
use LPhenom\Log\Logger\NullLogger;
use LPhenom\Lphenom\Provider\LogServiceProvider;
use PHPUnit\Framework\TestCase;

final class LogServiceProviderTest extends TestCase
{
    public function testRegistersNullLoggerByDefault(): void
    {
        $container = new Container();
        $config    = new Config([
            'log' => [
                'driver'  => 'null',
                'channel' => 'test',
            ]
        ]);

        $provider = new LogServiceProvider();
        $provider->register($container, $config);

        self::assertTrue($container->has(LoggerInterface::class));

        $logger = $container->get(LoggerInterface::class);
        self::assertInstanceOf(NullLogger::class, $logger);
    }

    public function testRegistersFileLogger(): void
    {
        $container = new Container();
        $config    = new Config([
            'log' => [
                'driver'  => 'file',
                'channel' => 'test',
                'path'    => '/tmp/test-lphenom.log',
            ]
        ]);

        $provider = new LogServiceProvider();
        $provider->register($container, $config);

        self::assertTrue($container->has(LoggerInterface::class));
    }
}
