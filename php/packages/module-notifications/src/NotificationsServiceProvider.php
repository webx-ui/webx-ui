<?php

declare(strict_types=1);

namespace WebxUi\Notifications;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Admin\History\HistoryTypes;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Notifications\Console\PruneDeliveriesCommand;
use WebxUi\Notifications\Doctor\NotificationsCheck;
use WebxUi\Notifications\Email\EmailProvider;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Panel\NotificationsModule;
use WebxUi\Notifications\Telegram\TelegramProvider;

/**
 * Channels of notifications (the notifications spec): a registry of providers with Telegram and
 * e-mail in it, a registry of the events consumers declare, `Notifications::send()` for them to
 * call, the queue job, the log and its trim, a section in the panel and a check in webx:doctor.
 *
 * A consumer declares its events without requiring this package:
 *
 *     if (class_exists(NotificationEvents::class)) {
 *         $this->callAfterResolving(NotificationEvents::class, fn (NotificationEvents $events) => $events->register(…));
 *     }
 */
class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-notifications.php', 'webx-notifications');

        $this->app->singleton(NotificationEvents::class);
        $this->app->singleton(Providers::class, function (): Providers {
            $providers = new Providers;
            $providers->register($this->app->make(TelegramProvider::class));
            $providers->register($this->app->make(EmailProvider::class));

            return $providers;
        });

        $this->callAfterResolving(DoctorChecks::class, static function (DoctorChecks $checks): void {
            $checks->register(NotificationsCheck::class);
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PruneDeliveriesCommand::class)
                ->dailyAt((string) $this->app->make('config')->get('webx-notifications.log.prune_at', '03:50'))
                ->onOneServer()
                ->withoutOverlapping();
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-notifications');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->registerScreens();
        $this->registerHistory();

        $this->app->make(ModuleRegistry::class)->register($this->app->make(NotificationsModule::class));

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([PruneDeliveriesCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-notifications.php' => config_path('webx-notifications.php'),
        ], 'webx-notifications-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-notifications'),
        ], 'webx-notifications-lang');
    }

    /**
     * The channel's form: the common part from the JSON, each provider's settings laid over it
     * when it is built — a provider registered by a site appears without a line of Vue — and the
     * events to choose from.
     */
    private function registerScreens(): void
    {
        $screens = $this->app->make(ScreenRegistry::class);

        $screens->register(Channel::SCREEN, __DIR__.'/../resources/screens/channel.json');
        $screens->extend(Channel::SCREEN, function (): array {
            $patch = [
                ['op' => 'set', 'target' => 'provider', 'props' => ['options' => array_values(array_map(
                    static fn (Contracts\Provider $provider): array => ['value' => $provider->key(), 'label' => (string) __($provider->label())],
                    $this->app->make(Providers::class)->all(),
                ))]],
                ['op' => 'set', 'target' => 'event', 'props' => ['options' => array_values(array_map(
                    static fn (Events\EventDefinition $event): array => ['value' => $event->key, 'label' => (string) __($event->label)],
                    $this->app->make(NotificationEvents::class)->all(),
                ))]],
            ];

            foreach ($this->app->make(Providers::class)->all() as $provider) {
                foreach ($provider->screen() as $node) {
                    $patch[] = ['op' => 'add', 'target' => 'connection', 'node' => $node];
                }
            }

            return $patch;
        });
    }

    /** Who changed which channel and how; the secrets as a mask (§3.2). */
    private function registerHistory(): void
    {
        $fields = [];

        foreach (['name', 'provider', 'event', 'credentials', 'options', 'recipients', 'subject', 'template', 'enabled'] as $field) {
            $fields[$field] = 'webx-notifications::screen.'.$field;
        }

        $this->app->make(HistoryTypes::class)->register(
            Channel::TYPE,
            Channel::class,
            fields: $fields,
            permission: [NotificationsModule::VIEW, NotificationsModule::MANAGE],
            module: NotificationsModule::ID,
            label: 'webx-notifications::module.channel',
        );
    }
}
