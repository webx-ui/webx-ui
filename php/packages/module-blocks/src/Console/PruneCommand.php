<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\ContentEdit;
use WebxUi\Blocks\Models\Region;

/**
 * Take out of every entity the values of fields its block types do not have.
 *
 * A field removed from a type, or values brought in by an import written for another version of
 * it, stay in the content: a save keeps them on purpose, so that a field put back finds what it
 * had. They are invisible on the site and in the way everywhere else — in the editor's data, in
 * what an agent reads, in the line a block is recognised by. This removes them, from what the site
 * shows and from the draft, in every model listed in `webx-blocks.entities` and in the regions.
 * `--dry-run` lists what would go and changes nothing.
 *
 * Written straight to the columns, not through a save: nothing about the page changes for a
 * visitor, so there is nothing to publish and no version to write.
 */
final class PruneCommand extends Command
{
    protected $signature = 'webx:blocks:prune
        {--dry-run : List what would be removed and change nothing}';

    protected $description = 'Remove block values for fields their block types no longer define';

    public function handle(Config $config, BlockTypes $types): int
    {
        $known = [];
        $fields = static function (string $slug) use ($types, &$known): ?array {
            if (! array_key_exists($slug, $known)) {
                $type = $types->find($slug) ?? $types->draft($slug);
                $known[$slug] = $type?->fields();
            }

            return $known[$slug];
        };

        $dry = (bool) $this->option('dry-run');
        $rows = [];

        foreach ($this->models($config) as $model) {
            $query = in_array(SoftDeletes::class, class_uses_recursive($model), true)
                ? $model->newQueryWithoutScopes()
                : $model->newQuery();

            foreach ($query->cursor() as $entity) {
                foreach ($this->prune($entity, $fields, $dry) as $where => $dropped) {
                    foreach ($dropped as $one) {
                        $rows[] = [class_basename($entity).' #'.$entity->getKey(), $where, $one['type'], $one['key'] ?? '—', implode(', ', $one['fields'])];
                    }
                }
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

        return self::SUCCESS;
    }

    /**
     * The live tree and the draft's, each pruned and written back unless this is a dry run.
     *
     * @param  callable(string): ?list<string>  $fields
     * @return array<string, list<array{key: ?string, type: string, fields: list<string>}>>
     */
    private function prune(Model $entity, callable $fields, bool $dry): array
    {
        $column = method_exists($entity, 'blocksColumn') ? (string) $entity->blocksColumn() : 'blocks';
        $found = [];
        $writes = [];

        $live = $entity->getAttribute($column);

        if (is_array($live)) {
            $dropped = [];
            $pruned = ContentEdit::prune(array_values($live), $fields, $dropped);

            if ($dropped !== []) {
                $found['site'] = $dropped;
                $writes[$column] = json_encode($pruned, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        $draftColumn = method_exists($entity, 'draftColumn') ? (string) $entity->draftColumn() : null;
        $draft = $draftColumn === null ? null : $entity->getAttribute($draftColumn);

        if ($draftColumn !== null && is_array($draft) && is_array($draft[$column] ?? null)) {
            $dropped = [];
            $draft[$column] = ContentEdit::prune(array_values($draft[$column]), $fields, $dropped);

            if ($dropped !== []) {
                $found['draft'] = $dropped;
                $writes[$draftColumn] = json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        if (! $dry && $writes !== []) {
            $entity->newQueryWithoutScopes()->whereKey($entity->getKey())->update($writes);
        }

        return $found;
    }

    /**
     * @return list<Model>
     */
    private function models(Config $config): array
    {
        $classes = $config->get('webx-blocks.entities', []);
        $classes = is_array($classes) ? $classes : [];
        $models = [new Region];

        foreach ($classes as $class) {
            if (is_string($class) && $class !== Region::class && is_subclass_of($class, Model::class)) {
                $models[] = new $class;
            }
        }

        return $models;
    }
}
