<?php

declare(strict_types=1);

namespace WebxUi\Menu\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Links\LinkSources;

/**
 * What a content module does in one line, so that the menu has something to point at.
 *
 * A provider and not a line in `setUp()`, because when the source is registered decides whether
 * the cache hears about it: the subscription is taken once the application has booted, and a
 * source registered after that would never be listened to. Getting that wrong in a test would
 * look exactly like the feature working.
 */
class ThingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(LinkSources::class)->register(new ThingLinkSource);
    }
}
