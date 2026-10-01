<?php

declare(strict_types=1);

namespace WebxUi\CatalogLandings\Console;

use Illuminate\Console\Command;
use WebxUi\Catalog\Catalog;
use WebxUi\CatalogLandings\Catalog\LandingCounter;

/**
 * `webx:catalog-landings:count` — the landings' counts of products (§6.5 of the landings spec).
 *
 * Without `--all`, the ones waiting since a batch of the index touched their bases. Where the
 * database answers every search there is no index and nothing marks them, so every landing is
 * counted — a catalogue small enough for that engine is cheap to count. Nightly, `--all` is the
 * safety net either way.
 */
final class CountCommand extends Command
{
    protected $signature = 'webx:catalog-landings:count {--all : Count every landing, not only the ones waiting}';

    protected $description = 'Recount how many products each catalogue landing shows';

    public function handle(LandingCounter $counter, Catalog $catalog): int
    {
        $counted = $counter->recount((bool) $this->option('all') || ! $catalog->needsIndex());

        $this->info(sprintf('Landings counted: %d', $counted));

        return self::SUCCESS;
    }
}
