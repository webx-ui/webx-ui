<?php

declare(strict_types=1);

namespace WebxUi\Media;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Http\Events\RequestHandled;
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
        $field = $this->app->make(MediaFieldType::class);
        $this->app->make(FieldTypes::class)->register('wx-media', $field);

        // The field remembers the files one response asked about; in a process that serves many
        // responses that memory would outlive the library it describes.
        $this->app->make(Dispatcher::class)->listen(RequestHandled::class, static function () use ($field): void {
            $field->flush();
        });

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
