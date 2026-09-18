<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Rendering;

use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Blocks\BlockType;
use WebxUi\Localization\Locales;

/**
 * What a block stores, turned into what its template reads.
 *
 * A field keeps the least it can — `wx-media` keeps the library key, not the address, so a
 * library that moves to S3 does not rewrite a single page — and the type that stores it is the
 * type that fills the rest back in. That is what a screen's values already do
 * ({@see ScreenValues::resolve()}); a block's values are screen nodes too, so they read the
 * same way.
 *
 * Two things pass through untouched, and both on purpose: a value whose key the schema does not
 * name — a field removed after the page was written — and a value of a type nobody registered,
 * `wx-blocks` above all. The nested tree is a list of blocks, and the renderer, not a field
 * type, is what prints it.
 */
final readonly class Values
{
    public function __construct(
        private FieldTypes $types,
        private Locales $locales,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function resolve(BlockType $type, array $values): array
    {
        if ($values === []) {
            return [];
        }

        $fields = $this->fields(self::named($type->schema));
        $resolved = [];

        foreach ($values as $name => $value) {
            $node = $fields[(string) $name] ?? null;

            // A localized field keeps a language map, and a template wants one language. This
            // is the same step a described screen takes on the way to the site
            // ({@see ScreenValues::resolve()}) — without it the template is handed the map,
            // Blade refuses to print an array, and the block renders as nothing at all.
            if ($node !== null && ($node['localized'] ?? false) === true && is_array($value)) {
                $value = $this->pick($value);
            }

            $field = $node === null ? null : $this->types->get((string) ($node['type'] ?? ''));

            $resolved[$name] = $field === null || $node === null ? $value : $field->resolve($value, $node);
        }

        return $resolved;
    }

    /**
     * The language the site is being read in, then the site's default, then its fallback —
     * the chain every localized value is read through.
     *
     * @param  array<string, mixed>  $translations
     */
    private function pick(array $translations): mixed
    {
        foreach ($this->locales->chain() as $code) {
            $candidate = $translations[$code] ?? null;

            if ($candidate !== null && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * The nodes a block's own values are keyed by, by id.
     *
     * The walk goes through layout — a schema puts fields in cards and tabs — and stops at a
     * field, because what is under a field belongs to its value and not to the block:
     * `wx-repeater` holds `city` in every one of its items, and the block holds no `city` at
     * all. {@see Tree::fields()} draws the same line with `name`; here the line is "the type is
     * one the server knows", since a block's schema names its fields `id`. The nodes arrive
     * normalized by {@see self::named()}.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, array<string, mixed>>
     */
    private function fields(array $nodes): array
    {
        $fields = [];

        foreach ($nodes as $node) {
            $id = $node['id'] ?? null;

            if (is_string($id) && $id !== '' && ! isset($fields[$id])) {
                $fields[$id] = $node;
            }

            if ($this->types->has((string) ($node['type'] ?? ''))) {
                continue;
            }

            foreach ($this->fields(Tree::children($node)) as $childId => $child) {
                $fields[$childId] ??= $child;
            }
        }

        return $fields;
    }

    /**
     * A block's schema names its fields `id`; a screen names them `name`, and so does everything
     * that walks one — `wx-repeater` finds the fields of an item with {@see Tree::fields()},
     * which stops at a node without a name. The copy the panel draws is normalized the same way
     * on the client (`formSchema`), and for the same reason; what is stored, exported and shown
     * to an agent stays as its author wrote it.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function named(array $nodes): array
    {
        $normalized = [];

        foreach ($nodes as $node) {
            if (! isset($node['name']) && isset($node['id'])) {
                $node['name'] = $node['id'];
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $node['children'] = self::named(array_values($node['children']));
            }

            $normalized[] = $node;
        }

        return $normalized;
    }
}
