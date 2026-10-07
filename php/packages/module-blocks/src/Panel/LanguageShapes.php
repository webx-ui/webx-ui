<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Regions;
use WebxUi\Blocks\Schema;
use WebxUi\Localization\Locales;

/**
 * What switching a field's `localized` does to the content already written with it.
 *
 * A localized field keeps `{ en: …, de: … }`, any other keeps one value, and the schema says which
 * — but the content was written under the schema of its day. Switch the flag and every page still
 * holds the other shape: reading copes ({@see Locales::isMap()}), but the form
 * draws a list of tags as two empty languages, and a template printing `{{ $title }}` is handed a
 * map. So when a version that flips the flag is published, the values follow it: a plain value
 * becomes the main language's, and a map becomes its main language's value.
 *
 * The second direction can lose words — the other languages — and is not done silently: publishing
 * is refused, naming the pages, until whoever publishes says to drop them.
 *
 * @phpstan-type Flip array{field: string, child: string|null, localized: bool}
 * @phpstan-type Affected array{model: string, id: int|string, title: string|null, translations: bool}
 */
final class LanguageShapes
{
    public function __construct(
        private readonly FieldTypes $types,
        private readonly Locales $locales,
        private readonly Container $container,
    ) {}

    /**
     * The fields whose flag differs between two schemas, a repeater's children included. A field
     * only one of them has is not a flip: there is nothing written under the other to convert.
     *
     * @param  list<array<string, mixed>>  $before
     * @param  list<array<string, mixed>>  $after
     * @return list<Flip>
     */
    public function flips(array $before, array $after): array
    {
        $old = Schema::valueFields($before, $this->types);
        $flips = [];

        foreach (Schema::valueFields($after, $this->types) as $id => $node) {
            $was = $old[$id] ?? null;

            if ($was === null || ($was['type'] ?? null) !== ($node['type'] ?? null)) {
                continue;
            }

            if (self::localized($was) !== self::localized($node)) {
                $flips[] = ['field' => (string) $id, 'child' => null, 'localized' => self::localized($node)];
            }

            if (($node['type'] ?? null) === 'wx-repeater') {
                $oldChildren = Schema::valueFields(Tree::children($was), $this->types);

                foreach (Schema::valueFields(Tree::children($node), $this->types) as $childId => $child) {
                    $wasChild = $oldChildren[$childId] ?? null;

                    if ($wasChild !== null && self::localized($wasChild) !== self::localized($child)) {
                        $flips[] = ['field' => (string) $id, 'child' => (string) $childId, 'localized' => self::localized($child)];
                    }
                }
            }
        }

        return $flips;
    }

    /**
     * The entities whose blocks of this type hold a value a flip changes — and, for each, whether
     * the change drops words in another language.
     *
     * @param  list<Flip>  $flips
     * @return list<Affected>
     */
    public function affected(string $slug, array $flips): array
    {
        if ($flips === []) {
            return [];
        }

        $found = [];

        foreach ($this->entities() as $entity) {
            $touched = false;
            $lossy = false;

            foreach ($this->trees($entity) as $tree) {
                Content::walk($tree, function (array $node) use ($slug, $flips, &$touched, &$lossy): void {
                    if ($node['type'] !== $slug) {
                        return;
                    }

                    $values = is_array($node['values'] ?? null) ? $node['values'] : [];

                    foreach ($flips as $flip) {
                        foreach ($this->valuesAt($values, $flip) as $value) {
                            if ($this->changes($value, $flip['localized'])) {
                                $touched = true;
                                $lossy = $lossy || (! $flip['localized'] && $this->dropsWords($value));
                            }
                        }
                    }
                });
            }

            if ($touched) {
                $found[] = [
                    'model' => $entity::class,
                    'id' => $entity->getKey(),
                    'title' => $this->title($entity),
                    'translations' => $lossy,
                ];
            }
        }

        return $found;
    }

    /**
     * Rewrite every value the flips change, in what the site shows and in the draft of every
     * entity. Straight to the row, quietly: nothing is published by it and nothing is drafted —
     * the same content in the shape its field now has.
     *
     * @param  list<Flip>  $flips
     * @return int How many entities were rewritten.
     */
    public function apply(string $slug, array $flips): int
    {
        if ($flips === []) {
            return 0;
        }

        $changed = 0;

        foreach ($this->entities() as $entity) {
            $column = method_exists($entity, 'blocksColumn') ? (string) $entity->blocksColumn() : 'blocks';
            $dirty = false;

            $live = $entity->getAttribute($column);

            if (is_array($live)) {
                $converted = $this->convertTree($live, $slug, $flips);

                if ($converted !== $live) {
                    $entity->setAttribute($column, $converted);
                    $dirty = true;
                }
            }

            if (method_exists($entity, 'draftValues') && method_exists($entity, 'draftColumn')) {
                $draft = $entity->draftValues();

                if (is_array($draft[$column] ?? null)) {
                    $converted = $this->convertTree($draft[$column], $slug, $flips);

                    if ($converted !== $draft[$column]) {
                        $draft[$column] = $converted;
                        $entity->setAttribute((string) $entity->draftColumn(), $draft);
                        $dirty = true;
                    }
                }
            }

            if ($dirty) {
                $entity->saveQuietly();
                $changed++;

                // A region's published tree is cached by name, and a quiet save does not say so.
                if ($entity instanceof Region) {
                    $this->container->make(Regions::class)->forget($entity->name);
                }
            }
        }

        return $changed;
    }

