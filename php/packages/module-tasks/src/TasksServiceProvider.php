<?php

declare(strict_types=1);

namespace WebxUi\Tasks;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Support\ServiceProvider;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Admin\Snapshots\SnapshotTables;
use WebxUi\Tasks\Console\StaleCommand;
use WebxUi\Tasks\Events\StageMoved;
use WebxUi\Tasks\Events\TaskStatusChanged;
use WebxUi\Tasks\Notifications\NotificationSettings;
use WebxUi\Tasks\Notifications\Notifier;
use WebxUi\Tasks\Notifications\NotifyPeople;
use WebxUi\Tasks\Notifications\TelegramNotifier;
use WebxUi\Tasks\Panel\TasksModule;
use WebxUi\Tasks\Workflow\ContextPackage;
use WebxUi\Tasks\Workflow\Dispatcher;
use WebxUi\Tasks\Workflow\Journal;
use WebxUi\Tasks\Workflow\Tasks;

/**
 * Projects, tasks, stages and their journal, the section they live in, and the notices a person
 * gets about them.
 *
 * No public route: the site's visitors never see a task. The panel reaches it through
 * `routes/api.php`, agents through the MCP tools of {@see TasksModule}.
 */
class TasksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/webx-tasks.php', 'webx-tasks');

        $this->app->singleton(Journal::class);
        $this->app->singleton(Tasks::class);
        $this->app->singleton(Dispatcher::class);
        $this->app->singleton(ContextPackage::class);
        $this->app->singleton(NotificationSettings::class);

        // Telegram unless the site said otherwise: a binding of its own wins, whichever provider
        // registers first.
        $this->app->bindIf(Notifier::class, TelegramNotifier::class);

        // The work of the site travels with its content: a stand restored from a snapshot has
        // the same queue, journals included.
        $this->callAfterResolving(SnapshotTables::class, static function (SnapshotTables $tables): void {
            $tables->content('tasks_projects', 'tasks_tasks', 'tasks_stages', 'tasks_journal');
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'webx-tasks');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $screens = $this->app->make(ScreenRegistry::class);

        // The form of a task and the section's settings, described, so a project can move or
        // relabel their fields with a patch.
        $screens->register(TasksModule::FORM, __DIR__.'/../resources/screens/form.json');
        $screens->register(NotificationSettings::SCREEN, __DIR__.'/../resources/screens/settings.json');

        $this->app->make(ModuleRegistry::class)->register($this->app->make(TasksModule::class));

        $events = $this->app->make(Events::class);
        $events->listen(StageMoved::class, [NotifyPeople::class, 'stageMoved']);
        $events->listen(TaskStatusChanged::class, [NotifyPeople::class, 'taskChanged']);

        // Hung stages are found by the clock; every quarter of an hour is soon enough for a limit
        // counted in hours.
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule->command('webx:tasks:stale')->everyFifteenMinutes()->withoutOverlapping();
        });

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([StaleCommand::class]);

        $this->publishes([
            __DIR__.'/../config/webx-tasks.php' => config_path('webx-tasks.php'),
        ], 'webx-tasks-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/webx-tasks'),
        ], 'webx-tasks-lang');
    }
}
