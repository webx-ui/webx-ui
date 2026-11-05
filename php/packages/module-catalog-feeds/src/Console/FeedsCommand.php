<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Console;

use Illuminate\Console\Command;
use WebxUi\CatalogFeeds\Delivery\FeedWatch;

/** The watcher, once a minute from the schedule: it decides and queues, a worker builds. */
final class FeedsCommand extends Command
{
    protected $signature = 'webx:catalog:feeds';

    protected $description = 'Queue the builds of the feeds that are due one';

    public function handle(FeedWatch $watch): int
    {
        $this->info('Builds queued: '.$watch->tick());

        return self::SUCCESS;
    }
}
