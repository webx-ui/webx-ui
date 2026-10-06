<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Blocks\Models\Region;

/**
 * Values for fields a block type does not define: finding them and taking them out.
 *
 * One place for the three that need it — `webx:blocks:prune`, the audit's check and the audit's
 * fix — so that what the check reports is exactly what the fix removes. A save keeps such values
 * on purpose ({@see ContentValues}): a field put back into a type finds what it had. This is the
 * deliberate clean-up.
 *
 * What is measured: a block's own values against the field ids of its type, and the items of a
 * `wx-repeater` against the fields of its children — the same bargain the repeater makes on
 * every save. A node of a type nobody knows is left alone: there is no schema to measure it by.
 *
 * @phpstan-type Dropped array{key: ?string, type: string, fields: list<string>}
 */
final class StrayValues
{
    /** @var array<string, array<string, list<string>|null>|null> */
    private array $allowed = [];

    public function __construct(
        private readonly BlockTypes $blocks,
        private readonly FieldTypes $types,
        private readonly Config $config,
    ) {}

    /**
     * What a type lets a block hold: field id → the keys of a repeater's items, or null for any
     * other field. Null for a type that is not known.
     *
     * @return array<string, list<string>|null>|null
     */
    public function allowed(string $slug): ?array
    {
        if (! array_key_exists($slug, $this->allowed)) {
            $type = $this->blocks->find($slug) ?? $this->blocks->draft($slug);

            if ($type === null) {
                $this->allowed[$slug] = null;
            } else {
                $allowed = [];

                foreach (Schema::fields($type->schema, $this->types) as $id => $field) {
                    $allowed[(string) $id] = ($field['type'] ?? null) === 'wx-repeater'
                        ? array_map('strval', array_keys(Schema::fields(Tree::children($field), $this->types)))
                        : null;
                }

                $this->allowed[$slug] = $allowed;
            }
        }

        return $this->allowed[$slug];
    }

    /**
     * What an entity holds that its types do not define, in what the site shows and in the draft.
     *
     * @return array{site?: list<Dropped>, draft?: list<Dropped>}
     */
    public function find(Model $entity): array
    {
        return $this->walk($entity, false);
    }

    /**
     * The same, taken out — straight to the columns: nothing changes for a visitor, so there is
     * nothing to publish and no version to write.
     *
     * @return array{site?: list<Dropped>, draft?: list<Dropped>}
     */
    public function prune(Model $entity): array
    {
        return $this->walk($entity, true);
    }

    /**
     * Every entity that holds blocks: the regions and each model listed in `webx-blocks.entities`.
     *
     * @return iterable<int, Model>
     */
    public function entities(bool $withTrashed = false): iterable
    {
        $classes = $this->config->get('webx-blocks.entities', []);
        $models = [new Region];

        foreach (is_array($classes) ? $classes : [] as $class) {
            if (is_string($class) && $class !== Region::class && is_subclass_of($class, Model::class)) {
                $models[] = new $class;
            }
        }

        foreach ($models as $model) {
            $query = $withTrashed && in_array(SoftDeletes::class, class_uses_recursive($model), true)
                ? $model->newQueryWithoutScopes()
                : $model->newQuery();

            foreach ($query->cursor() as $entity) {
                yield $entity;
            }
        }
    }

    /**
     * @return array{site?: list<Dropped>, draft?: list<Dropped>}
     */
    private function walk(Model $entity, bool $write): array
    {
        $column = method_exists($entity, 'blocksColumn') ? (string) $entity->blocksColumn() : 'blocks';
        $allowed = $this->allowed(...);
        $found = [];
        $writes = [];

        $live = $entity->getAttribute($column);

        if (is_array($live)) {
            $dropped = [];
            $pruned = ContentEdit::prune(array_values($live), $allowed, $dropped);

            if ($dropped !== []) {
                $found['site'] = $dropped;
                $writes[$column] = json_encode($pruned, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        $draftColumn = method_exists($entity, 'draftColumn') ? (string) $entity->draftColumn() : null;
        $draft = $draftColumn === null ? null : $entity->getAttribute($draftColumn);

        if ($draftColumn !== null && is_array($draft) && is_array($draft[$column] ?? null)) {
            $dropped = [];
            $draft[$column] = ContentEdit::prune(array_values($draft[$column]), $allowed, $dropped);

            if ($dropped !== []) {
                $found['draft'] = $dropped;
                $writes[$draftColumn] = json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        if ($write && $writes !== []) {
            $entity->newQueryWithoutScopes()->whereKey($entity->getKey())->update($writes);
        }

        return $found;
    }
}
