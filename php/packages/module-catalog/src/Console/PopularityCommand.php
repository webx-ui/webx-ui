<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Console;

use Illuminate\Console\Command;
use WebxUi\Catalog\Popularity\Popularity;

/**
 * The nightly recount (§9): the views fade, every signal is weighed into a score, and the
 * products whose score moved go to the engine.
 */
class PopularityCommand extends Command
{
    protected $signature = 'webx:catalog:popularity';

    protected $description = 'Fade the views and recount the popularity score of every product';

    public function handle(Popularity $popularity): int
    {
        $touched = $popularity->recount();

        $this->info(sprintf('Scores recounted; %d product%s moved enough to reindex.', $touched, $touched === 1 ? '' : 's'));

        return self::SUCCESS;
    }
}
