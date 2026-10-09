<?php

declare(strict_types=1);

namespace WebxUi\Themes\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;

/** A module's public views, registered the way every module registers them. */
class FakeModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/module/views', 'webx-fake');
    }
}
