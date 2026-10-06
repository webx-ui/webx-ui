<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Admin\Versions\HasDraft;
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

                foreach (Schema::valueFields($type->schema, $this->types) as $id => $field) {
                    $allowed[(string) $id] = ($field['type'] ?? null) === 'wx-repeater'
                        ? array_map('strval', array_keys(Schema::valueFields(Tree::children($field), $this->types)))
                        : null;
                }

                $this->allowed[$slug] = $allowed;
            }
        }

        return $this->allowed[$slug];
    }

    /**
     * What an entity holds that its types do not define, in what the site shows and in the draft
     * — and whether taking it out would leave the draft saying what the site says.
     */
    public function find(Model $entity): StrayReport
    {
        return $this->walk($entity, false);
    }

    /**
     * The same, taken out — straight to the columns: nothing changes for a visitor, so there is
     * nothing to publish and no version to write. A draft left equal to what the site shows is
     * dropped rather than kept, as a save would drop it.
     */
    public function prune(Model $entity): StrayReport
    {
        return $this->walk($entity, true);
    }

    /**
     * The fields of these values a block of this type does not define — what a write is refused
     * for, so that a stray value cannot be put in, only taken out.
     *
     * A key the block already holds may keep its value or be emptied with null: a page that came
     * with stray values can still be written back as it was read. Nested blocks are not looked
     * into here; the caller walks the tree. Empty for a type nobody knows.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $current  What the block holds now; empty for a new one.
     * @return list<string>
     */
    public function unknown(string $type, array $values, array $current = []): array
    {
        $allowed = $this->allowed($type);

        if ($allowed === null) {
            return [];
        }

        $unknown = [];

        foreach ($values as $field => $value) {
            $field = (string) $field;
            $held = array_key_exists($field, $current);

            if (! array_key_exists($field, $allowed)) {
                if (! $held || ($value !== null && $value !== $current[$field])) {
                    $unknown[] = $field;
                }

                continue;
            }

            $items = $allowed[$field];

            if ($items === null || ! is_array($value) || ! array_is_list($value) || ($held && $value === $current[$field])) {
                continue;
            }

            foreach ($value as $item) {
                foreach (is_array($item) ? array_keys($item) : [] as $name) {
                    if (! in_array((string) $name, $items, true)) {
                        $unknown[] = $field.'.*.'.$name;
                    }
                }
            }
        }

        return array_values(array_unique($unknown));
    }

    /**
     * The fields a type defines, for an answer that refuses a stray one.
     *
     * @return list<string>
     */
    public function fieldsOf(string $type): array
    {
        return array_keys($this->allowed($type) ?? []);
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

    private function walk(Model $entity, bool $write): StrayReport
    {
        $column = method_exists($entity, 'blocksColumn') ? (string) $entity->blocksColumn() : 'blocks';
        $allowed = $this->allowed(...);
        $site = [];
        $drafted = [];
        $writes = [];
        $prunedLive = null;

        $live = $entity->getAttribute($column);

        if (is_array($live)) {
            $prunedLive = ContentEdit::prune(array_values($live), $allowed, $site);

            if ($site !== []) {
                $writes[$column] = json_encode($prunedLive, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        $draftColumn = method_exists($entity, 'draftColumn') ? (string) $entity->draftColumn() : null;
        $draft = $draftColumn === null ? null : $entity->getAttribute($draftColumn);
        $draftDropped = false;

        if ($draftColumn !== null && is_array($draft) && is_array($draft[$column] ?? null)) {
            $draft[$column] = ContentEdit::prune(array_values($draft[$column]), $allowed, $drafted);

            if ($drafted !== []) {
                $draftDropped = $this->draftMatchesLive($entity, $column, $prunedLive, $draft);
                $writes[$draftColumn] = $draftDropped ? null : json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        if ($write && $writes !== []) {
            $entity->newQueryWithoutScopes()->whereKey($entity->getKey())->update($writes);
        }

        return new StrayReport($site, $drafted, $draftDropped);
    }

    /**
     * Whether the cleaned draft says exactly what the site will show once its own stray values
     * are gone too — the comparison a save makes ({@see HasDraft::matchesLive()}),
     * on a copy that carries the cleaned live tree.
     *
     * @param  list<mixed>|null  $prunedLive
     * @param  array<string, mixed>  $draft
     */
    private function draftMatchesLive(Model $entity, string $column, ?array $prunedLive, array $draft): bool
    {
        if (! method_exists($entity, 'matchesLive') || ! method_exists($entity, 'isPublished') || ! $entity->isPublished()) {
            return false;
        }

        $live = clone $entity;

        if ($prunedLive !== null) {
            $live->setAttribute($column, $prunedLive);
        }

        return (bool) $live->matchesLive($draft);
    }
}
