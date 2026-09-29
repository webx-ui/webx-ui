<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use WebxUi\Admin\History\HistoryPruner;

/**
 * Drop journal rows older than the configured number of days.
 *
 * Put on the schedule by the frame, daily: unlike the versions, nothing trims the journal as it
 * is written — a row is never "one too many" for its record, only too old.
 */
class PruneHistoryCommand extends Command
{
    protected $signature = 'webx:history:prune';

    protected $description = 'Remove journal rows older than webx-admin.history.retention_days';

    public function handle(HistoryPruner $pruner): int
    {
        $removed = $pruner->prune();

        $this->info(sprintf(
            'Removed %d journal row%s older than %d day%s.',
            $removed,
            $removed === 1 ? '' : 's',
            $pruner->days(),
            $pruner->days() === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }
}
