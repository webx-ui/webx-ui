<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use WebxUi\Blocks\Exceptions\BlocksException;
use WebxUi\Localization\Locales;

/**
 * The words content is edited in a node at a time — `set`, `unset`, `add`, `move`, `duplicate`,
 * `remove`, `hide`, `show` — and what each of them does to a tree.
 *
 * One dictionary for every door: the agent's `blocks_edit_content` and the panel on the site
 * (WEBX_UI_SITE_BAR.md, SB5) send the same operations and get the same refusals. Two copies
 * would drift — a check added to one, a new key made by the other — and an operation that the
 * agent may send and the bar may not, or the other way round, is a bug report waiting.
 *
 * Nothing is saved here and nothing is checked beyond the operation itself: where a block may
 * stand, how many of a type a page takes, what a value may be — that is {@see ContentValues}, on
 * the whole tree, after every operation has been applied.
 */
final class ContentOps
{
    /** What an operation may be called; anything else is refused with this list. */
    public const NAMES = ['set', 'unset', 'add', 'move', 'duplicate', 'remove', 'hide', 'show'];

    public function __construct(
        private readonly BlockTypes $types,
        private readonly StrayValues $strays,
        private readonly Locales $locales,
    ) {}

    /** A key for a node made here: random, so it survives the node being moved. */
    public static function newKey(): string
    {
        return substr(bin2hex(random_bytes(8)), 0, 12);
    }

    /**
     * Apply one operation.
     *
     * A block added gets its key here, not when the tree is written: whoever sent the operation
     * needs to know which block it made — the panel on the site opens its form at once.
     *
     * @param  list<array<string, mixed>>  $tree
     * @param  array<string, mixed>  $op
     * @param  string|null  $made  The key of the block the operation made — an added one, a duplicate's copy.
     * @return list<array<string, mixed>>
     *
     * @throws BlocksException naming what is wrong with the operation
     */
    public function apply(array $tree, array $op, ?string &$made = null): array
    {
        $name = $op['op'] ?? null;
        $key = is_string($op['key'] ?? null) ? $op['key'] : '';
        $parent = self::text($op, 'parent');
        $field = self::text($op, 'field');
        $before = self::text($op, 'before');
        $after = self::text($op, 'after');
        $made = null;

        // A value for a field the type does not have is refused at the door: this is how stray
        // values got into pages, and once in, nothing but a prune takes them out.
        if ($name === 'set' && is_array($op['values'] ?? null) && ($node = ContentEdit::find($tree, $key)) !== null) {
            $this->refuseStray((string) $node['type'], $op['values'], is_array($node['values'] ?? null) ? $node['values'] : [], self::held($tree));
        }

        if ($name === 'add' && is_string($op['type'] ?? null) && is_array($op['values'] ?? null)) {
            $this->refuseStray($op['type'], $op['values'], [], self::held($tree));
        }

        $containers = $this->containers(...);

        switch ($name) {
            case 'set':
                return ContentEdit::set(
                    $tree,
                    self::key($key),
                    is_array($op['values'] ?? null) ? $op['values'] : throw new BlocksException('`values` is required by set.'),
                    self::text($op, 'locale'),
                    $this->localized(...),
                    $this->locales->defaultCode(),
                );

            case 'duplicate':
                return ContentEdit::duplicate($tree, self::key($key), self::newKey(...), $made);

            case 'add':
                $made = self::newKey();

                return ContentEdit::insert(
                    $tree,
                    [
                        'key' => $made,
                        'type' => is_string($op['type'] ?? null) && $op['type'] !== ''
                            ? $op['type']
                            : throw new BlocksException('`type` is required by add: the slug of a block type.'),
                        'values' => is_array($op['values'] ?? null) ? $op['values'] : [],
                    ],
                    $parent,
                    $field,
                    $before,
                    $after,
                    $containers,
                );

            case 'move':
                return ContentEdit::move($tree, self::key($key), $parent, $field, $before, $after, $containers);

            case 'remove':
                return ContentEdit::remove($tree, self::key($key));

            case 'unset':
                return ContentEdit::unset(
                    $tree,
                    self::key($key),
                    is_array($op['fields'] ?? null) && $op['fields'] !== [] && array_is_list($op['fields'])
                        ? array_map(strval(...), $op['fields'])
                        : throw new BlocksException('`fields` is required by unset: the names of the values to take out.'),
                );

            case 'hide':
            case 'show':
                return ContentEdit::visibility($tree, self::key($key), $name === 'hide');
        }

        throw new BlocksException('Unknown operation ['.(is_string($name) ? $name : '?').']: '.implode(', ', self::NAMES).'.');
    }

    /**
     * The `wx-blocks` fields of a block type, by id — empty for a type that holds no blocks, null
     * for one nobody knows. What says whether "inside this block" is a place at all.
     *
     * @return list<string>|null
     */
    public function containers(string $type): ?array
    {
        return ($this->types->find($type) ?? $this->types->draft($type))?->nestedFields();
    }

    /**
     * Whether a field of a block type holds a language map; null when nobody can say — the type
     * was removed, or the field is not in its schema any more.
     */
    public function localized(string $type, string $field): ?bool
    {
        $block = $this->types->draft($type) ?? $this->types->find($type);

        if ($block === null || ! in_array($field, $block->fields(), true)) {
            return null;
        }

        return in_array($field, $block->localizedFields(), true);
    }

    /**
     * Refuse values for fields a block type does not define — the block's own and those of every
     * block nested in it. A field the block already holds may keep its value or be emptied; a
     * value of a field the type no longer has goes with `unset`.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>  $current
     * @param  array<string, array<string, mixed>>  $held  Key → the values of every node the entity holds now.
     *
     * @throws BlocksException
     */
    public function refuseStray(string $type, array $values, array $current, array $held): void
    {
        $unknown = $this->strays->unknown($type, $values, $current);

        if ($unknown !== []) {
            throw new BlocksException(
                "{$type} has no field [".implode('], [', $unknown).']. Its fields: '.implode(', ', $this->strays->fieldsOf($type)).'.'
            );
        }

        foreach ($values as $value) {
            if (! Content::isNodeList($value)) {
                continue;
            }

            foreach ($value as $node) {
                if (Content::isNode($node)) {
                    $key = is_string($node['key'] ?? null) ? $node['key'] : null;
                    $this->refuseStray((string) $node['type'], is_array($node['values'] ?? null) ? $node['values'] : [], $key === null ? [] : ($held[$key] ?? []), $held);
                }
            }
        }
    }

    /**
     * Every node of a tree by key, with its values: what a write may hand back as it was.
     *
     * @param  list<mixed>  $tree
     * @return array<string, array<string, mixed>>
     */
    public static function held(array $tree): array
    {
        $held = [];

        Content::walk($tree, static function (array $node) use (&$held): void {
            if (is_string($node['key'] ?? null)) {
                $held[$node['key']] ??= is_array($node['values'] ?? null) ? $node['values'] : [];
            }
        });

        return $held;
    }

    private static function key(string $key): string
    {
        return $key !== '' ? $key : throw new BlocksException('`key` is required: the key of the block to change.');
    }

    /**
     * @param  array<string, mixed>  $op
     */
    private static function text(array $op, string $name): ?string
    {
        return is_string($op[$name] ?? null) && $op[$name] !== '' ? $op[$name] : null;
    }
}
