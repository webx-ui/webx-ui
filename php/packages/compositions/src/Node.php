<?php

declare(strict_types=1);

namespace WebxUi\Compositions;

/**
 * One node of a tree (COMPOSITIONS §2.3): the kind it is, what that kind needs, its children, and
 * who put it there. Mutable on purpose — a patch moves and rewrites nodes in place, and a tree is
 * built once, when compiling, never on a request.
 */
final class Node
{
    /** The kinds a node can be, by the key that names it. A node with none of them is a group. */
    public const array KINDS = ['tag', 'view', 'component', 'region', 'slot', 'stack', 'directive', 'html'];

    /** The kinds that hold children: what `add` and `move` can put a node into. */
    public const array CONTAINERS = ['tag', 'group', 'component'];

    /** The kinds that take attributes. */
    public const array ATTRIBUTED = ['tag', 'component'];

    /** @var list<Node> */
    public array $children = [];

    public ?Node $parent = null;

    /** Where a protected node of the base stood: it moves only inside that node (§3.6). */
    public ?string $home = null;

    /**
     * @param  string  $kind  one of {@see KINDS}, or `group`
     * @param  string  $value  the tag, the view, the component, the region, the slot, the stack, the directive or the HTML
     * @param  array<string, string|bool>  $attrs
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $with  the literals an included view gets
     * @param  list<Node>  $fallback  what a named slot prints while the caller handed nothing
     * @param  string  $origin  the layer that put the node in the tree
     * @param  bool  $base  whether that layer is the base of the composition (`place only="added"`)
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $value = '',
        public array $attrs = [],
        public array $props = [],
        public array $with = [],
        public array $fallback = [],
        public ?string $regionFallback = null,
        public ?string $args = null,
        public bool $protected = false,
        public string $origin = '',
        public bool $base = false,
    ) {}

    public function isContainer(): bool
    {
        return in_array($this->kind, self::CONTAINERS, true);
    }

    /** Whether `$node` is this node or one inside it. */
    public function contains(Node $node): bool
    {
        for ($at = $node; $at !== null; $at = $at->parent) {
            if ($at === $this) {
                return true;
            }
        }

        return false;
    }

    /** @return iterable<Node> this node and everything inside it, depth first */
    public function walk(): iterable
    {
        yield $this;

        foreach ($this->children as $child) {
            yield from $child->walk();
        }
    }

    public function detach(): void
    {
        if ($this->parent !== null) {
            $this->parent->children = array_values(array_filter($this->parent->children, fn (Node $child): bool => $child !== $this));
            $this->parent = null;
        }
    }

    /** Puts `$node` among the children at `$index` (the end when null). */
    public function insert(Node $node, ?int $index = null): void
    {
        $node->detach();
        $node->parent = $this;
        $index ??= count($this->children);
        array_splice($this->children, max(0, min($index, count($this->children))), 0, [$node]);
    }

    public function indexOf(Node $node): ?int
    {
        $index = array_search($node, $this->children, true);

        return $index === false ? null : $index;
    }
}
