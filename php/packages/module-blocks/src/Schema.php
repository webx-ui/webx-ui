<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\Tree;

/**
 * A block type's schema, read the way the rest of the panel reads a screen.
 *
 * One definition because both directions need it: what a value is turned into on its way to a
 * template ({@see Rendering\Values}) and what a value is turned into on its way into the
 * content ({@see ContentValues}) have to agree on which node a value belongs to, or a field
 * is cast by one type and read as another.
 */
final class Schema
{
    /** The types that arrange fields or explain them, and hold no value of their own. */
    public const LAYOUT = ['wx-card', 'wx-tabs', 'wx-tab', 'wx-row', 'wx-col', 'wx-divider', 'wx-heading', 'wx-text', 'wx-alert'];

    public static function isLayout(string $type): bool
    {
        return in_array($type, self::LAYOUT, true);
    }

    /**
     * The nodes a block's own values are keyed by, by id.
     *
     * The walk goes through layout — a schema puts fields in cards and tabs — and stops at a
     * field, because what is under a field belongs to its value and not to the block:
     * `wx-repeater` holds `city` in every one of its items, and the block holds no `city` at
     * all. {@see Tree::fields()} draws the same line with `name`; here the line is "the type is
     * one the server knows", since a block's schema names its fields `id`.
     *
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, array<string, mixed>>
     */
    public static function fields(array $nodes, FieldTypes $types): array
    {
        return self::collect(self::named($nodes), $types);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return array<string, array<string, mixed>>
     */
    private static function collect(array $nodes, FieldTypes $types): array
    {
        $fields = [];

        foreach ($nodes as $node) {
            $id = $node['id'] ?? null;

            if (is_string($id) && $id !== '' && ! isset($fields[$id])) {
                $fields[$id] = $node;
            }

            if ($types->has((string) ($node['type'] ?? ''))) {
                continue;
            }

            foreach (self::collect(Tree::children($node), $types) as $childId => $child) {
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
            // Layout keeps its id and gets no name: a named node is a field to the walk, and a
            // row of columns inside a repeater would otherwise hide every field in it.
            if (! isset($node['name']) && isset($node['id']) && ! self::isLayout((string) ($node['type'] ?? ''))) {
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
