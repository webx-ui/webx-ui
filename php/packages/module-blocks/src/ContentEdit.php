<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use WebxUi\Blocks\Exceptions\BlocksException;

/**
 * Changing a tree of blocks a node at a time, by key.
 *
 * Why this exists next to "read the tree, write the tree back": rewriting twenty nodes to change
 * one heading costs the whole page twice and loses whatever somebody else wrote in between. Here
 * the caller names the node and says what to do with it, and everything else stays the object it
 * already was — which also means an agent cannot drop a branch it never looked at.
 *
 * Every method takes a tree and returns a new one; nothing is saved here. Keys are the addresses,
 * so a node without one cannot be edited — {@see Content} calls that shape a node all the same,
 * because a tree written by hand is still a tree, and the caller fills the missing keys in.
 */
final class ContentEdit
{
    /**
     * Merge values into one node.
     *
     * Merge by field, replace by value: a field that is not mentioned keeps what it had, and a
     * field that is mentioned takes the new value whole. Going deeper than a field would mean
     * guessing what a value means, and only the field type knows that.
     *
     * @param  list<array<string, mixed>>  $tree
     * @param  array<string, mixed>  $values
     * @param  (callable(string, string): ?bool)|null  $localized  Type slug and field name → whether the field holds a language map; null when nobody knows.
     * @return list<array<string, mixed>>
     */
    public static function set(array $tree, string $key, array $values, ?string $locale = null, ?callable $localized = null): array
    {
        return self::edit($tree, $key, static function (array $node) use ($values, $locale, $localized): array {
            $node['values'] = self::merge(
                is_array($node['values'] ?? null) ? $node['values'] : [],
                $values,
                $locale,
                $localized,
                (string) $node['type'],
            );

            return $node;
        });
    }

    /**
     * Put a node somewhere: at the top level, or inside another block's constructor field.
     *
     * @param  list<array<string, mixed>>  $tree
     * @param  array<string, mixed>  $node
     * @return list<array<string, mixed>>
     */
    public static function insert(
        array $tree,
        array $node,
        ?string $parent = null,
        ?string $field = null,
        ?string $before = null,
        ?string $after = null,
    ): array {
        return self::inList($tree, $parent, $field, static fn (array $list): array => self::place($list, $node, $before, $after));
    }

