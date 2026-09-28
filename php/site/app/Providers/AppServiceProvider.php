<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The layout's header and footer are regions of webx-ui/module-blocks. Without the module
        // the tag still has to compile, and `resources/views/regions/region.blade.php` prints
        // the fallback it names. With the module this line does nothing.
        if (! class_exists(\WebxUi\Blocks\Regions::class)) {
            Blade::anonymousComponentPath(resource_path('views/regions'), 'webx-blocks');
        }
    }
}
