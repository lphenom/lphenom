<?php

declare(strict_types=1);

/**
 * Media processing configuration.
 *
 * @return array<string, mixed>
 */
return [
    'image_driver' => $_ENV['MEDIA_IMAGE_DRIVER'] ?? 'auto',
    'video_driver' => $_ENV['MEDIA_VIDEO_DRIVER'] ?? 'auto',
];

