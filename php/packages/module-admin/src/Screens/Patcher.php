<?php

declare(strict_types=1);

namespace WebxUi\Admin\Screens;

/**
 * Applies a patch — add, remove, replace, move, set — to a screen tree, the same way
 * `applyPatch` does in `@webx-ui/schema`. One difference by design: the client skips an
 * operation it cannot apply and reports it, because a patch it received is not its to fix;
 * the server throws, because a patch registered here is a bug in whoever registered it.
 *
 * @phpstan-type Node array<string, mixed>
 */
final class Patcher
{
    /**
     * @param  list<Node>  $root
     * @param  list<array<string, mixed>>  $patch
     * @return list<Node>
     */
    public static function apply(string $screen, array $root, array $patch): array
    {
        foreach ($patch as $index => $op) {
            $name = (string) ($op['op'] ?? '');
            $target = (string) ($op['target'] ?? '');

            if (Tree::find($root, $target) === null) {
                throw ScreenException::patchFailed($screen, $index, $name, "target \"{$target}\" not found");
            }

            try {
                $root = match ($name) {
                    'add' => self::add($root, $target, $op),
                    'remove' => self::remove($root, $target),
                    'replace' => self::replace($root, $target, $op),
                    'move' => self::move($root, $target, $op),
                    'set' => self::set($root, $target, $op),
                    default => throw new ScreenException("unknown operation \"{$name}\""),
                };
            } catch (ScreenException $exception) {
                throw ScreenException::patchFailed($screen, $index, $name, $exception->getMessage());
            }
        }

        return $root;
    }

    /**
     * @param  list<Node>  $root
     * @param  array<string, mixed>  $op
     * @return list<Node>
     */
    private static function add(array $root, string $target, array $op): array
    {
        /** @var Node $node */
        $node = $op['node'];
        self::refuseClash($root, $node);

        return self::map($root, $target, static function (array $parent) use ($node, $op): array {
            $parent['children'] = self::insert(Tree::children($parent), $node, $op['position'] ?? null);

            return $parent;
        });
    }

    /**
     * @param  list<Node>  $root
     * @return list<Node>
     */
    private static function remove(array $root, string $target): array
    {
        return self::map($root, $target, static fn (array $node): ?array => null);
    }

    /**
     * @param  list<Node>  $root
     * @param  array<string, mixed>  $op
     * @return list<Node>
     */
    private static function replace(array $root, string $target, array $op): array
    {
        /** @var Node $node */
        $node = $op['node'];
        // The replaced node's own ids are gone, so only the rest of the tree can clash.
        self::refuseClash(self::remove($root, $target), $node);

        return self::map($root, $target, static fn (array $old): array => $node);
    }

    /**
     * @param  list<Node>  $root
     * @param  array<string, mixed>  $op
     * @return list<Node>
     */
    private static function move(array $root, string $target, array $op): array
    {
        /** @var Node $node */
        $node = Tree::find($root, $target);
        $position = $op['position'] ?? null;

        if (array_key_exists('to', $op)) {
            $to = (string) $op['to'];
            $destination = Tree::find($root, $to);

            if ($destination === null) {
                throw new ScreenException("destination \"{$to}\" not found");
            }

            if ($to === $target || in_array($to, Tree::ids([$node]), true)) {
                throw new ScreenException("cannot move \"{$target}\" into itself");
            }

            $without = self::remove($root, $target);

            return self::map($without, $to, static function (array $parent) use ($node, $position): array {
                $parent['children'] = self::insert(Tree::children($parent), $node, $position);

                return $parent;
            });
        }

        $parent = self::parentOf($root, $target);

        if ($parent === null) {
            return self::insert(self::remove($root, $target), $node, $position);
        }

        return self::map(self::remove($root, $target), $parent, static function (array $parentNode) use ($node, $position): array {
            $parentNode['children'] = self::insert(Tree::children($parentNode), $node, $position);

            return $parentNode;
        });
    }

    /**
     * @param  list<Node>  $root
     * @param  array<string, mixed>  $op
     * @return list<Node>
     */
    private static function set(array $root, string $target, array $op): array
    {
        return self::map($root, $target, static function (array $node) use ($op): array {
            foreach ($op as $key => $value) {
                if ($key === 'op' || $key === 'target') {
                    continue;
                }

                if ($key === 'props') {
                    $node['props'] = array_merge(is_array($node['props'] ?? null) ? $node['props'] : [], is_array($value) ? $value : []);
                } else {
                    $node[$key] = $value;
                }
            }

            return $node;
        });
    }

    /**
     * Applies `$fn` to the node with `$id`, wherever it is; `null` from it removes the node.
     *
     * @param  list<Node>  $nodes
     * @param  callable(Node): (Node|null)  $fn
     * @return list<Node>
     */
    private static function map(array $nodes, string $id, callable $fn): array
    {
        $out = [];

        foreach ($nodes as $node) {
            if (($node['id'] ?? null) === $id) {
                $replacement = $fn($node);

                if ($replacement !== null) {
                    $out[] = $replacement;
                }

                continue;
            }

            if (isset($node['children']) && is_array($node['children'])) {
                $node['children'] = self::map(Tree::children($node), $id, $fn);
            }

            $out[] = $node;
        }

        return $out;
    }

    /**
     * @param  list<Node>  $nodes
     * @param  Node  $node
     * @return list<Node>
     */
    private static function insert(array $nodes, array $node, mixed $position): array
    {
        if ($position === null || $position === 'last') {
            $nodes[] = $node;

            return $nodes;
        }

        if ($position === 'first') {
            array_unshift($nodes, $node);

            return $nodes;
        }

        if (! ScreenValidator::isPosition($position)) {
            throw new ScreenException('position must be first, last, before:<id> or after:<id>');
        }

        /** @var string $position */
        [$where, $anchor] = explode(':', $position, 2);
        $at = null;

        foreach ($nodes as $index => $sibling) {
            if (($sibling['id'] ?? null) === $anchor) {
                $at = $index;

                break;
            }
        }

        if ($at === null) {
            throw new ScreenException("no sibling \"{$anchor}\" to insert {$where}");
        }

        array_splice($nodes, $where === 'before' ? $at : $at + 1, 0, [$node]);

        return array_values($nodes);
    }

    /**
     * Id of the node whose children hold `$id`, or null when it sits at the root.
     *
     * @param  list<Node>  $nodes
     */
    private static function parentOf(array $nodes, string $id, ?string $parent = null): ?string
    {
        foreach ($nodes as $node) {
            if (($node['id'] ?? null) === $id) {
                return $parent;
            }

            $found = self::parentOf(Tree::children($node), $id, (string) ($node['id'] ?? ''));

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  list<Node>  $root
     * @param  Node  $incoming
     */
    private static function refuseClash(array $root, array $incoming): void
    {
        $existing = array_flip(Tree::ids($root));

        foreach (Tree::ids([$incoming]) as $id) {
            if (isset($existing[$id])) {
                throw new ScreenException("id \"{$id}\" already exists in the screen");
            }
        }
    }
}
