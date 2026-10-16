<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Carbon;
use WebxUi\Notifications\Models\Delivery;

/**
 * Drop delivery log rows older than the configured number of days (§9.1). Put on the schedule by
 * the package, daily: the log holds what visitors typed, and it is not kept for nothing.
 */
final class PruneDeliveriesCommand extends Command
{
    protected $signature = 'webx:notifications:prune {--days= : Older than this many days; 0 keeps everything} {--dry-run : Count, delete nothing}';

    protected $description = 'Remove notification delivery log rows older than webx-notifications.log.days';

    public function handle(Config $config): int
    {
        $option = $this->option('days');
        $days = is_numeric($option) ? (int) $option : (int) $config->get('webx-notifications.log.days', 90);

        if ($days <= 0) {
            $this->info('The delivery log is kept forever (days = 0): nothing removed.');

            return self::SUCCESS;
        }

        $before = Carbon::now()->subDays($days);
        $query = Delivery::query()->where('created_at', '<', $before);

        if ($this->option('dry-run')) {
            $this->info(sprintf('Would remove %d delivery row%s older than %d day%s.', $count = $query->count(), $count === 1 ? '' : 's', $days, $days === 1 ? '' : 's'));

            return self::SUCCESS;
        }

        $removed = 0;

        // In batches: a year of a busy form is enough rows to hold a lock on the table for long.
        do {
            $ids = Delivery::query()->where('created_at', '<', $before)->orderBy('id')->limit(1000)->pluck('id')->all();
            $removed += $ids === [] ? 0 : Delivery::query()->whereKey($ids)->delete();
        } while (count($ids) === 1000);

        $this->info(sprintf('Removed %d delivery row%s older than %d day%s.', $removed, $removed === 1 ? '' : 's', $days, $days === 1 ? '' : 's'));

        return self::SUCCESS;
    }
}
