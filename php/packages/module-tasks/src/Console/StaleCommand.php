<?php

declare(strict_types=1);

namespace WebxUi\Tasks\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use WebxUi\Tasks\Models\Stage;
use WebxUi\Tasks\Notifications\Notices;
use WebxUi\Tasks\Notifications\NotificationSettings;
use WebxUi\Tasks\Notifications\Notifier;
use WebxUi\Tasks\Notifications\SendNotice;
use WebxUi\Tasks\Workflow\StageStatus;

/**
 * Stages in progress longer than the limit (§6a, `stage.stale`): an agent that fell over says
 * nothing, so the clock says it for it. Once per stint — taking the stage again starts a new one.
 * Runs on the scheduler; by hand it is the same.
 */
final class StaleCommand extends Command
{
    protected $signature = 'webx:tasks:stale';

    protected $description = 'Notify about stages in progress longer than the limit';

    public function handle(NotificationSettings $settings, Notifier $notifier, Notices $notices): int
    {
        if (! $settings->enabled('stage.stale') || ! $notifier->configured()) {
            return self::SUCCESS;
        }

        $stages = Stage::query()
            ->where('status', StageStatus::InProgress->value)
            ->whereNull('stale_notified_at')
            ->where('status_changed_at', '<=', Carbon::now()->subHours($settings->staleHours()))
            ->whereHas('task')
            ->get();

        foreach ($stages as $stage) {
            SendNotice::dispatch($notices->forStage('stage.stale', $stage)->toArray());
            $stage->forceFill(['stale_notified_at' => Carbon::now()])->save();
        }

        $this->components->info("Stages reported as hung: {$stages->count()}");

        return self::SUCCESS;
    }
}
