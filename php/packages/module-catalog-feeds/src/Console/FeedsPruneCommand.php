<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Console;

use Illuminate\Console\Command;
use WebxUi\CatalogFeeds\Delivery\FeedPrune;

/** Old files and old lines of the journal, daily from the schedule. */
final class FeedsPruneCommand extends Command
{
    protected $signature = 'webx:catalog:feeds-prune';

    protected $description = 'Remove the old files and the old journal of the feeds';

    public function handle(FeedPrune $prune): int
    {
        $removed = $prune->run();

        $this->info("Files removed: {$removed['files']}, builds removed: {$removed['builds']}");

        return self::SUCCESS;
    }
}
