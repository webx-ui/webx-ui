<?php

declare(strict_types=1);

namespace WebxUi\Users;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Events\ResponsePrepared;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Visitors\Visitors;
use WebxUi\Compositions\Compositions;
use WebxUi\Users\Account\AccountMenu;
use WebxUi\Users\Account\AccountRoutes;
use WebxUi\Users\Account\AccountSection;
use WebxUi\Users\Account\DeletionWarnings;
use WebxUi\Users\Auth\UserProvider;
use WebxUi\Users\Console\IndexFieldCommand;
use WebxUi\Users\Console\PruneCommand;
use WebxUi\Users\Doctor\FieldsCheck;
use WebxUi\Users\Doctor\MailCheck;
use WebxUi\Users\Doctor\UsersTableCheck;
use WebxUi\Users\Fields\FieldCatalog;
use WebxUi\Users\Fields\UserFields;
use WebxUi\Users\Http\TrackVisitor;
use WebxUi\Users\Models\Group;
use WebxUi\Users\Models\ProviderSettings;
use WebxUi\Users\Models\User;
use WebxUi\Users\Panel\GroupsModule;
use WebxUi\Users\Panel\SettingsModule;
use WebxUi\Users\Panel\SettingsScreen;
use WebxUi\Users\Panel\UsersModule;
use WebxUi\Users\Social\DevProvider;
use WebxUi\Users\Social\FacebookProvider;
use WebxUi\Users\Social\GoogleProvider;
use WebxUi\Users\Social\ProviderKeys;
use WebxUi\Users\Social\SocialProviders;

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

        // The fields of the site (§10): modules register theirs in `boot()`, the config is read on
        // the first question.
        $this->app->singleton(UserFields::class);

        // The sections of the account and what a module says before one is deleted (§11.2, §14.1).
        $this->app->singleton(AccountMenu::class);
        $this->app->singleton(DeletionWarnings::class);

        // Social networks (§9): the registry for the whole process, the keys read once a request.
        $this->app->singleton(SocialProviders::class);
        $this->app->scoped(ProviderKeys::class);
        $this->app->scoped(SignInDialog::class);
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
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerPanel();
        $this->registerScreens();
        $this->registerHistory();

        $this->callAfterResolving(AccountMenu::class, static function (AccountMenu $menu): void {
            $menu->register(new AccountSection(
                id: AccountMenu::PROFILE, label: 'webx-users::account.menu.profile', route: 'webx-users.profile',
                icon: 'user', position: 10, active: 'webx-users.profile*',
            ));
            $menu->register(new AccountSection(
                id: AccountMenu::SECURITY, label: 'webx-users::account.menu.security', route: 'webx-users.security',
                icon: 'lock', position: 90, active: 'webx-users.security*',
            ));
        });

        $this->callAfterResolving(SocialProviders::class, function (SocialProviders $providers): void {
            $providers->register(new GoogleProvider);
            $providers->register(new FacebookProvider);

            // A made-up network for a machine of development: anybody may say any address on its
            // page, so never anywhere else (and shown only with WEBX_USERS_DEV_ENABLED=true).
            if ($this->app->environment('local')) {
                $providers->register(new DevProvider);
            }
        });

        // A provider may come back by POST from its own site (Apple does): `state` guards the
        // return, not the CSRF token the visitor's browser never sent there (§15).
        PreventRequestForgery::except(AccountRoutes::prefix().'/auth/*/callback');

        // `users.auth` — the sign-in and the registration; `users.account` — the account (§11.4).
        Compositions::handles('users', ['users.account', 'users.auth']);
        // «Confirm your e-mail» first in the `main` of the layout (§8.3).
        Compositions::load(__DIR__.'/../resources/compositions');

        // Every page keeps the cookie `webx_visitor` and the stamp of the password in step (§7,
        // §11.6); the script of a cached page reads the cookie, so it is not encrypted.
        EncryptCookies::except(TrackVisitor::COOKIE);
        $this->trackVisitors();
        $this->printSignInDialog();

        $this->callAfterResolving(DoctorChecks::class, static function (DoctorChecks $checks): void {
            $checks->register(UsersTableCheck::class);
            $checks->register(MailCheck::class);
            $checks->register(FieldsCheck::class);
        });

        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('webx:users:prune')->daily()->name('webx-users:prune');
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([PruneCommand::class, IndexFieldCommand::class]);

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
     * «Customers» (spec §16, decision 20): three entries in a menu group of their own — the
     * people, the groups, the settings. The group is added to the panel's config at boot, as a
     * site that published `webx-admin.php` keeps its own copy of the list.
     */
    private function registerPanel(): void
    {
        $config = $this->app->make('config');
        /** @var array<string, mixed> $groups */
        $groups = (array) $config->get('webx-admin.groups', []);

        if (! array_key_exists(UsersModule::GROUP, $groups)) {
            $config->set('webx-admin.groups', [
                ...$groups,
                UsersModule::GROUP => ['title' => 'webx-users::module.title', 'icon' => 'users', 'order' => 400],
            ]);
        }

        $modules = $this->app->make(ModuleRegistry::class);

        foreach ([UsersModule::class, GroupsModule::class, SettingsModule::class] as $module) {
            $modules->register($this->app->make($module));
        }
    }

    /**
     * The card of a visitor and the settings, each completed when its tree is built: the groups
     * to tick, the site's fields with the place `panel` in `project-fields` (§10.4), a row of
     * «required» for every field the visitor fills in.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);

        $screens->register(User::SCREEN, __DIR__.'/../resources/screens/form.json');
        $screens->extend(User::SCREEN, function (): array {
            $patch = [['op' => 'set', 'target' => 'groups', 'props' => ['options' => Group::query()->orderBy('name')->get(['code', 'name'])->map(
                static fn (Group $group): array => ['value' => $group->code, 'label' => $group->name],
            )->all()]]];
            $nodes = $this->app->make(FieldCatalog::class)->nodes();

            if ($nodes !== []) {
                $patch[] = ['op' => 'remove', 'target' => 'project-fields-empty'];
            }

            foreach ($nodes as $node) {
                $patch[] = ['op' => 'add', 'target' => FieldCatalog::CARD, 'node' => $node];
            }

            return $patch;
        });

        $screens->register(SettingsScreen::NAME, __DIR__.'/../resources/screens/settings.json');
        $screens->extend(SettingsScreen::NAME, fn (): array => $this->app->make(SettingsScreen::class)->patch());
    }

    /** Who changed which visitor, group and provider; a secret and a password as a mask (§16). */
    private function registerHistory(): void
    {
        $types = $this->app->make(HistoryTypes::class);
        $read = [UsersModule::VIEW, UsersModule::MANAGE];

        $fields = [];

        foreach (['email', 'first_name', 'last_name', 'phone', 'email_verified_at', 'blocked_at', 'password', 'locale', 'extra'] as $field) {
            $fields[$field] = 'webx-users::screen.history-'.str_replace('_', '-', $field);
        }

        $types->register(User::TYPE, User::class, fields: $fields, permission: $read, module: UsersModule::ID, label: 'webx-users::screen.history-user');
        $types->register(Group::TYPE, Group::class, fields: [
            'code' => 'webx-users::screen.group-code',
            'name' => 'webx-users::screen.group-name',
        ], permission: $read, module: UsersModule::ID, label: 'webx-users::screen.history-group');
        $types->register(ProviderSettings::TYPE, ProviderSettings::class, fields: [
            'enabled' => 'webx-users::screen.provider-enabled',
            'options' => 'webx-users::screen.history-options',
            'credentials' => 'webx-users::screen.history-credentials',
        ], permission: [UsersModule::SETTINGS], module: UsersModule::ID, label: 'webx-users::screen.history-provider');
    }

    /**
     * The dialog of the sign-in before `</body>` of a page that opens it (§12.1), on the first of
     * the two `ResponsePrepared`: the session still has the flash of a form of the dialog sent
     * without JavaScript, which opens it on its view — as the widgets put in their forms in a
     * dialog (WIDGETS §6.4). Forgotten when the request is handled: a test's next request, a
     * long-lived worker's next page print it again.
     */
    private function printSignInDialog(): void
    {
        $events = $this->app->make(Dispatcher::class);

        $events->listen(ResponsePrepared::class, function (ResponsePrepared $event): void {
            $response = $event->response;

            if (! $response instanceof Response || $response instanceof StreamedResponse || $response instanceof BinaryFileResponse
                || $response instanceof JsonResponse || $response instanceof RedirectResponse || ! $response->isSuccessful()) {
                return;
            }

            $type = (string) $response->headers->get('Content-Type', '');
            $content = $response->getContent();

            if (($type === '' || str_contains($type, 'html')) && is_string($content) && str_contains($content, '</body>')) {
                $response->setContent($this->app->make(SignInDialog::class)->into($content));
            }
        });

        $events->listen(RequestHandled::class, fn () => $this->app->make(SignInDialog::class)->flush());
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
