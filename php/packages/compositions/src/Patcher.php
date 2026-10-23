<?php

declare(strict_types=1);

namespace WebxUi\Compositions;

/**
 * The operations of one file over a tree (COMPOSITIONS §3), in the fixed order
 * `add → move → replace → attrs → props → remove`: the order of keys in a JSON object is no
 * contract, and `remove` last lets a file put a node after X and take X away.
 *
 * Whatever cannot be applied as written — an id that is not there, a duplicate, a cycle, a
 * protected node — is skipped and said as a finding. A layout that throws takes every page of
 * the site down, the one where the patch would be fixed included.
 */
final class Patcher
{
    private const array POSITIONS = ['before', 'after', 'first', 'last'];

    public function __construct(private readonly Reader $reader) {}

    /**
     * @param  array<string, mixed>  $patch  checked by the reader already
     * @param  string  $origin  the layer, as a node remembers who added it
     * @param  string  $file  the file, as a finding says where to look
     */
    public function apply(Tree $tree, array $patch, string $origin, string $file, bool $base = false): void
    {
        $tree->touchedBy($origin);

        foreach ((array) ($patch['add'] ?? []) as $item) {
            $this->add($tree, (array) $item, $origin, $file, $base);
        }

        foreach ((array) ($patch['move'] ?? []) as $item) {
            $this->move($tree, (array) $item, $file);
        }

        foreach ((array) ($patch['replace'] ?? []) as $id => $node) {
            $this->replace($tree, (string) $id, (array) $node, $origin, $file, $base);
        }

        foreach ((array) ($patch['attrs'] ?? []) as $id => $attrs) {
            $this->attrs($tree, (string) $id, (array) $attrs, $file);
        }

        foreach ((array) ($patch['props'] ?? []) as $id => $props) {
            $this->props($tree, (string) $id, (array) $props, $file);
        }

        foreach ((array) ($patch['remove'] ?? []) as $id) {
            $this->remove($tree, (string) $id, $file);
        }
    }

    /** @param array<string, mixed> $item */
    private function add(Tree $tree, array $item, string $origin, string $file, bool $base): void
    {
        $id = (string) $item['id'];
        $operation = "add {$id}";
        $to = $tree->find((string) $item['to']);

        if ($to === null) {
            $tree->note($file, $operation, "no node \"{$item['to']}\" to add into");

            return;
        }

        if (! $to->isContainer()) {
            $tree->note($file, $operation, "\"{$to->id}\" is a {$to->kind}, not a container");

            return;
        }

        $node = $this->reader->build(array_diff_key($item, array_flip(['to', ...self::POSITIONS])), $origin, $base);

        foreach ($node->walk() as $inside) {
            if ($tree->has($inside->id)) {
                $tree->note($file, $operation, "\"{$inside->id}\" is already in the tree — replace changes a node");

                return;
            }
        }

        $to->insert($node, $this->index($tree, $to, $item, $node, $file, $operation));
        $tree->changed($id, 'add', $file);
    }

    /** @param array<string, mixed> $item */
    private function move(Tree $tree, array $item, string $file): void
    {
        $id = (string) $item['id'];
        $operation = "move {$id}";
        $node = $tree->find($id);

        if ($node === null) {
            $tree->note($file, $operation, 'no node with this id');

            return;
        }

        $to = isset($item['to']) ? $tree->find((string) $item['to']) : $node->parent;

        if ($to === null) {
            $tree->note($file, $operation, isset($item['to']) ? "no node \"{$item['to']}\" to move into" : 'the root does not move');

            return;
        }

        if (! $to->isContainer()) {
            $tree->note($file, $operation, "\"{$to->id}\" is a {$to->kind}, not a container");

            return;
        }

        if ($node->contains($to)) {
            $tree->note($file, $operation, "\"{$to->id}\" is inside the node itself");

            return;
        }

        if ($node->protected) {
            $home = $node->home === null ? null : $tree->find($node->home);

            if ($home === null || $node->parent === $tree->root || ! $home->contains($to)) {
                $tree->note($file, $operation, $node->parent === $tree->root || $home === null
                    ? 'a protected node of the base does not move'
                    : "a protected node of the base stays inside \"{$home->id}\"");

                return;
            }
        }

        foreach (['before', 'after'] as $key) {
            if (($item[$key] ?? null) === $id) {
                $tree->note($file, $operation, "{$key} itself");

                return;
            }
        }

        $node->detach();
        $to->insert($node, $this->index($tree, $to, $item, $node, $file, $operation));
        $tree->changed($id, 'move', $file);
    }

