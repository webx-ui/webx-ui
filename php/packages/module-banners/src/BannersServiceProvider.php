<?php

declare(strict_types=1);

namespace WebxUi\Banners;

use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Banners\Models\Banner;
use WebxUi\Banners\Panel\BannersModule;

/**
 * The banners, their places and the screen they are edited on — and not one public route, not a
 * block, not a view (decision 7). A banner reaches the site through `banners('hero')` in a
 * template of the site, which prints its own markup.
 */
class BannersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-banners.php', 'webx-banners');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-banners');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerScreens();

        $this->app->make(ModuleRegistry::class)->register($this->app->make(BannersModule::class));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-banners.php' => config_path('webx-banners.php'),
        ], 'webx-banners-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-banners'),
        ], 'webx-banners-lang');
    }

    /**
     * The editor of a banner, described, so a project adds a field with a patch — and the button
     * variants laid over it from the config (§5.3). The select has no options in the JSON: this
     * patch is where they come from, so a site adds a variant with one line of config, and the
     * check of the value is the one every `wx-select` already has on the server.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);

        $screens->register(Banner::SCREEN, __DIR__.'/../resources/screens/form.json');
        $screens->extend(Banner::SCREEN, [[
            'op' => 'set',
            'target' => 'button-variant',
            'props' => ['options' => $this->app->make(Variants::class)->options()],
        ]]);
    }
}
