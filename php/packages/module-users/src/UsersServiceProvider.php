<?php

declare(strict_types=1);

namespace WebxUi\Users;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\Visitors\Visitors;
use WebxUi\Compositions\Compositions;
use WebxUi\Users\Auth\UserProvider;
use WebxUi\Users\Console\PruneCommand;
use WebxUi\Users\Doctor\MailCheck;
use WebxUi\Users\Doctor\UsersTableCheck;
use WebxUi\Users\Http\TrackVisitor;
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
        // «Confirm your e-mail» first in the `main` of the layout (§8.3).
        Compositions::load(__DIR__.'/../resources/compositions');

        // Every page keeps the cookie `webx_visitor` and the stamp of the password in step (§7,
        // §11.6); the script of a cached page reads the cookie, so it is not encrypted.
        EncryptCookies::except(TrackVisitor::COOKIE);
        $this->trackVisitors();

        $this->callAfterResolving(DoctorChecks::class, static function (DoctorChecks $checks): void {
            $checks->register(UsersTableCheck::class);
            $checks->register(MailCheck::class);
        });

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('webx:users:prune')->daily()->name('webx-users:prune');
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([PruneCommand::class]);

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

    /**
     * Through the HTTP kernel where there is one: it owns the groups and hands them to the router
     * again, over what was pushed to the router alone.
     */
    private function trackVisitors(): void
    {
        $kernel = $this->app->make(HttpKernel::class);

        if ($kernel instanceof Kernel && array_key_exists('web', $kernel->getMiddlewareGroups())) {
            $kernel->appendMiddlewareToGroup('web', TrackVisitor::class);

            return;
        }

        $this->app->make(Router::class)->pushMiddlewareToGroup('web', TrackVisitor::class);
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
