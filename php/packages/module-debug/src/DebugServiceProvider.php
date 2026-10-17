<?php

declare(strict_types=1);

namespace WebxUi\Debug;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Cookie\QueueingFactory as Cookies;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Debug\Collecting\Subscriber;
use WebxUi\Debug\Http\CollectLate;
use WebxUi\Debug\Http\Insertion;
use WebxUi\Debug\Http\StartCollecting;
use WebxUi\Debug\Mcp\HttpPageFetcher;
use WebxUi\Debug\Mcp\PageFetcher;
use WebxUi\Debug\Panel\DebugModule;

/**
 * The debug bar at the bottom of the site's pages (WEBX_UI_DEBUGBAR.md).
 *
 * In production this provider merges its config and stops: no listener, no middleware, no
 * route, no module and so no permission and no MCP tool — a site that ships with the package
 * installed is the site without it (§3.1). Everywhere else it puts one middleware first in the
 * global stack and one last in `web`, both of which look at a request and let it pass unless it
 * says it is an administrator's, and one listener on `RequestHandled` that does nothing unless
 * collecting started. The listeners that do the collecting are subscribed only then
 * ({@see Subscriber}).
 */
class DebugServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-debug.php', 'webx-debug');

        if ($this->production()) {
            return;
        }

        $this->app->singleton(Collector::class);
        $this->app->singleton(Subscriber::class);
        $this->app->singleton(Support\CallSite::class);
        $this->app->singleton(Support\Masker::class);
        $this->app->singleton(Bar::class);
        $this->app->singleton(Insertion::class);
        $this->app->bind(PageFetcher::class, HttpPageFetcher::class);

        // The script's address is under the panel's prefix, and the panel's shell answers every
        // address below it: the route has to be in the list before the shell's, which is
        // registered while the frame boots.
        $this->app->booting(function (): void {
            $app = $this->app;

            if (! ($app instanceof CachesRoutes && $app->routesAreCached())) {
                require __DIR__.'/../routes/web.php';
            }
        });
    }

    public function boot(): void
    {
        if ($this->production()) {
            return;
        }

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-debug');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->app->make(ModuleRegistry::class)->register($this->app->make(DebugModule::class));

        $this->registerMiddleware();
        $this->registerListeners();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-debug.php' => config_path('webx-debug.php'),
        ], 'webx-debug-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-debug'),
        ], 'webx-debug-lang');
    }

    /** Production is decided by the environment alone: no setting turns the bar on there. */
    private function production(): bool
    {
        return $this->app->environment('production');
    }

    private function registerMiddleware(): void
    {
        $kernel = $this->app->make(HttpKernel::class);

        if ($kernel instanceof Kernel) {
            // First of all: what the session, the site's closing and SEO do is part of the page.
            $kernel->prependMiddleware(StartCollecting::class);
        }

        $this->app->make(Router::class)->pushMiddlewareToGroup('web', CollectLate::class);
    }

    private function registerListeners(): void
    {
        $events = $this->app->make(Dispatcher::class);

        // After every middleware: whatever the site does to a page has been done.
        $events->listen(RequestHandled::class, function (RequestHandled $event): void {
            $this->app->make(Insertion::class)->apply($event->request, $event->response);
        });

        // The cookie that lets the next page be collected from its first middleware, set at the
        // door of the panel and taken back at its way out.
        $events->listen(Login::class, function (Login $event): void {
            $access = $this->app->make(Access::class);

            $user = $event->user;

            if ($event->guard === $access->guard() && $access->enabled() && $user instanceof Model && $access->allows($user)) {
                $this->app->make(Cookies::class)->queue($access->mark($user));
            }
        });

        $events->listen(Logout::class, function (Logout $event): void {
            $access = $this->app->make(Access::class);

            if ($event->guard === $access->guard()) {
                $this->app->make(Cookies::class)->queue($access->unmark());
            }
        });
    }
}
