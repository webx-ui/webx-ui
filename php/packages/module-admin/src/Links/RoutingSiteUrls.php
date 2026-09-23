<?php

declare(strict_types=1);

namespace WebxUi\Admin\Links;

use WebxUi\Admin\AdminServiceProvider;
use WebxUi\Admin\Contracts\SiteUrls;
use WebxUi\Routing\SiteUrl;

/**
 * {@see SiteUrls} answered by `webx-ui/routing`, when that package is installed.
 *
 * Bound only behind a `class_exists` check in {@see AdminServiceProvider}, so on a
 * panel without the address registry this class is never resolved and therefore never loaded —
 * which is what makes it safe for the frame to name a class it does not require.
 */
final readonly class RoutingSiteUrls implements SiteUrls
{
    public function __construct(private SiteUrl $site) {}

    public function to(string $path, ?string $locale = null): string
    {
        return $this->site->to($path, $locale);
    }
}
