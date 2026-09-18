<?php

declare(strict_types=1);

namespace WebxUi\Inbox;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Inbox\Panel\InboxModule;
use WebxUi\Inbox\Support\Forms;

class InboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-inbox.php', 'webx-inbox');

        // Looked up by the rate limiter and then by the controller, within one request.
        $this->app->scoped(Forms::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-inbox');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-inbox');

        $this->registerRateLimiter();

        $this->loadRoutesFrom(__DIR__.'/../routes/public.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->app->make(ModuleRegistry::class)->register($this->app->make(InboxModule::class));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-inbox.php' => config_path('webx-inbox.php'),
        ], 'webx-inbox-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-inbox'),
        ], 'webx-inbox-lang');

        // The letter, mostly: every client wants their own letterhead on it, and publishing
        // the view is how they get one without this package knowing about it.
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-inbox'),
        ], 'webx-inbox-views');
    }

    /**
     * How often one address may submit one form (§7).
     *
     * A named limiter rather than `throttle:5,1` on the route, because the number belongs to
     * the form: a support form that people send twice in a row is not a subscribe box. The
     * form is already in memory by the time this runs — the limiter and the controller share
     * one lookup — so reading its setting costs nothing.
     */
    private function registerRateLimiter(): void
    {
        RateLimiter::for('webx-inbox', function (Request $request): Limit {
            $slug = (string) $request->route('slug');
            $form = $this->app->make(Forms::class)->enabled($slug);

            $perMinute = $form !== null
                ? (int) $form->antispam('throttle')
                : (int) $this->app->make('config')->get('webx-inbox.antispam.throttle', 5);

            // Zero turns it off, which is a thing a site behind its own rate limiting wants.
            return $perMinute <= 0
                ? Limit::none()
                : Limit::perMinute($perMinute)->by($request->ip().'|'.$slug);
        });
    }
}