    /**
     * @param  array<array-key, mixed>  $tree
     * @param  list<Flip>  $flips
     * @return array<array-key, mixed>
     */
    private function convertTree(array $tree, string $slug, array $flips): array
    {
        foreach ($tree as $index => $node) {
            if (! Content::isNode($node)) {
                continue;
            }

            $values = is_array($node['values'] ?? null) ? $node['values'] : [];

            foreach ($values as $name => $value) {
                if (Content::isNodeList($value)) {
                    $values[$name] = $this->convertTree($value, $slug, $flips);
                }
            }

            if ($node['type'] === $slug) {
                foreach ($flips as $flip) {
                    $values = $this->convertValues($values, $flip);
                }
            }

            if (is_array($node['values'] ?? null)) {
                $node['values'] = $values;
            }

            $tree[$index] = $node;
        }

        return $tree;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  Flip  $flip
     * @return array<string, mixed>
     */
    private function convertValues(array $values, array $flip): array
    {
        $field = $flip['field'];

        if (! array_key_exists($field, $values)) {
            return $values;
        }

        if ($flip['child'] === null) {
            $values[$field] = $this->convert($values[$field], $flip['localized']);

            return $values;
        }

        if (! is_array($values[$field]) || ! array_is_list($values[$field])) {
            return $values;
        }

        foreach ($values[$field] as $row => $item) {
            if (is_array($item) && array_key_exists($flip['child'], $item)) {
                $item[$flip['child']] = $this->convert($item[$flip['child']], $flip['localized']);
                $values[$field][$row] = $item;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  Flip  $flip
     * @return list<mixed>
     */
    private function valuesAt(array $values, array $flip): array
    {
        $value = $values[$flip['field']] ?? null;

        if ($flip['child'] === null) {
            return [$value];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }

        return array_map(static fn (mixed $item): mixed => is_array($item) ? ($item[$flip['child']] ?? null) : null, $value);
    }

    /** One value in the shape its field now has. */
    public function convert(mixed $value, bool $localized): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return $value;
        }

        if ($localized) {
            return $this->locales->isMap($value) ? $value : [$this->locales->defaultCode() => $value];
        }

        if (! $this->locales->isMap($value, true)) {
            return $value;
        }

        foreach ($this->locales->chain($this->locales->defaultCode()) as $code) {
            $candidate = $value[$code] ?? null;

            if ($candidate !== null && $candidate !== '' && $candidate !== []) {
                return $candidate;
            }
        }

        foreach ($value as $candidate) {
            if ($candidate !== null && $candidate !== '' && $candidate !== []) {
                return $candidate;
            }
        }

        return null;
    }

    private function changes(mixed $value, bool $localized): bool
    {
        return $this->convert($value, $localized) !== $value;
    }

    /**
     * Whether keeping one language of a map loses words: more than one language holds something,
     * and not the same thing.
     */
    private function dropsWords(mixed $value): bool
    {
        if (! $this->locales->isMap($value, true)) {
            return false;
        }

        $filled = array_filter($value, static fn (mixed $one): bool => $one !== null && $one !== '' && $one !== []);

        return count(array_unique(array_map(static fn (mixed $one): string => (string) json_encode($one), $filled))) > 1;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function localized(array $node): bool
    {
        return ($node['localized'] ?? false) === true;
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function trees(Model $entity): array
    {
        $column = method_exists($entity, 'blocksColumn') ? (string) $entity->blocksColumn() : 'blocks';
        $trees = [];
        $live = $entity->getAttribute($column);

        if (is_array($live)) {
            $trees[] = $live;
        }

        if (method_exists($entity, 'draftValues')) {
            $draft = $entity->draftValues();

            if (is_array($draft[$column] ?? null)) {
                $trees[] = $draft[$column];
            }
        }

        return $trees;
    }

    private function title(Model $entity): ?string
    {
        if ($entity instanceof Region) {
            return $this->container->make(Regions::class)->title($entity->name);
        }

        $title = $entity->getAttribute('title');

        if (is_array($title)) {
            $title = array_values(array_filter($title, static fn (mixed $one): bool => is_string($one) && $one !== ''))[0] ?? null;
        }

        return is_string($title) && $title !== '' ? $title : null;
    }

    /**
     * The same entities {@see Usage} walks: every model the site named, and the declared regions.
     *
     * @return iterable<int, Model>
     */
    private function entities(): iterable
    {
        return $this->container->make(Usage::class)->entities();
    }
}
