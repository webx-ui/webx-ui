<?php

declare(strict_types=1);

namespace WebxUi\Seo\Redirects;

use WebxUi\Routing\Contracts\AddressRedirects;
use WebxUi\Seo\Models\SeoRedirect;
use WebxUi\Seo\Panel\UrlMatcher;

/** A moved address as a row of the redirects section, where an editor can see and undo it. */
final class SeoAddressRedirects implements AddressRedirects
{
    public function moved(string $from, string $to): void
    {
        SeoRedirect::query()->updateOrCreate(
            ['match_type' => UrlMatcher::EXACT, 'pattern' => $from],
            ['target' => $to, 'status' => 301, 'is_active' => true],
        );
    }
}
