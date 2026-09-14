<?php

declare(strict_types=1);

namespace WebxUi\Media;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Media\Screens\MediaFieldType;

class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-media.php', 'webx-media');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-media');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->app->make(ModuleRegistry::class)->register(new MediaModule);

        // What a screen means by `wx-media`, on the server: the key the field stores and the
        // address the site reads. The front end registers the component under the same name.
        $this->app->make(FieldTypes::class)->register('wx-media', $this->app->make(MediaFieldType::class));

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
