<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use WebxUi\Seo\Targets\ForeignHost;
use WebxUi\Seo\Targets\UrlTargets;

/**
 * An address on this site: a path, or a full address whose host is this site's (§18.2). Copied
 * from the browser with the host is fine — the host goes on saving; another site's is refused.
 */
final class OwnAddress implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        try {
            app(UrlTargets::class)->resolve($value);
        } catch (ForeignHost) {
            $fail('webx-seo::errors.foreign-host')->translate();
        }
    }
}
