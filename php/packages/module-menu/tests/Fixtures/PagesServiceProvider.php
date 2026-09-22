<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Links\LinkSources;

/**
 * Stands in for `module-pages` registering itself, for the one test that is about the demo.
 *
 * Kept out of the base case on purpose: every other test in this package is about a menu, and a
 * second source of the same records would be a second answer to questions that have one.
 */
class PagesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(LinkSources::class)->register(new PageLinkSource);
    }
}