    /**
     * Take a node out of wherever it is and put it back somewhere else.
     *
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    public static function move(
        array $tree,
        string $key,
        ?string $parent = null,
        ?string $field = null,
        ?string $before = null,
        ?string $after = null,
    ): array {
        $node = self::find($tree, $key);

        if ($node === null) {
            throw new BlocksException(self::unknown($key));
        }

        if ($parent !== null && ($parent === $key || self::find([$node], $parent) !== null)) {
            throw new BlocksException("Block [{$key}] cannot be moved inside itself.");
        }

        return self::insert(self::remove($tree, $key), $node, $parent, $field, $before, $after);
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    public static function remove(array $tree, string $key): array
    {
        $gone = false;
        $tree = self::without($tree, $key, $gone);

        if (! $gone) {
            throw new BlocksException(self::unknown($key));
        }

        return $tree;
    }

    /**
     * One node with everything under it, or null when the tree has no such key.
     *
     * @param  list<array<string, mixed>>  $tree
     * @return array<string, mixed>|null
     */
    public static function find(array $tree, string $key): ?array
    {
        foreach ($tree as $node) {
            if (! Content::isNode($node)) {
                continue;
            }

            if (($node['key'] ?? null) === $key) {
                return $node;
            }

            foreach (is_array($node['values'] ?? null) ? $node['values'] : [] as $value) {
                if (Content::isNodeList($value) && ($found = self::find(array_values($value), $key)) !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * The page as a list: what is on it, in what order, under what — and nothing else.
     *
     * This is what an agent should read before editing: the values of every block are the
     * expensive part, and it needs them for one node, not for twenty.
     *
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    public static function outline(array $tree, ?string $parent = null, int $depth = 0): array
    {
        $outline = [];

        foreach ($tree as $node) {
            if (! Content::isNode($node)) {
                continue;
            }

            $key = is_string($node['key'] ?? null) ? $node['key'] : null;
            $values = is_array($node['values'] ?? null) ? $node['values'] : [];

            $outline[] = array_filter([
                'key' => $key,
                'type' => (string) $node['type'],
                'depth' => $depth,
                'parent' => $parent,
                'label' => self::label($values),
            ], static fn (mixed $value): bool => $value !== null);

            foreach ($values as $field => $value) {
                if (Content::isNodeList($value)) {
                    $outline = [...$outline, ...self::outline(array_values($value), $key, $depth + 1)];
                }
            }
        }

        return $outline;
    }

    /**
     * The `wx-blocks` fields a node already uses, by name.
     *
     * A block type may declare more than one constructor field, and then "inside this block" is
     * not an address — hence {@see self::inList()} asking the caller which one it means.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    public static function nested(array $node): array
    {
        $fields = [];

        foreach (is_array($node['values'] ?? null) ? $node['values'] : [] as $field => $value) {
            if (Content::isNodeList($value)) {
                $fields[] = (string) $field;
            }
        }

        return $fields;
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @param  callable(array<string, mixed>): array<string, mixed>  $edit
     * @return list<array<string, mixed>>
     */
    private static function edit(array $tree, string $key, callable $edit, bool &$found = false): array
    {
        $top = func_num_args() < 4;
        $edited = [];

        foreach ($tree as $node) {
            if (! Content::isNode($node)) {
                $edited[] = $node;

                continue;
            }

            if (($node['key'] ?? null) === $key) {
                $found = true;
                $edited[] = $edit($node);

                continue;
            }

            foreach (is_array($node['values'] ?? null) ? $node['values'] : [] as $field => $value) {
                if (Content::isNodeList($value)) {
                    $node['values'][$field] = self::edit(array_values($value), $key, $edit, $found);
                }
            }

            $edited[] = $node;
        }

        if ($top && ! $found) {
            throw new BlocksException(self::unknown($key));
        }

        return $edited;
    }

    /**
     * Hand the list at a location to the caller and put back what comes out.
     *
     * @param  list<array<string, mixed>>  $tree
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $edit
     * @return list<array<string, mixed>>
     */
    private static function inList(array $tree, ?string $parent, ?string $field, callable $edit): array
    {
        if ($parent === null) {
            return $edit($tree);
        }

        return self::edit($tree, $parent, static function (array $node) use ($parent, $field, $edit): array {
            $nested = self::nested($node);
            $name = $field;

            if ($name === null) {
                if (count($nested) > 1) {
                    throw new BlocksException(
                        "Block [{$parent}] holds blocks in more than one field: ".implode(', ', $nested)
                        .'. Say which one with `field`.'
                    );
                }

                $name = $nested[0] ?? null;
            }

            if ($name === null) {
                throw new BlocksException(
                    "Block [{$node['key']}] holds no blocks yet, so `field` is needed: the name of its wx-blocks field."
                );
            }

            $values = is_array($node['values'] ?? null) ? $node['values'] : [];
            $list = Content::isNodeList($values[$name] ?? null) ? array_values($values[$name]) : [];
            $values[$name] = $edit($list);
            $node['values'] = $values;

            return $node;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $list
     * @param  array<string, mixed>  $node
     * @return list<array<string, mixed>>
     */
    private static function place(array $list, array $node, ?string $before, ?string $after): array
    {
        $sibling = $before ?? $after;

        if ($sibling === null) {
            return [...$list, $node];
        }

        foreach ($list as $index => $one) {
            if (($one['key'] ?? null) === $sibling) {
                $at = $before !== null ? $index : $index + 1;

                return [...array_slice($list, 0, $at), $node, ...array_slice($list, $at)];
            }
        }

        throw new BlocksException("Block [{$sibling}] is not in that list, so there is nowhere to put the node beside it.");
    }

    /**
     * @param  list<array<string, mixed>>  $tree
     * @return list<array<string, mixed>>
     */
    private static function without(array $tree, string $key, bool &$gone): array
    {
        $kept = [];

        foreach ($tree as $node) {
            if (Content::isNode($node) && ($node['key'] ?? null) === $key) {
                $gone = true;

                continue;
            }

            if (Content::isNode($node)) {
                foreach (is_array($node['values'] ?? null) ? $node['values'] : [] as $field => $value) {
                    if (Content::isNodeList($value)) {
                        $node['values'][$field] = self::without(array_values($value), $key, $gone);
                    }
                }
            }

            $kept[] = $node;
        }

        return $kept;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $values
     * @param  (callable(string, string): ?bool)|null  $localized
     * @return array<string, mixed>
     */
    private static function merge(array $current, array $values, ?string $locale, ?callable $localized, string $type): array
    {
        foreach ($values as $field => $value) {
            $name = (string) $field;
            $many = $localized === null ? null : $localized($type, $name);
            $held = $current[$name] ?? null;

            if ($locale !== null) {
                if ($many === false || ($many === null && $held !== null && ! is_array($held))) {
                    throw new BlocksException(
                        "Field [{$name}] of [{$type}] holds one value, not a language map: send it without `locale`."
                    );
                }

                $map = is_array($held) ? $held : [];
                $map[$locale] = $value;
                $current[$name] = $map;

                continue;
            }

            if ($many === true && ! is_array($value)) {
                throw new BlocksException(
                    "Field [{$name}] of [{$type}] is localized: send `locale` with the value, or a map of languages."
                );
            }

            $current[$name] = $value;
        }

        return $current;
    }

    /**
     * A line to recognise a block by: the first text it holds, whatever language it is in.
     *
     * @param  array<string, mixed>  $values
     */
    private static function label(array $values): ?string
    {
        foreach ($values as $value) {
            if (is_array($value) && ! Content::isNodeList($value)) {
                $value = self::label($value);
            }

            if (is_string($value) && trim($value) !== '') {
                $text = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');

                if ($text !== '') {
                    return mb_strimwidth($text, 0, 80, '…');
                }
            }
        }

        return null;
    }

    private static function unknown(string $key): string
    {
        return "No block on this entity has the key [{$key}]. blocks_get_content with outline says which keys there are.";
    }
}
