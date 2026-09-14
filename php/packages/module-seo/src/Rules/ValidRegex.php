<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use WebxUi\Seo\Panel\UrlMatcher;

/**
 * A pattern `preg_match` will accept, delimiters and all.
 *
 * Checked when it is saved so that a typo is a message under the field. Without this the
 * mistake surfaces on the public side, on every page, as a 500 — and the rule that caused it is
 * in a table nobody thinks to look at.
 */
final class ValidRegex implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! UrlMatcher::isValidRegex($value)) {
            $fail('webx-seo::errors.bad-regex')->translate();
        }
    }
}
