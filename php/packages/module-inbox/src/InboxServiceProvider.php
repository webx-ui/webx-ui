<?php

declare(strict_types=1);

namespace WebxUi\Inbox;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Inbox\Panel\InboxModule;
use WebxUi\Inbox\Rendering\Assets;
use WebxUi\Inbox\Rendering\FormTag;
use WebxUi\Inbox\Support\Forms;

class InboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-inbox.php', 'webx-inbox');

        // Looked up by the rate limiter and then by the controller, within one request.
        $this->app->scoped(Forms::class);

        // What a response has already printed, which is a fact about the response.
        $this->app->scoped(Assets::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-inbox');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-inbox');

        $this->registerRateLimiter();

        // `<x-webx-form slug="contact" />` — the whole public half of the module, as one tag
        // (§10). A class component rather than an anonymous one, because what it prints is
        // decided by a form, a guard and a captcha that all come out of the container.
        Blade::component(FormTag::class, 'webx-form');

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

        // The form on the site and the letter. Both ship as the least markup that works, and
        // both are meant to be published and rewritten: the reference implementation kept the
        // intake and replaced every control on the page (§2.15).
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-inbox'),
        ], 'webx-inbox-views');

        // For a site that would rather have the script in its own bundle than as one more
        // request. It then switches `webx-inbox.script` off, and the form stops linking ours.
        $this->publishes([
            __DIR__.'/../resources/js' => resource_path('js/vendor/webx-inbox'),
        ], 'webx-inbox-assets');
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
