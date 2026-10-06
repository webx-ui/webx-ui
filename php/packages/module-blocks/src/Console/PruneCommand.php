<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Console;

use Illuminate\Console\Command;
use WebxUi\Blocks\StrayValues;

/**
 * Take out of every entity the values of fields its block types do not have.
 *
 * A field removed from a type, or values brought in by an import written for another version of
 * it, stay in the content: a save keeps them on purpose, so that a field put back finds what it
 * had. They are invisible on the site and in the way everywhere else — in the editor's data, in
 * what an agent reads, in the line a block is recognised by. This removes them, from what the site
 * shows and from the draft, in every model listed in `webx-blocks.entities` and in the regions,
 * the bin included. `--dry-run` lists what would go and changes nothing. What is measured, and
 * how, is {@see StrayValues} — the audit's check and fix use the same.
 */
final class PruneCommand extends Command
{
    protected $signature = 'webx:blocks:prune
        {--dry-run : List what would be removed and change nothing}';

    protected $description = 'Remove block values for fields their block types no longer define';

    public function handle(StrayValues $strays): int
    {
        $dry = (bool) $this->option('dry-run');
        $rows = [];
        $drafts = 0;

        foreach ($strays->entities(withTrashed: true) as $entity) {
            $report = $dry ? $strays->find($entity) : $strays->prune($entity);
            $drafts += $report->draftDropped ? 1 : 0;

            foreach ($report->rows() as $one) {
                $rows[] = [class_basename($entity).' #'.$entity->getKey(), $one['where'], $one['type'], $one['key'] ?? '—', implode(', ', $one['fields'])];
            }
        }

        if ($rows === []) {
            $this->components->info('Every block holds only the fields its type defines.');

            return self::SUCCESS;
        }

        $this->table(['Entity', 'In', 'Type', 'Block', 'Values'], $rows);
        $this->components->info($dry
            ? 'Blocks with values to remove: '.count($rows).'. Run without --dry-run to remove them.'
            : 'Blocks cleaned: '.count($rows).'.');

        // A draft that differed from the site only by stray values has nothing left waiting.
        if ($drafts > 0) {
            $this->components->info(($dry ? 'Drafts that would be dropped' : 'Drafts dropped').', nothing else was waiting in them: '.$drafts.'.');
        }

        return self::SUCCESS;
    }
}
