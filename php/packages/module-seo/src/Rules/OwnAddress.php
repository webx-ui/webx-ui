<?php

declare(strict_types=1);

namespace WebxUi\Seo\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use WebxUi\Admin\Links\SiteRoutes;
use WebxUi\Seo\Targets\ForeignHost;
use WebxUi\Seo\Targets\UrlTargets;

/**
 * An address on this site: a path, or a full address whose host is this site's (§18.2). Copied
 * from the browser with the host is fine — the host goes on saving; another site's is refused.
 * And a page of the site rather than its machinery: the panel, a preview or the OAuth consent has
 * no title or description of its own to set ({@see SiteRoutes::isServiceAddress()}).
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

            return;
        }

        if (app(SiteRoutes::class)->isServiceAddress($value)) {
            $fail('webx-seo::errors.service-address')->translate();
        }
    }
}
