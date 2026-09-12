<?php

declare(strict_types=1);

namespace WebxUi\Auth;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Auth\Console\CreateAdminCommand;
use WebxUi\Auth\Events\AdminLoggedIn;
use WebxUi\Auth\Events\AdminLoginFailed;
use WebxUi\Auth\Http\Middleware\Authenticate;
use WebxUi\Auth\Http\Middleware\EnsurePermission;
use WebxUi\Auth\Listeners\RecordSignIn;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-auth.php', 'webx-auth');

        $this->registerGuard();
        $this->protectPanel();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-auth');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        /** @var Router $router */
        $router = $this->app->make('router');
        $router->aliasMiddleware('cms.auth', Authenticate::class);
        $router->aliasMiddleware('cms.can', EnsurePermission::class);

        $this->app->make(ModuleRegistry::class)->register(new AuthModule);

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

        $this->commands([CreateAdminCommand::class]);
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
     * this lands before webx-ui/admin reads the value while declaring its routes.
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
