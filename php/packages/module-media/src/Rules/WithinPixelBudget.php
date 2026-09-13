<?php

declare(strict_types=1);

namespace WebxUi\Media\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Refuse a picture too large to decode, before anything decodes it.
 *
 * Size in bytes says nothing here: a few hundred kilobytes of PNG can be thirty thousand pixels
 * on a side, and opening it is how a server runs out of memory.
 */
final class WithinPixelBudget implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! str_starts_with((string) $value->getClientMimeType(), 'image/')) {
            return;
        }

        $size = @getimagesize($value->getRealPath());

        if ($size === false) {
            return;
        }

        $budget = (int) config('webx-media.image.max_pixels', 50_000_000);

        if ($budget < $size[0] * $size[1]) {
            $fail('webx-media::errors.image-too-large')->translate([
                'megapixels' => round($budget / 1_000_000, 1),
            ]);
        }
    }
}
