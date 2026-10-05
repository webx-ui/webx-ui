<?php

declare(strict_types=1);

namespace WebxUi\Media\Images\Optimizing;

use Intervention\Image\Interfaces\ImageInterface;

/**
 * No side longer than `max_side`.
 *
 * A camera's 6000px photograph is printed at a fraction of that on any page, and every visitor
 * downloads the whole of it. Smaller pictures are left alone: scaling up only adds bytes.
 */
final class ScaleDown implements OptimizeStep
{
    public function apply(ImageInterface $image, array $config): ImageInterface
    {
        $max = (int) ($config['max_side'] ?? 0);

        return $max > 0 ? $image->scaleDown(width: $max, height: $max) : $image;
    }
}
