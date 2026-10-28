<?php

declare(strict_types=1);

namespace WebxUi\Users\Console;

use Illuminate\Console\Command;
use WebxUi\Users\Passwords\ResetLinks;

/**
 * Deletes the links of resets and invitations that are out of date (spec §14.3). Accounts are not
 * touched: an unconfirmed one may have orders (decision 9), and pruning them is not in the first
 * version (decision 23). Put on the schedule daily by the provider.
 */
final class PruneCommand extends Command
{
    protected $signature = 'webx:users:prune {--dry-run : only count them}';

    protected $description = 'Delete the links of password resets and invitations that are out of date';

    public function handle(ResetLinks $links): int
    {
        $count = $this->option('dry-run') ? $links->expired()->count() : $links->expired()->delete();

        $this->components->info(sprintf(
            '%d %s out of date %s.',
            $count,
            $count === 1 ? 'link' : 'links',
            $this->option('dry-run') ? 'would be deleted' : 'deleted',
        ));

        return self::SUCCESS;
    }
}
