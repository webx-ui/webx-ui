<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Console;

use Illuminate\Console\Command;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\Indexer;

/**
 * Hand the engine what the queue holds (§8.3); `--rebuild` writes the whole catalogue from
 * scratch, schema and all.
 *
 * On the schedule every minute, and only where the engine keeps an index: under `SqlEngine` the
 * database is the index and there is nothing to hand over.
 */
class IndexCommand extends Command
{
    protected $signature = 'webx:catalog:index
                            {--rebuild : Write every product again, with the index made anew}';

    protected $description = 'Send the products waiting in the queue to the catalogue engine';

    public function handle(Catalog $catalog, Indexer $indexer): int
    {
        if (! $catalog->needsIndex()) {
            $this->info('The engine is the database itself: there is no index to write.');

            return self::SUCCESS;
        }

        $written = $this->option('rebuild') ? $indexer->rebuild() : $indexer->run();

        $this->info(sprintf('Wrote %d product%s to the engine.', $written, $written === 1 ? '' : 's'));

        return self::SUCCESS;
    }
}
