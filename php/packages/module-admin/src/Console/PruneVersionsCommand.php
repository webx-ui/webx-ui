<?php

declare(strict_types=1);

namespace WebxUi\Admin\Console;

use Illuminate\Console\Command;
use WebxUi\Admin\Versions\VersionPruner;

/**
 * Trim every entity's history to the configured limit.
 *
 * Not something a schedule needs: the limit is applied at every write, so it cannot be exceeded
 * by editing. This is for the day the limit was lowered and the rows above it are still there.
 */
class PruneVersionsCommand extends Command
{
    protected $signature = 'webx:versions:prune';

    protected $description = 'Trim the version history of every entity to the configured limit';

    public function handle(VersionPruner $pruner): int
    {
        $removed = $pruner->pruneAll();

        $this->info(sprintf(
            'Removed %d version%s; every entity keeps at most %d publication%s and %d autosave%s.',
            $removed,
            $removed === 1 ? '' : 's',
            $pruner->limit(),
            $pruner->limit() === 1 ? '' : 's',
            $pruner->autosaves(),
            $pruner->autosaves() === 1 ? '' : 's',
        ));

        return self::SUCCESS;
    }
}
