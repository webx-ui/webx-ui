<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

/**
 * Walking a tree of blocks without rendering it.
 *
 * A node is `{ key, type, values }`, and a value that is itself a list of such nodes is a
 * nested constructor — that is the whole shape, and it is recognised by shape rather than by
 * schema on purpose: the walk has to work for a type that is no longer published, whose
 * schema nobody can look up any more.
 */
final class Content
{
    /**
     * The distinct types a tree uses, nested ones included, in first-seen order.
     *
     * @param  iterable<array-key, mixed>|null  $blocks
     * @return list<string>
     */
    public static function types(?iterable $blocks): array
    {
        $types = [];

        self::walk($blocks, static function (array $node) use (&$types): void {
            $types[(string) $node['type']] = true;
        });

        return array_keys($types);
    }

    /**
     * Every node, parents before children.
     *
     * @param  iterable<array-key, mixed>|null  $blocks
     * @param  callable(array<string, mixed>, int): void  $visit  The node and its depth, root being 0.
     */
    public static function walk(?iterable $blocks, callable $visit, int $depth = 0): void
    {
        if ($blocks === null) {
            return;
        }

        foreach ($blocks as $node) {
            if (! self::isNode($node)) {
                continue;
            }

            $visit($node, $depth);

            foreach (is_array($node['values'] ?? null) ? $node['values'] : [] as $value) {
                if (self::isNodeList($value)) {
                    self::walk($value, $visit, $depth + 1);
                }
            }
        }
    }

    /**
     * What the tree is, as a short string: the same content gives the same revision.
     *
     * An entity's draft has no number of its own — autosaves are a ring and lose theirs — so
     * "the page as I read it" is the content itself. Two writers agreeing on the content is not
     * a conflict, and this says so; anything else is.
     *
     * @param  iterable<array-key, mixed>|null  $blocks
     */
    public static function revision(?iterable $blocks): string
    {
        $tree = [];

        foreach ($blocks ?? [] as $node) {
            $tree[] = $node;
        }

        return substr(sha1(json_encode($tree, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), 0, 12);
    }

    /**
     * @phpstan-assert-if-true array<string, mixed> $node
     */
    public static function isNode(mixed $node): bool
    {
        return is_array($node) && is_string($node['type'] ?? null) && $node['type'] !== '';
    }

    /**
     * Whether a block is switched off: in the content, editable in the panel, not drawn on the
     * site (§23).
     *
     * The flag is only ever there when it is true. Content written before the switch existed is
     * therefore visible without a migration, and a tree shows the key only for the blocks
     * somebody deliberately turned off.
     *
     * @param  array<string, mixed>  $node
     */
    public static function isHidden(array $node): bool
    {
        return ($node['hidden'] ?? null) === true;
    }

    /** A list of nodes — a nested constructor's value — as opposed to a list of strings or a map. */
    public static function isNodeList(mixed $value): bool
    {
        if (! is_array($value) || $value === [] || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! self::isNode($item)) {
                return false;
            }
        }

        return true;
    }
}
