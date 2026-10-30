<?php

declare(strict_types=1);

namespace WebxUi\Orders\Checkout\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;

/**
 * Deletes the checkout's drafts nobody touched for `webx-orders.checkout.draft_days` (spec §21.3),
 * 500 at a time, even where the cart lives on: the contacts of a checkout left unfinished should
 * not live as long as the goods in a cart. Put on the schedule daily while the checkout is on.
 */
final class PruneDraftsCommand extends Command
{
    public const string NAME = 'webx:orders:checkout-prune';

    protected $signature = 'webx:orders:checkout-prune
        {--days= : older than this many days; the default is webx-orders.checkout.draft_days}
        {--dry-run : only count them}';

    protected $description = 'Delete the checkout drafts nobody has touched for a while';

    public function handle(): int
    {
        $days = $this->option('days') ?? config('webx-orders.checkout.draft_days');

        if ($days === null || $days === '') {
            $this->components->info('Pruning is off: webx-orders.checkout.draft_days is null.');

            return self::SUCCESS;
        }

        if (! is_numeric($days) || (int) $days < 1) {
            $this->components->error('--days is a whole number of days, at least 1.');

            return self::FAILURE;
        }

        $query = CheckoutDraft::query()->where('updated_at', '<', Carbon::now()->subDays((int) $days));

        if ($this->option('dry-run')) {
            $count = $query->count();
            $this->components->info(sprintf('%d %s untouched for %d days would be deleted.', $count, $count === 1 ? 'draft' : 'drafts', (int) $days));

            return self::SUCCESS;
        }

        $deleted = 0;

        $query->select('cart_id')->chunkById(500, static function ($drafts) use (&$deleted): void {
            $deleted += CheckoutDraft::query()->whereKey($drafts->modelKeys())->delete();
        }, 'cart_id');

        $this->components->info(sprintf('%d %s untouched for %d days deleted.', $deleted, $deleted === 1 ? 'draft' : 'drafts', (int) $days));

        return self::SUCCESS;
    }
}
