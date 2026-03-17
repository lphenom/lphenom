<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Provider;

use LPhenom\Core\Config\Config;
use LPhenom\Core\Container\Container;
use LPhenom\Core\Container\ServiceFactoryInterface;
use LPhenom\LPhenom\ServiceProviderInterface;
use LPhenom\Media\FfmpegVideoProcessor;
use LPhenom\Media\ImageMagickProcessor;
use LPhenom\Media\ImageProcessorInterface;
use LPhenom\Media\Shell\ShellRunner;
use LPhenom\Media\VideoProcessorInterface;

/**
 * Registers media processing services using CLI tools only.
 *
 * Uses ImageMagickProcessor (convert) for images and
 * FfmpegVideoProcessor (ffmpeg/ffprobe) for video.
 * No PHP extensions required — works in both PHP and KPHP.
 *
 * Requirements: `convert` (ImageMagick) and `ffmpeg`/`ffprobe` in $PATH.
 *
 * @see MediaServiceProvider for the GD-aware variant (shared-only)
 *
 * @lphenom-build shared,kphp
 */
final class CliMediaServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, Config $config): void
    {
        $container->set(ImageProcessorInterface::class, new ImageMagickProcessorFactory());
        $container->set(VideoProcessorInterface::class, new FfmpegVideoProcessorFactory());
    }

    public function boot(Container $container, Config $config): void
    {
        // no-op
    }
}

/**
 * @internal
 */
final class ImageMagickProcessorFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        return new ImageMagickProcessor(new ShellRunner());
    }
}

/**
 * @internal
 */
final class FfmpegVideoProcessorFactory implements ServiceFactoryInterface
{
    public function create(Container $container): mixed
    {
        return new FfmpegVideoProcessor(new ShellRunner());
    }
}

