<?php

declare(strict_types=1);

namespace WebxUi\Media;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\ModuleRegistry;

class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-media.php', 'webx-media');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-media');

        $this->app->make(ModuleRegistry::class)->register(new MediaModule);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-media.php' => config_path('webx-media.php'),
        ], 'webx-media-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-media'),
        ], 'webx-media-lang');
    }
}
