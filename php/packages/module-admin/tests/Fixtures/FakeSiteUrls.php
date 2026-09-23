<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests\Fixtures;

use WebxUi\Admin\Contracts\SiteUrls;

/**
 * The address registry's answer, written out.
 *
 * `webx-ui/routing` is not a dependency of the frame, so this package cannot test against the real
 * one — and does not need to: what belongs here is that a path goes through whoever answers this
 * contract. Whether the prefix itself is right is `SiteUrlTest` in routing.
 */
final class FakeSiteUrls implements SiteUrls
{
    public function to(string $path, ?string $locale = null): string
    {
        $prefix = $locale === null || $locale === 'en' ? '' : '/'.$locale;

        return 'https://example.test'.$prefix.'/'.ltrim($path, '/');
    }
}
