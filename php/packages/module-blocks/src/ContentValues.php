<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use WebxUi\Admin\Screens\FieldType;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Blocks\Rendering\Values;

/**
 * What arrives for a block, turned into what it keeps.
 *
 * The mirror of {@see Values}: there a stored value is handed to its field type to be read,
 * here an incoming one is handed to the same type to be kept. A described screen has had this
 * since it was written ({@see ScreenValues::validate()}) — it looks the node's type up and
 * casts the value with `store()` — and a block's values are screen nodes too, so a value put
 * in a block is kept the same way a value put on a screen is. Without it a type that cleans
 * what it is given — an allowlist over pasted HTML, a colour lowercased, a number that stops
 * being the string a form sent — does that work on one half of the panel and not the other.
 *
 * Three things pass through untouched, and all three on purpose: a value whose key the schema
 * does not name — a field removed after the page was written — a value of a type nobody
 * registered, and a nested tree of blocks, which is the renderer's to print rather than any
 * field type's to keep.
 *
 * Rules are not run here. What a block holds is checked where it is drawn: the tree arrives
 * from the constructor rather than from a form with a field per node, and a value the panel
 * cannot show is not a value an editor can fix from a 422.
 */
final class ContentValues
{
    /** @var array<string, array<string, array<string, mixed>>> slug → the nodes of its schema, by id */
    private array $fields = [];

    public function __construct(
        private readonly BlockTypes $blocks,
        private readonly FieldTypes $types,
    ) {}

    /**
     * A tree on its way into an entity, every value cast by the type its schema names.
     *
     * @param  iterable<array-key, mixed>|null  $tree
     * @return list<mixed>
     */
    public function store(?iterable $tree): array
    {
        $stored = [];

        foreach ($tree ?? [] as $node) {
            $stored[] = Content::isNode($node) ? $this->node($node) : $node;
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function node(array $node): array
    {
        $values = is_array($node['values'] ?? null) ? $node['values'] : [];

        if ($values === []) {
            return $node;
        }

        $fields = $this->fields((string) $node['type']);
        $kept = [];

        foreach ($values as $name => $value) {
            $kept[$name] = $this->value($fields[(string) $name] ?? null, $value);
        }

        // Laid back into the node rather than built from a list of keys. A node carries more
        // than its values — `key`, and `hidden` since §23 — and a writer that names the keys it
        // knows loses the next one somebody adds, silently: the write succeeds, the key is not
        // in the row, and nothing anywhere says so.
        $node['values'] = $kept;

        return $node;
    }

    /**
     * @param  array<string, mixed>|null  $field
     */
    private function value(?array $field, mixed $value): mixed
    {
        // A nested constructor, recognised by shape the way the rest of {@see Content} is: a
        // `wx-blocks` field dropped from the schema still holds blocks, and they are still
        // blocks. No field type owns them.
        if (Content::isNodeList($value)) {
            return $this->store($value);
        }

        $type = $field === null ? null : $this->types->get((string) ($field['type'] ?? ''));

        if ($field === null || ! $type instanceof FieldType) {
            return $value;
        }

        // Never `?? $value`. A type is allowed to answer null — an editor emptied of everything
        // but its markup holds no document — and a fallback would put back exactly the value
        // that was refused.
        if (($field['localized'] ?? false) === true && is_array($value)) {
            return array_map(static fn (mixed $one): mixed => $type->store($one, $field), $value);
        }

        return $type->store($value, $field);
    }

    /**
     * The fields of a block type, by id, kept for as long as this write lasts: a page holds
     * twenty blocks of half a dozen types, and the schema of one of them does not change
     * halfway through saving it.
     *
     * The published version first — that is the schema the constructor drew the block with and
     * the one the site will print it with. A type that has never been published has only a
     * draft, and an agent that writes content with a type it has just made should not have its
     * values fall through uncast.
     *
     * @return array<string, array<string, mixed>>
     */
    private function fields(string $slug): array
    {
        if (! isset($this->fields[$slug])) {
            $type = $this->blocks->find($slug) ?? $this->blocks->draft($slug);

            $this->fields[$slug] = $type === null ? [] : Schema::fields($type->schema, $this->types);
        }

        return $this->fields[$slug];
    }
}
