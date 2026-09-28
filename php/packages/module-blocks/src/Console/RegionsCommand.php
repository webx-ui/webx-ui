<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Console;

use Illuminate\Console\Command;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Regions;

/**
 * The regions of the layout, as the site has them: declared, saved, on the site.
 *
 * `--prune` removes the rows whose name left `webx-blocks.regions` — a region taken out of the
 * layout keeps its row until somebody says so (§3.1 of the regions spec), because a config edited
 * by mistake should not cost a header anybody spent a day on. Their history goes with them.
 */
final class RegionsCommand extends Command
{
    protected $signature = 'webx:blocks:regions
                            {--prune : Delete the saved regions the config no longer declares}';

    protected $description = 'List the regions of the layout, or remove the ones no longer declared';

    public function handle(Regions $regions): int
    {
        $declared = $regions->declared();

        if ($this->option('prune')) {
            $gone = Region::query()->whereNotIn('name', array_keys($declared))->get();

            foreach ($gone as $region) {
                $region->versions()->delete();
                $region->delete();
                $this->line("Removed [{$region->name}].");
            }

            $this->info($gone->isEmpty() ? 'Nothing to prune: every saved region is declared.' : "Pruned {$gone->count()}.");

            return self::SUCCESS;
        }

        if ($declared === []) {
            $this->info('No regions are declared (webx-blocks.regions).');
        }

        $rows = [];

        foreach (array_keys($declared) as $name) {
            $region = $regions->find($name);

            $rows[] = [
                $name,
                $regions->title($name),
                match (true) {
                    $region === null => 'never saved',
                    $region->isPublished() => 'published'.($region->hasDraft() ? ', with a draft' : ''),
                    default => 'fallback',
                },
                (string) Regions::visible($region?->editingTree() ?? []),
                $regions->fallbackOf($name) ?? '—',
            ];
        }

        foreach (Region::query()->whereNotIn('name', array_keys($declared))->pluck('name') as $name) {
            $rows[] = [(string) $name, '—', 'not declared (--prune removes it)', '—', '—'];
        }

        if ($rows !== []) {
            $this->table(['Name', 'Title', 'State', 'Blocks', 'Fallback'], $rows);
        }

        return self::SUCCESS;
    }
}
