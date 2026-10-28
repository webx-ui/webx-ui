<?php

declare(strict_types=1);

namespace WebxUi\Cart\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use WebxUi\Cart\Models\Cart;

/**
 * Deletes the guest carts nobody touched for a while, 500 at a time; their lines go by cascade.
 * Put on the schedule daily by the provider.
 */
final class PruneCommand extends Command
{
    protected $signature = 'webx:cart:prune
        {--days= : older than this many days; the default is webx-cart.prune_after_days}
        {--dry-run : only count them}';

    protected $description = 'Delete the carts nobody has touched for a while';

    public function handle(): int
    {
        $days = $this->option('days') ?? config('webx-cart.prune_after_days');

        if ($days === null || $days === '') {
            $this->components->info('Pruning is off: webx-cart.prune_after_days is null.');

            return self::SUCCESS;
        }

        if (! is_numeric($days) || (int) $days < 1) {
            $this->components->error('--days is a whole number of days, at least 1.');

            return self::FAILURE;
        }

        $before = Carbon::now()->subDays((int) $days);
        $query = Cart::query()->where('updated_at', '<', $before);

        if ($this->option('dry-run')) {
            $this->components->info(sprintf('%d %s untouched for %d days would be deleted.', $count = $query->count(), $count === 1 ? 'cart' : 'carts', (int) $days));

            return self::SUCCESS;
        }

        $deleted = 0;

        $query->select('id')->chunkById(500, function ($carts) use (&$deleted): void {
            $deleted += Cart::query()->whereKey($carts->modelKeys())->delete();
        });

        $this->components->info(sprintf('%d %s untouched for %d days deleted.', $deleted, $deleted === 1 ? 'cart' : 'carts', (int) $days));

        return self::SUCCESS;
    }
}
