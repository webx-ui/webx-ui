<?php

declare(strict_types=1);

namespace WebxUi\Users;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\Visitors\Visitors;
use WebxUi\Compositions\Compositions;
use WebxUi\Users\Auth\UserProvider;
use WebxUi\Users\Doctor\UsersTableCheck;
use WebxUi\Users\Models\User;

/**
 * Visitors of the site (spec §1): the table `users`, the guard `site`, the sign-in and the
 * registration, and the seam of the panel — `Visitors` — answered by them.
 *
 * The guard and its provider are added to `auth` in `register()`, before anything asks for a
 * guard, and only where the site has not declared them itself; the default guard is the site's
 * and stays so. `Visitors` is bound here: the panel's `singletonIf` of its empty answer gives way
 * whichever provider runs first.
 */
class UsersServiceProvider extends ServiceProvider
{
    public const string PROVIDER = 'webx-users';

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-users.php', 'webx-users');

        $this->registerGuard();

        // Scoped: the visitor of one request is not the next one's in a long-running worker.
        $this->app->scoped(Visitors::class, UserVisitors::class);
    }

    public function boot(): void
    {
        Auth::provider(self::PROVIDER, static fn (Application $app, array $config): UserProvider => new UserProvider(
            $app->make('hash'),
            is_string($config['model'] ?? null) ? $config['model'] : User::class,
        ));

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-users');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'webx-users');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // `users.auth` — the sign-in and the registration; `users.account` — the account (§11.4).
        Compositions::handles('users', ['users.account', 'users.auth']);

        $this->callAfterResolving(DoctorChecks::class, static function (DoctorChecks $checks): void {
            $checks->register(UsersTableCheck::class);
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/webx-users.php' => config_path('webx-users.php'),
        ], 'webx-users-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-users'),
        ], 'webx-users-lang');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/webx-users'),
        ], 'webx-users-views');
    }

    private function registerGuard(): void
    {
        $config = $this->app->make('config');
        $guard = Urls::guard();

        if (! is_array($config->get("auth.guards.{$guard}"))) {
            $config->set("auth.guards.{$guard}", ['driver' => 'session', 'provider' => self::PROVIDER]);
        }

        if (! is_array($config->get('auth.providers.'.self::PROVIDER))) {
            $config->set('auth.providers.'.self::PROVIDER, ['driver' => self::PROVIDER, 'model' => User::class]);
        }
    }
}
