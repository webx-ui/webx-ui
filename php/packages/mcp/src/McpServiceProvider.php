<?php

declare(strict_types=1);

namespace WebxUi\Mcp;

use DateInterval;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\McpServiceProvider as LaravelMcpServiceProvider;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Passport;
use WebxUi\Admin\Gate\Openings;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Mcp\Console\ListToolsCommand;
use WebxUi\Mcp\Console\PruneCallsCommand;
use WebxUi\Mcp\Grants\Grants;
use WebxUi\Mcp\Http\Middleware\AuthenticateAgent;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\WebxServer;

class McpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-mcp.php', 'webx-mcp');

        // Registered here rather than left to package discovery, so that a Testbench or an
        // application that lists providers by hand gets the transport with the contract.
        $this->app->register(LaravelMcpServiceProvider::class);

        $this->app->singleton(
            ToolRegistry::class,
            static fn ($app): ToolRegistry => new ToolRegistry($app->make(ModuleRegistry::class)),
        );

        // Scoped, not a singleton: it remembers what it looked up, and a memory that outlived
        // the request would keep a connection alive after the person switched it off.
        $this->app->scoped(Grants::class);

        $this->configurePassport();
    }

    public function boot(): void
    {
        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('webx.mcp-auth', AuthenticateAgent::class);

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerTokenGuard();
        $this->registerOAuthRoutes($router);
        $this->registerServers();
        $this->registerPruneSchedule();
        $this->registerGateOpenings();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([ListToolsCommand::class, PruneCallsCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-mcp.php' => config_path('webx-mcp.php'),
        ], 'webx-mcp-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-mcp'),
        ], 'webx-mcp-views');
    }

    /**
     * The guard the HTTP door asks, over the panel's own people.
     *
     * Registered in boot() rather than register(): the provider it names belongs to
     * `webx-ui/module-auth`, which registers after this package — it depends on it — so in
     * register() there would be nothing to point at yet. Nothing reads a guard before a
     * request, so the later moment costs nothing.
     */
    private function registerTokenGuard(): void
    {
        if (! class_exists(Passport::class)) {
            return;
        }

        $config = $this->app->make('config');
        $guard = (string) $config->get('webx-mcp.guard', 'api');
        $provider = $config->get('webx-auth.provider');

        // Anything the application has already defined under this name wins — it knows
        // something we do not — and without a panel to belong to there is nobody to let in.
        if ($guard === '' || $config->has("auth.guards.{$guard}") || ! is_string($provider)) {
            return;
        }

        if (! $config->has("auth.providers.{$provider}")) {
            return;
        }

        $config->set("auth.guards.{$guard}", ['driver' => 'passport', 'provider' => $provider]);
    }

    /**
     * Point Passport at the panel, and close the two doors it and `laravel/mcp` ship open.
     *
     * In register(), not boot(), because of one line in Passport's own routes file: the
     * middleware of `POST /oauth/authorize` is `'auth:'.config('passport.guard')`, read the
     * moment its provider boots and baked into the route. Setting the guard afterwards
     * leaves the approve step asking the site's guard about an administrator it has never
     * heard of — while the consent page before it, which resolves the guard lazily, shows
     * the right person and looks entirely correct.
     */
    private function configurePassport(): void
    {
        $config = $this->app->make('config');

        if (! class_exists(Passport::class) || $config->get('webx-mcp.oauth.enabled') === false) {
            return;
        }

        $guard = $config->get('webx-mcp.oauth.guard');

        if (is_string($guard) && $guard !== '') {
            $config->set('passport.guard', $guard);
        }

        foreach (['redirect_domains', 'custom_schemes'] as $key) {
            $value = $config->get("webx-mcp.oauth.{$key}");

            if (is_array($value)) {
                $config->set("mcp.{$key}", $value);
            }
        }

        Passport::tokensExpireIn(new DateInterval('PT'.(int) $config->get('webx-mcp.oauth.access_token_hours', 1).'H'));
        Passport::refreshTokensExpireIn(new DateInterval('P'.(int) $config->get('webx-mcp.oauth.refresh_token_days', 30).'D'));

        // A client that asks for no scope at all would otherwise be handed a token that can
        // do nothing, and every tool would refuse it for a scope it was never offered.
        if (Passport::defaultScopes() === []) {
            Passport::defaultScopes([Registrar::OAUTH_SCOPE]);
        }

        // Also done by `oauthRoutes()`, and needed even when the routes come from the cache:
        // an authorization request is refused for a scope Passport has not been told about.
        Registrar::ensureMcpScope();
    }

    /**
     * Past the password over a site in testing: the server and the OAuth dance in front of it.
     *
     * An agent cannot answer a Basic dialog, and a client registering itself has no pair to
     * send — closed, the connector fails with an error that names neither the site nor the
     * gate. The server under the panel's API is let through with the panel; one moved to a
     * path of its own is let through here. Discovery lives under `/.well-known`, which the
     * frame opens itself. Read per request, like everything the gate asks.
     */
    private function registerGateOpenings(): void
    {
        $config = $this->app->make('config');
        $openings = $this->app->make(Openings::class);

        $openings->allow(static fn (Request $request): bool => $openings->under($request, $config->get('webx-mcp.path')));

        $openings->allow(static fn (Request $request): bool => class_exists(Passport::class)
            && $config->get('webx-mcp.oauth.enabled') !== false
            && $openings->under($request, $config->get('webx-mcp.oauth.prefix', 'oauth')));
    }

    /**
     * The way in for an agent whose person has only an address to paste: discovery, dynamic
     * client registration and PKCE, all of it written by `laravel/mcp`.
     */
    private function registerOAuthRoutes(Router $router): void
    {
        $config = $this->app->make('config');

        if (! class_exists(Passport::class) || $config->get('webx-mcp.oauth.enabled') === false) {
            return;
        }

        // Passport ships no consent page — it asks the application for one, and without it
        // the flow ends in "not instantiable" on the last screen the person sees. This is
        // the plain one; a panel with screens of its own replaces the binding afterwards,
        // which is what `Passport::authorizationView()` does.
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-mcp');

        if (! $this->app->bound(AuthorizationViewResponse::class)) {
            Passport::authorizationView('webx-mcp::authorize');
        }

        $prefix = trim((string) $config->get('webx-mcp.oauth.prefix', 'oauth'), '/');

        // Defined whether or not the routes are registered here, because a cached route still
        // names its limiter and `ThrottleRequests` given a name nothing answers to reads it as
        // a number instead — zero — and refuses every request. Loudly, but only on a deployed
        // site, and only once somebody ran `route:cache`.
        $limiters = [
            $prefix.'/register' => ['webx-mcp-register', $config->get('webx-mcp.oauth.register_throttle')],
            $prefix.'/token' => ['webx-mcp-token', $config->get('webx-mcp.oauth.token_throttle')],
        ];

        foreach ($limiters as [$limiter, $throttle]) {
            $this->defineLimiter($limiter, $throttle);
        }

        if ($this->routesAreCached()) {
            return;
        }

        Mcp::oauthRoutes($prefix);

        foreach ($limiters as $uri => [$limiter, $throttle]) {
            if (is_string($throttle) && $throttle !== '') {
                $this->throttle($router, $uri, $limiter);
            }
        }
    }

    /**
     * A counter of one route's own.
     *
     * Not `throttle:10,60`: for a caller who is not signed in, Laravel keys that counter by
     * address alone, so every throttled route in the application shares it and the lowest
     * limit among them decides. Registration is the loud one and the panel's sign-in form is
     * the low one, which makes "a stranger registering clients" and "an administrator locked
     * out of their own panel" the same event. A named limiter is how a route gets its own.
     *
     * @param  mixed  $throttle  attempts and minutes, `10,60`; anything else defines nothing
     */
    private function defineLimiter(string $limiter, mixed $throttle): void
    {
        if (! is_string($throttle) || ! preg_match('/^(\d+),(\d+)$/', $throttle, $limits)) {
            return;
        }

        RateLimiter::for($limiter, static fn (Request $request): Limit => Limit::perMinutes(
            (int) $limits[2],
            (int) $limits[1],
        )->by($limiter.'|'.$request->ip()));
    }

    /**
     * Put the route at this address on that limiter.
     *
     * Registration opens before anybody has signed in, so a limit is all there is to have.
     */
    private function throttle(Router $router, string $uri, string $limiter): void
    {
        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (! $route instanceof Route || $route->uri() !== $uri || ! in_array('POST', $route->methods(), true)) {
                continue;
            }

            // Passport's own token route carries a bare `throttle`, which is the shared
            // counter this is here to get off. Only that exact spelling goes — a limit the
            // application put there itself is its business.
            $action = $route->getAction();
            $action['middleware'] = array_values(array_filter(
                (array) ($action['middleware'] ?? []),
                static fn (mixed $middleware): bool => $middleware !== 'throttle',
            ));

            $route->setAction($action);
            $route->middleware('throttle:'.$limiter);
        }
    }

    /**
     * The one server, over HTTP next to the panel's API and over stdio by name. The routes
     * are skipped when the router's cache is in use, the way `loadRoutesFrom()` skips: they
     * are in the cache already.
     */
    private function registerServers(): void
    {
        $config = $this->app->make('config');
        $path = $config->get('webx-mcp.path');

        if ($path !== false && ! $this->routesAreCached()) {
            $path = is_string($path) && $path !== ''
                ? $path
                : trim((string) $config->get('webx-admin.api_path', 'api/cms'), '/').'/mcp';

            $middleware = $config->get('webx-mcp.middleware', []);

            Mcp::web($path, WebxServer::class)
                ->middleware(is_array($middleware) ? $middleware : [$middleware])
                ->name('webx.mcp');
        }

        $local = $config->get('webx-mcp.local');

        if (is_string($local) && $local !== '') {
            Mcp::local($local, WebxServer::class);
        }
    }

    /**
     * The call log kept to its retention, nightly, by the package rather than by the site —
     * the way the nightly dump is. `callAfterResolving` because the scheduler is built on the
     * first console command that needs one, not during boot.
     */
    private function registerPruneSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $config = $this->app->make('config');
            $days = $config->get('webx-mcp.calls.days');

            if (! $config->get('webx-mcp.calls.enabled', true) || ! is_int($days) || $days < 1) {
                return;
            }

            $schedule->command(PruneCallsCommand::class)
                ->daily()
                ->onOneServer();
        });
    }

    private function routesAreCached(): bool
    {
        return $this->app instanceof CachesRoutes && $this->app->routesAreCached();
    }
}
