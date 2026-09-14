<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * Walks over a screen tree. Trees are plain arrays — the JSON as decoded — and every function
 * here returns a new one rather than changing what it was given.
 *
 * @phpstan-type Node array<string, mixed>
 */
final class Tree
{
    public const TRANS = 'trans::';

    /**
     * Every id in the tree, in document order. Duplicates are kept so a validator can spot them.
     *
     * @param  list<Node>  $nodes
     * @return list<string>
     */
    public static function ids(array $nodes): array
    {
        $ids = [];

        foreach ($nodes as $node) {
            $ids[] = (string) ($node['id'] ?? '');

            foreach (self::ids(self::children($node)) as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param  list<Node>  $nodes
     * @return Node|null
     */
    public static function find(array $nodes, string $id): ?array
    {
        foreach ($nodes as $node) {
            if (($node['id'] ?? null) === $id) {
                return $node;
            }

            $found = self::find(self::children($node), $id);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * The nodes that carry a value: everything with a `name`, in document order.
     *
     * A named node owns what is under it and the walk stops there. That is what makes
     * `wx-repeater` work: its children are the fields of one of its items — `city`, not
     * `contacts.offices.city` — and they belong to the repeater's value, not to the screen's.
     *
     * @param  list<Node>  $nodes
     * @return list<Node>
     */
    public static function fields(array $nodes): array
    {
        $fields = [];

        foreach ($nodes as $node) {
            if (isset($node['name']) && is_string($node['name']) && $node['name'] !== '') {
                $fields[] = $node;

                continue;
            }

            foreach (self::fields(self::children($node)) as $field) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Drops every node whose `can` the check refuses, with everything under it.
     *
     * @param  list<Node>  $nodes
     * @param  callable(string): bool  $can
     * @return list<Node>
     */
    public static function filter(array $nodes, callable $can): array
    {
        $kept = [];

        foreach ($nodes as $node) {
            $permission = $node['can'] ?? null;

            if (is_string($permission) && $permission !== '' && ! $can($permission)) {
                continue;
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $node['children'] = self::filter(self::children($node), $can);
            }

            $kept[] = $node;
        }

        return $kept;
    }

    /**
     * Turns every `trans::<namespace>::<key>` string — in a label, a hint, or anywhere in the
     * props — into words. The translator receives `<namespace>::<key>`.
     *
     * @param  callable(string): string  $translate
     */
    public static function translate(mixed $value, callable $translate): mixed
    {
        if (is_string($value)) {
            return str_starts_with($value, self::TRANS)
                ? $translate(substr($value, strlen(self::TRANS)))
                : $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::translate($item, $translate);
            }
        }

        return $value;
    }

    /**
     * @param  Node  $node
     * @return list<Node>
     */
    public static function children(array $node): array
    {
        $children = $node['children'] ?? [];

        return is_array($children) ? array_values($children) : [];
    }
}
