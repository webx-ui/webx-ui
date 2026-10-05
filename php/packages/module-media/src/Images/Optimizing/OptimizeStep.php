<?php

declare(strict_types=1);

namespace WebxUi\Media\Images\Optimizing;

use Intervention\Image\Interfaces\ImageInterface;

/**
 * One step of what happens to a picture on its way into the library.
 *
 * Listed in `webx-media.optimize.steps`, in order, so a project adds its own — a watermark, a
 * colour profile — by naming a class rather than by forking the module. The image arrives turned
 * the right way up already, and is encoded after the last step.
 */
interface OptimizeStep
{
    /**
     * @param  array<string, mixed>  $config  `webx-media.optimize`, for the step's own setting
     */
    public function apply(ImageInterface $image, array $config): ImageInterface;
}
