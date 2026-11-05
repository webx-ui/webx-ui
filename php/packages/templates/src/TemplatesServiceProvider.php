<?php

declare(strict_types=1);

namespace WebxUi\Templates;

use Illuminate\Support\ServiceProvider;

/**
 * The words of a refused template, under `webx-templates::errors`. A package that shows them
 * registers this provider itself (`$this->app->register()`), so they are there whether the site
 * found the library by discovery or a test listed the providers by hand.
 */
class TemplatesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-templates');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../lang' => lang_path('vendor/webx-templates'),
            ], 'webx-templates-lang');
        }
    }
}
