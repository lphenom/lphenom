<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Tests;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\LPhenom\Provider\MediaServiceProvider;
use LPhenom\Media\ImageProcessorInterface;
use LPhenom\Media\VideoProcessorInterface;
use PHPUnit\Framework\TestCase;

final class MediaServiceProviderTest extends TestCase
{
    public function testRegistersImageAndVideoProcessors(): void
    {
        $container = new Container();
        $config    = new Config([
            'media' => [
                'image_driver' => 'auto',
                'video_driver' => 'auto',
            ],
        ]);

        $provider = new MediaServiceProvider();
        $provider->register($container, $config);

        self::assertTrue($container->has(ImageProcessorInterface::class));
        self::assertTrue($container->has(VideoProcessorInterface::class));
    }

    public function testRegistersWithGdDriver(): void
    {
        $container = new Container();
        $config    = new Config([
            'media' => [
                'image_driver' => 'gd',
                'video_driver' => 'ffmpeg',
            ],
        ]);

        $provider = new MediaServiceProvider();
        $provider->register($container, $config);

        self::assertTrue($container->has(ImageProcessorInterface::class));
        self::assertTrue($container->has(VideoProcessorInterface::class));
    }

    public function testRegistersWithDefaultConfig(): void
    {
        $container = new Container();
        $config    = new Config([]);

        $provider = new MediaServiceProvider();
        $provider->register($container, $config);

        self::assertTrue($container->has(ImageProcessorInterface::class));
        self::assertTrue($container->has(VideoProcessorInterface::class));
    }
}
