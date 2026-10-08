<?php

declare(strict_types=1);

namespace WebxUi\Blocks;

use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Localization\Locales;

/**
 * A line to recognise a block by, chosen by what the fields mean rather than by which string
 * came first.
 *
 * The first string a block held was its label, and the first string is as often a setting as a
 * text: a reviews block read «grid», a recipes block «showcase», a picture block the path of its
 * image, and a hero the demo heading of a field its type no longer has. So: a field named like a
 * heading first, then the first text field of the schema, in the order the schema has them —
 * never a picture, a choice, a switch, a number, an address, or a key the type does not define.
 * Nothing that fits, and the type's own title says what the block is.
 */
final class BlockLabel
{
    /** Field ids that name a block, in the order they are tried. */
    private const HEADINGS = ['heading', 'title', 'eyebrow', 'card_title'];

    /** The field types whose value is text a person wrote. */
    private const TEXT = ['wx-input', 'wx-textarea', 'wx-rich-text'];

    private const LENGTH = 80;

    /** @var array<string, array{title: string, fields: list<string>}|null> */
    private array $types = [];

    public function __construct(
        private readonly BlockTypes $blocks,
        private readonly FieldTypes $fieldTypes,
        private readonly Locales $locales,
        private readonly Shortcodes $shortcodes,
    ) {}

    /**
     * @param  array<string, mixed>  $node
     */
    public function of(array $node): ?string
    {
        $type = $this->type((string) ($node['type'] ?? ''));
        $values = is_array($node['values'] ?? null) ? $node['values'] : [];

        if ($type === null) {
            return null;
        }

        foreach ($type['fields'] as $field) {
            $text = $this->text($values[$field] ?? null);

            if ($text !== null) {
                return $text;
            }
        }

        return $type['title'];
    }

    /**
     * The type's title and the fields worth reading, best first.
     *
     * @return array{title: string, fields: list<string>}|null
     */
    private function type(string $slug): ?array
    {
        if (! array_key_exists($slug, $this->types)) {
            $type = $this->blocks->find($slug) ?? $this->blocks->draft($slug);

            if ($type === null) {
                $this->types[$slug] = null;
            } else {
                $text = [];

                foreach (Schema::fields($type->schema, $this->fieldTypes) as $id => $field) {
                    if (in_array($field['type'] ?? null, self::TEXT, true)) {
                        $text[] = (string) $id;
                    }
                }

                $headings = array_values(array_filter(self::HEADINGS, static fn (string $id): bool => in_array($id, $text, true)));

                $this->types[$slug] = [
                    'title' => $type->title,
                    'fields' => array_values(array_unique([...$headings, ...$text])),
                ];
            }
        }

        return $this->types[$slug];
    }

    /** One value as a short line: the content language of a map, tags gone, addresses skipped. */
    private function text(mixed $value): ?string
    {
        if (is_array($value)) {
            $picked = null;

            foreach ([$this->locales->content(), ...array_keys($value)] as $code) {
                if (is_string($value[$code] ?? null) && trim($value[$code]) !== '') {
                    $picked = $value[$code];

                    break;
                }
            }

            $value = $picked;
        }

        if (! is_string($value)) {
            return null;
        }

        // Read as the page reads it: «Deeply heard.», not «Deeply heard[dot]».
        $text = trim(preg_replace('/\s+/u', ' ', $this->shortcodes->text($value)) ?? '');

        // A text field that holds an address is a link somebody pasted, not a name.
        if ($text === '' || preg_match('~^(https?://|/|mailto:|tel:|#)~i', $text) === 1) {
            return null;
        }

        return mb_strimwidth($text, 0, self::LENGTH, '…');
    }
}
