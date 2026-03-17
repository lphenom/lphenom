<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Provider;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\Container\ServiceFactoryInterface;
use LPhenom\LPhenom\ServiceProviderInterface;
use LPhenom\Media\FfmpegVideoProcessor;
use LPhenom\Media\GdImageProcessor;
use LPhenom\Media\ImageMagickProcessor;
use LPhenom\Media\ImageProcessorInterface;
use LPhenom\Media\Shell\ShellRunner;
use LPhenom\Media\VideoProcessorInterface;

/**
 * Registers media processing services from lphenom/media.
 *
 * Config (config/media.php shape):
 *   media.image_driver — 'gd' | 'imagick' | 'auto', default 'auto'
 *   media.video_driver — 'ffmpeg' | 'auto', default 'auto'
 *
 * PHP-only: references GdImageProcessor (which requires the GD extension)
 * and uses `mixed` return type — neither is supported in KPHP.
 *
 * @see CliMediaServiceProvider for the KPHP-compatible variant (ImageMagick CLI + FFmpeg)
 *
 * @lphenom-build shared
 */
final class MediaServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, Config $config): void
    {
        $imageDriver = $config->get('media.image_driver', 'auto');
        $imageDriverStr = is_string($imageDriver) ? $imageDriver : 'auto';

        $container->set(ImageProcessorInterface::class, new ImageProcessorFactory($imageDriverStr));
        $container->set(VideoProcessorInterface::class, new VideoProcessorFactory());
    }

    public function boot(Container $container, Config $config): void
    {
        // no-op
    }
}

/**
 * @internal
 */
final class ImageProcessorFactory implements ServiceFactoryInterface
{
    /** @var string */
    private string $driver;

    public function __construct(string $driver)
    {
        $this->driver = $driver;
    }

    public function create(Container $container): mixed
    {
        if ($this->driver === 'gd') {
            return new GdImageProcessor();
        }

        $shell = new ShellRunner();

        if ($this->driver === 'imagick') {
            return new ImageMagickProcessor($shell);
        }

        // auto: prefer GD, fall back to ImageMagick
        if (extension_loaded('gd')) {
            return new GdImageProcessor();
        }

        return new ImageMagickProcessor($shell);
    }
}

/**
 * @internal
 */
final class VideoProcessorFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        $shell = new ShellRunner();
        return new FfmpegVideoProcessor($shell);
    }
}




