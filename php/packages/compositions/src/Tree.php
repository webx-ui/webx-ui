<?php

declare(strict_types=1);

namespace WebxUi\Compositions;

/**
 * The tree of one composition for one handle, as the layers have left it so far, with what was
 * said along the way: the findings and every change by file — what `webx:composition:show`
 * prints as the origin of a node.
 */
final class Tree
{
    /** @var list<Finding> */
    public array $findings = [];

    /** @var list<array{id: string, operation: string, file: string}> */
    public array $changes = [];

    /** @var list<string> the layers that touched the tree, in order */
    public array $layers = [];

    /** @var list<string> the variables the leaves get from the place the composition is included (§2.2) */
    public array $vars = [];

    /** The view that includes the composition, when the base names it (§2.2). */
    public ?string $dispatcher = null;

    public function __construct(public Node $root) {}

    public function find(string $id): ?Node
    {
        foreach ($this->root->walk() as $node) {
            if ($node->id === $id) {
                return $node;
            }
        }

        return null;
    }

    public function has(string $id): bool
    {
        return $this->find($id) !== null;
    }

    public function note(string $file, string $operation, string $message, bool $note = false): void
    {
        $this->findings[] = new Finding($file, $operation, $message, $note);
    }

    public function changed(string $id, string $operation, string $file): void
    {
        $this->changes[] = ['id' => $id, 'operation' => $operation, 'file' => $file];
    }

    public function touchedBy(string $layer): void
    {
        if (! in_array($layer, $this->layers, true)) {
            $this->layers[] = $layer;
        }
    }

    /** A copy to patch further without touching this one: an `extends` builds on its parent's. */
    public function copy(): self
    {
        $tree = new self($this->clone($this->root, null));
        $tree->findings = $this->findings;
        $tree->changes = $this->changes;
        $tree->layers = $this->layers;
        $tree->vars = $this->vars;
        $tree->dispatcher = $this->dispatcher;

        return $tree;
    }

    private function clone(Node $node, ?Node $parent): Node
    {
        $copy = clone $node;
        $copy->parent = $parent;
        $copy->children = array_map(fn (Node $child): Node => $this->clone($child, $copy), $node->children);

        return $copy;
    }
}