    /** @param array<string, mixed> $replacement */
    private function replace(Tree $tree, string $id, array $replacement, string $origin, string $file, bool $base): void
    {
        $operation = "replace {$id}";
        $node = $tree->find($id);

        if ($node === null) {
            $tree->note($file, $operation, 'no node with this id');

            return;
        }

        if ($node->protected || $node->parent === null) {
            $tree->note($file, $operation, 'a protected node of the base is not replaced');

            return;
        }

        $new = $this->reader->build(['id' => $id, ...$replacement], $origin, $base, $id);

        foreach ($new->walk() as $inside) {
            $there = $tree->find($inside->id);

            if ($inside !== $new && $there !== null && ! $node->contains($there)) {
                $tree->note($file, $operation, "\"{$inside->id}\" is already in the tree");

                return;
            }
        }

        foreach ($node->walk() as $inside) {
            if ($inside !== $node && $inside->origin !== $node->origin && $inside->origin !== $origin && $new->indexOf($inside) === null) {
                $tree->note($file, $operation, "drops \"{$inside->id}\" added by {$inside->origin}", note: true);
            }
        }

        $parent = $node->parent;
        $index = $parent->indexOf($node);
        $node->detach();
        $parent->insert($new, $index);
        $tree->changed($id, 'replace', $file);
    }

    /** @param array<string, mixed> $attrs */
    private function attrs(Tree $tree, string $id, array $attrs, string $file): void
    {
        $node = $tree->find($id);

        if ($node === null) {
            $tree->note($file, "attrs {$id}", 'no node with this id');

            return;
        }

        if (! in_array($node->kind, Node::ATTRIBUTED, true)) {
            $tree->note($file, "attrs {$id}", "a {$node->kind} has no attributes — replace changes what it is");

            return;
        }

        foreach ($attrs as $name => $value) {
            $name = (string) $name;
            $operation = "attrs {$id}.{$name}";

            if ($node === $tree->root && $node->value === 'html' && $name === 'lang') {
                $tree->note($file, $operation, 'lang is the language of the page, set when rendering');

                continue;
            }

            if ($node->protected && $node->value === 'main' && $name === 'id') {
                $tree->note($file, $operation, 'the main keeps id="content": "Skip to content" leads there');

                continue;
            }

            if ($value === null || $value === false) {
                unset($node->attrs[$name]);
            } elseif ($name === 'class' && is_array($value)) {
                $gone = preg_split('/\s+/', trim((string) $value['remove'])) ?: [];
                $node->attrs['class'] = implode(' ', array_diff($this->classes($node), $gone));

                if ($node->attrs['class'] === '') {
                    unset($node->attrs['class']);
                }
            } elseif ($name === 'class' && is_string($value)) {
                $node->attrs['class'] = implode(' ', array_values(array_unique([...$this->classes($node), ...(preg_split('/\s+/', trim($value)) ?: [])])));
            } else {
                $node->attrs[$name] = is_bool($value) ? $value : (string) $value;
            }

            $tree->changed($id, "attrs.{$name}", $file);
        }
    }

    /** @param array<string, mixed> $props */
    private function props(Tree $tree, string $id, array $props, string $file): void
    {
        $node = $tree->find($id);

        if ($node === null || $node->kind !== 'component') {
            $tree->note($file, "props {$id}", $node === null ? 'no node with this id' : "a {$node->kind} has no props");

            return;
        }

        foreach ($props as $name => $value) {
            if ($value === null) {
                unset($node->props[$name]);
            } else {
                $node->props[$name] = $value;
            }

            $tree->changed($id, "props.{$name}", $file);
        }
    }

    private function remove(Tree $tree, string $id, string $file): void
    {
        $node = $tree->find($id);

        if ($node === null) {
            // Gone already is what the file asked for: a theme took the quick contact off the
            // layout, and layout.minimal, which extends it, takes it off once more.
            $by = array_values(array_filter($tree->changes, static fn (array $change): bool => $change['id'] === $id && $change['operation'] === 'remove'));
            $tree->note($file, "remove {$id}", $by === [] ? 'no node with this id' : "removed already by {$by[0]['file']}", note: $by !== []);

            return;
        }

        if ($node->protected || $node->parent === null) {
            $tree->note($file, "remove {$id}", 'a protected node of the base stays');

            return;
        }

        $node->detach();
        $tree->changed($id, 'remove', $file);
    }

    /**
     * Where among the children of `$to` a node goes. A neighbour that is not there puts it last
     * — a module's piece is not lost because a theme moved the node it stood beside.
     *
     * @param  array<string, mixed>  $item
     */
    private function index(Tree $tree, Node $to, array $item, Node $node, string $file, string $operation): ?int
    {
        if (($item['first'] ?? false) === true) {
            return 0;
        }

        foreach (['before' => 0, 'after' => 1] as $key => $offset) {
            if (! isset($item[$key])) {
                continue;
            }

            $siblings = array_values(array_filter($to->children, static fn (Node $child): bool => $child !== $node));

            foreach ($siblings as $index => $sibling) {
                if ($sibling->id === $item[$key]) {
                    return $index + $offset;
                }
            }

            $tree->note($file, $operation, "no \"{$item[$key]}\" inside \"{$to->id}\": put last");
        }

        return null;
    }

    /** @return list<string> */
    private function classes(Node $node): array
    {
        $class = $node->attrs['class'] ?? '';

        return is_string($class) && trim($class) !== '' ? (preg_split('/\s+/', trim($class)) ?: []) : [];
    }
}
