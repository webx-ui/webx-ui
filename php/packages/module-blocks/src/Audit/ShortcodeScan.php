<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Audit;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Audit\Content\AuditContentSources;
use WebxUi\Blocks\BlockTypes;
use WebxUi\Blocks\Content;
use WebxUi\Blocks\Schema;

/**
 * The text an editor typed into an entity's blocks, field by field — what the two shortcode
 * checks read ({@see UnknownShortcodesCheck}, {@see HardcodedValuesCheck}).
 *
 * Only the fields a shortcode is resolved in: a text input, a textarea, a rich text field, and
 * the same inside a repeater's items. A `tel` or `email` input is left out: it holds the number
 * on purpose, and its rules refuse a bracket. Nested blocks are read too. The draft is read after
 * what the site shows, and a text the site already shows is not listed again for it.
 */
final class ShortcodeScan
{
    private const TEXT = ['wx-input', 'wx-textarea', 'wx-rich-text'];

    /** @var array<string, array<string, array<string, mixed>>> slug → field id → node */
    private array $fields = [];

    public function __construct(
        private readonly BlockTypes $blocks,
        private readonly FieldTypes $types,
        private readonly AuditContentSources $sources,
    ) {}

    /**
     * @return list<array{block: string, field: string, text: string, published: bool}>
     */
    public function texts(Model $entity): array
    {
        $column = method_exists($entity, 'blocksColumn') ? (string) $entity->blocksColumn() : 'blocks';
        $rows = [];
        $seen = [];

        $live = $entity->getAttribute($column);
        $this->nodes(is_array($live) ? $live : [], true, $rows, $seen);

        $draftColumn = method_exists($entity, 'draftColumn') ? (string) $entity->draftColumn() : null;
        $draft = $draftColumn === null ? null : $entity->getAttribute($draftColumn);

        if (is_array($draft) && is_array($draft[$column] ?? null)) {
            $this->nodes($draft[$column], false, $rows, $seen);
        }

        return $rows;
    }

    /**
     * Every record the audit's content sources know, by the key a finding names it with: its
     * name and where it is edited.
     *
     * @return array<string, array{0: string, 1: ?string}>
     */
    public function records(): array
    {
        $records = [];

        foreach ($this->sources->all() as $source) {
            foreach ($source->records() as $record) {
                if ($record->subject instanceof Model) {
                    $records[StrayValuesCheck::key($record->subject)] = [$record->label, $record->editUrl];
                }
            }
        }

        return $records;
    }

    /**
     * @param  array<array-key, mixed>  $nodes
     * @param  list<array{block: string, field: string, text: string, published: bool}>  $rows
     * @param  array<string, true>  $seen
     */
    private function nodes(array $nodes, bool $published, array &$rows, array &$seen): void
    {
        foreach ($nodes as $node) {
            if (! is_array($node) || ! is_array($node['values'] ?? null)) {
                continue;
            }

            $slug = (string) ($node['type'] ?? '');
            $block = $slug.' · '.(string) ($node['key'] ?? '—');
            $fields = $this->fieldsOf($slug);

            foreach ($node['values'] as $id => $value) {
                if (Content::isNodeList($value)) {
                    $this->nodes($value, $published, $rows, $seen);

                    continue;
                }

                $field = $fields[(string) $id] ?? null;

                if ($field === null) {
                    continue;
                }

                if (($field['type'] ?? null) === 'wx-repeater') {
                    $children = Tree::fields(Tree::children($field));

                    foreach (is_array($value) ? array_values($value) : [] as $index => $item) {
                        foreach ($children as $child) {
                            $name = (string) ($child['name'] ?? '');

                            if (is_array($item) && self::isText($child)) {
                                $this->add($rows, $seen, $block, $id.'.'.($index + 1).'.'.$name, $item[$name] ?? null, $published);
                            }
                        }
                    }

                    continue;
                }

                if (self::isText($field)) {
                    $this->add($rows, $seen, $block, (string) $id, $value, $published);
                }
            }
        }
    }

    /**
     * @param  list<array{block: string, field: string, text: string, published: bool}>  $rows
     * @param  array<string, true>  $seen
     */
    private function add(array &$rows, array &$seen, string $block, string $field, mixed $value, bool $published): void
    {
        // A localized field holds a map of languages; each is its own text.
        $texts = is_array($value)
            ? array_filter($value, static fn (mixed $text, mixed $locale): bool => is_string($text) && is_string($locale), ARRAY_FILTER_USE_BOTH)
            : (is_string($value) ? ['' => $value] : []);

        foreach ($texts as $locale => $text) {
            $label = $locale === '' ? $field : $field.' ('.$locale.')';
            $id = $block."\n".$label."\n".$text;

            if ($text === '' || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $rows[] = ['block' => $block, 'field' => $label, 'text' => $text, 'published' => $published];
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fieldsOf(string $slug): array
    {
        if (! isset($this->fields[$slug])) {
            $type = $this->blocks->find($slug) ?? $this->blocks->draft($slug);
            $this->fields[$slug] = $type === null ? [] : Schema::valueFields($type->schema, $this->types);
        }

        return $this->fields[$slug];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function isText(array $node): bool
    {
        $type = (string) ($node['type'] ?? '');

        if (! in_array($type, self::TEXT, true)) {
            return false;
        }

        return $type === 'wx-rich-text' || in_array($node['props']['type'] ?? 'text', ['text', 'search'], true);
    }
}
