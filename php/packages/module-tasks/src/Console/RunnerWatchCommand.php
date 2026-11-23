<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use WebxUi\Tasks\Notifications\Notices;
use WebxUi\Tasks\Notifications\NotificationSettings;
use WebxUi\Tasks\Notifications\Notifier;
use WebxUi\Tasks\Notifications\SendNotice;
use WebxUi\Tasks\Runner\Heartbeats;

/**
 * A watcher that stopped beating (§14.3, `runner.silent`): it cannot say it fell over, so the clock
 * says it for it — once per silence; the next beat re-arms the alarm. Runs every minute on the
 * scheduler; the notice leaves through the queue, like every other.
 */
final class RunnerWatchCommand extends Command
{
    protected $signature = 'webx:tasks:runner-watch';

    protected $description = 'Notify about a task watcher that stopped sending heartbeats';

    public function handle(Heartbeats $heartbeats, NotificationSettings $settings, Notifier $notifier, Notices $notices): int
    {
        if (! $settings->enabled('runner.silent') || ! $notifier->configured()) {
            return self::SUCCESS;
        }

        $silent = $heartbeats->newlySilent();

        foreach ($silent as $beat) {
            SendNotice::dispatch($notices->forRunner($beat)->toArray());
            $beat->forceFill(['silent_notified_at' => Carbon::now()])->save();
        }

        $this->components->info('Silent watchers reported: '.count($silent));

        return self::SUCCESS;
    }
}
