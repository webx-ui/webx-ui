<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Console;

use Illuminate\Console\Command;
use WebxUi\Catalog\Popularity\Popularity;

/**
 * Write down the views the cache has counted since the last run (§9). On the schedule every five
 * minutes while `webx-catalog.popularity.views` is on.
 */
class FlushViewsCommand extends Command
{
    protected $signature = 'webx:catalog:flush-views';

    protected $description = 'Write the product views counted in the cache to the popularity table';

    public function handle(Popularity $popularity): int
    {
        $products = $popularity->flush();

        $this->info(sprintf('Views of %d product%s written down.', $products, $products === 1 ? '' : 's'));

        return self::SUCCESS;
    }
}
