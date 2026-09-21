<?php

declare(strict_types=1);

namespace WebxUi\Auth;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Auth\Consent\ConsentScreen;
use WebxUi\Auth\Console\CreateAdminCommand;
use WebxUi\Auth\Events\AdminLoggedIn;
use WebxUi\Auth\Events\AdminLoginFailed;
use WebxUi\Auth\Http\Middleware\Authenticate;
use WebxUi\Auth\Http\Middleware\EnsurePermission;
use WebxUi\Auth\Http\Middleware\SignInBeforeConsent;
use WebxUi\Auth\Listeners\RecordSignIn;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-auth.php', 'webx-auth');

        $this->registerGuard();
        $this->protectPanel();
        $this->registerConsentScreen();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-auth');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-auth');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('cms.auth', Authenticate::class);
        $router->aliasMiddleware('cms.can', EnsurePermission::class);

        $this->routeConsent($router);

        $registry = $this->app->make(ModuleRegistry::class);
        $registry->register(new AuthModule);

        $connect = new ConnectModule($this->app->make('config'));

        if ($connect->available()) {
            $registry->register($connect);
        }

        /** @var Dispatcher $events */
        $events = $this->app->make('events');
        $events->listen(AdminLoggedIn::class, [RecordSignIn::class, 'handleSuccess']);
        $events->listen(AdminLoginFailed::class, [RecordSignIn::class, 'handleFailure']);

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-auth.php' => config_path('webx-auth.php'),
        ], 'webx-auth-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-auth'),
        ], 'webx-auth-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-auth'),
        ], 'webx-auth-views');

        $this->commands([CreateAdminCommand::class]);
    }

    /**
     * Our consent screen instead of the plain one `webx-ui/mcp` binds when nobody else has.
     *
     * In register() so that it is there by the time that package boots and looks: the two
     * providers register in dependency order, and this one comes second.
     */
    private function registerConsentScreen(): void
    {
        if (! $this->consentEnabled()) {
            return;
        }

        Passport::authorizationView(
            fn (array $parameters) => $this->app->make(ConsentScreen::class)($parameters),
        );
    }

    /**
     * The routes the consent screen posts to, and the sign-in in front of Passport's own.
     *
     * Passport sends a guest to a route named `login`, which no site with this panel has —
     * the panel draws its sign-in itself. The middleware goes on Passport's route here, at
     * boot, which is also when the route cache is written: a cached route already carries it.
     */
    private function routeConsent(Router $router): void
    {
        if (! $this->consentEnabled() || $this->routesAreCached()) {
            return;
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/consent.php');

        $authorize = $router->getRoutes()->getByName('passport.authorizations.authorize');

        // The panel's language for the person reading it, the way every panel page gets it.
        $authorize?->middleware([SignInBeforeConsent::class, 'webx.panel-locale']);
    }

    private function consentEnabled(): bool
    {
        return class_exists(Passport::class)
            && $this->app->make('config')->get('webx-mcp.oauth.enabled') !== false;
    }

    private function routesAreCached(): bool
    {
        return $this->app instanceof CachesRoutes && $this->app->routesAreCached();
    }

    /**
     * Administrators get their own guard over their own table. Anything the application has
     * already defined under these names wins — it knows something we do not.
     */
    private function registerGuard(): void
    {
        $config = $this->app->make('config');

        $guard = (string) $config->get('webx-auth.guard');
        $provider = (string) $config->get('webx-auth.provider');

        if (! $config->has("auth.providers.{$provider}")) {
            $config->set("auth.providers.{$provider}", [
                'driver' => 'eloquent',
                'model' => $config->get('webx-auth.model'),
            ]);
        }

        if (! $config->has("auth.guards.{$guard}")) {
            $config->set("auth.guards.{$guard}", [
                'driver' => 'session',
                'provider' => $provider,
            ]);
        }
    }

    /**
     * Installing this package is what closes the panel, rather than remembering to.
     *
     * It runs in register() on purpose: every provider is registered before any is booted, so
     * this lands before webx-ui/module-admin reads the value while declaring its routes.
     */
    private function protectPanel(): void
    {
        $config = $this->app->make('config');

        if (! $config->get('webx-auth.protect_panel', true)) {
            return;
        }

        $config->set('webx-admin.api_middleware', $config->get('webx-auth.panel_api_middleware'));
    }
}
