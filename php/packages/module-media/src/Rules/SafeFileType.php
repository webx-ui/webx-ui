<?php

declare(strict_types=1);

namespace WebxUi\Media\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use WebxUi\Media\Support\FileTypes;
use WebxUi\Media\Support\SvgSanitizer;

/**
 * The file's own bytes make a type the library keeps (see {@see FileTypes}), and an SVG among
 * them is one {@see SvgSanitizer} can make safe.
 *
 * Next to `mimes`, not instead of it: `mimes` answers "is this kind of content allowed", this
 * one "under which name, and is it safe to serve" — asked here so a refusal is a validation
 * message instead of a failed store.
 */
final class SafeFileType implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $resolved = app(FileTypes::class)->resolve($value);

        if ($resolved === null) {
            $fail('webx-media::errors.type-mismatch')->translate();

            return;
        }

        if ($resolved[1] === FileTypes::SVG && ! app(SvgSanitizer::class)->accepts((string) file_get_contents((string) $value->getRealPath()))) {
            $fail('webx-media::errors.unsafe-svg')->translate();
        }
    }
}
