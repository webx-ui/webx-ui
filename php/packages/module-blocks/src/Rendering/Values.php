<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Rendering;

use Closure;
use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\PreloadsValues;
use WebxUi\Admin\Screens\ResolvesForEntity;
use WebxUi\Admin\Screens\ResolvesMissing;
use WebxUi\Admin\Screens\ScreenValues;
use WebxUi\Admin\Screens\Tree;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Admin\Shortcodes\ShortcodeText;
use WebxUi\Blocks\BlockType;
use WebxUi\Blocks\ContentValues;
use WebxUi\Blocks\Schema;
use WebxUi\Localization\Locales;

/**
 * What a block stores, turned into what its template reads.
 *
 * A field keeps the least it can — `wx-media` keeps the library key, not the address, so a
 * library that moves to S3 does not rewrite a single page — and the type that stores it is the
 * type that fills the rest back in. That is what a screen's values already do
 * ({@see ScreenValues::resolve()}); a block's values are screen nodes too, so they read the
 * same way.
 *
 * Two things pass through untouched, and both on purpose: a value whose key the schema does not
 * name — a field removed after the page was written — and a value of a type nobody registered,
 * `wx-blocks` above all. The nested tree is a list of blocks, and the renderer, not a field
 * type, is what prints it.
 *
 * Shortcodes are resolved here too, and only here, so that the page, the preview and
 * `blocks_render` print the same thing: an editor's text field holding `[phone]` reaches the
 * template as a {@see ShortcodeText} — escaped text with the shortcode's HTML in it — and a rich
 * text field as its HTML with the shortcodes replaced between the tags. The content keeps the
 * brackets, so a changed phone number changes every page that says `[phone]`.
 *
 * The other direction is {@see ContentValues}: the same walk over the same schema, asking the
 * type what to keep rather than what to hand over.
 */
final readonly class Values
{
    public function __construct(
        private FieldTypes $types,
        private Locales $locales,
        private Shortcodes $shortcodes,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @param  object|null  $entity  The record whose page is being printed, for a type that reads
     *                               differently on it ({@see ResolvesForEntity}); null on a sample.
     * @return array<string, mixed>
     */
    public function resolve(BlockType $type, array $values, ?object $entity = null): array
    {
        $fields = Schema::fields($type->schema, $this->types);
        $resolved = [];

        // A field nobody wrote is null in the template — except where the type says what an
        // absent value means ({@see ResolvesMissing}): a collection block never touched is the
        // whole collection, not an empty list.
        foreach ($fields as $name => $node) {
            $field = $this->types->get((string) ($node['type'] ?? ''));

            // A field with a `default` is drawn with it in the editor, so the template reads it
            // too; it goes through the second loop like any written value.
            if (array_key_exists('default', $node) && ! array_key_exists($name, $values)) {
                $values[$name] = null;

                continue;
            }

            if ($field instanceof ResolvesMissing && ! array_key_exists($name, $values)) {
                $resolved[$name] = $field instanceof ResolvesForEntity
                    ? $field->resolveFor(null, $node, $entity)
                    : $field->resolve(null, $node);
            }
        }

        foreach ($values as $name => $value) {
            $node = $fields[(string) $name] ?? null;

            // A language map is read in one language, and a template wants one. This is the
            // same step a described screen takes on the way to the site
            // ({@see ScreenValues::resolve()}) — without it the template is handed the map,
            // Blade refuses to print an array, and the block renders as nothing at all.
            //
            // By the value's shape, not the schema's flag: a field switched to `localized`
            // after the page was written still holds a plain value (a list of tags is not a map
            // of languages), and one switched back still holds the map until it is written again.
            if ($node !== null && $this->locales->isMap($value, ($node['localized'] ?? false) === true)) {
                $value = $this->pick($value);
            }

            if ($value === null && $node !== null && array_key_exists('default', $node)) {
                $value = $node['default'];
            }

            $field = $node === null ? null : $this->types->get((string) ($node['type'] ?? ''));

            $resolved[$name] = $this->withShortcodes(match (true) {
                $field === null || $node === null => $value,
                $field instanceof ResolvesForEntity => $field->resolveFor($value, $node, $entity),
                default => $field->resolve($value, $node),
            }, $node);
        }

        return $resolved;
    }

    /**
     * What a list of blocks holds in fields whose type loads ahead ({@see PreloadsValues}),
     * handed over by type once, before a block of the list is rendered: a page of seven picture
     * blocks asked the library seven times, once per block, and asks it once. The nested lists
     * are walked too; a repeater's items are not — its fields resolve one item at a time.
     *
     * @param  iterable<array-key, mixed>  $blocks
     * @param  Closure(string): ?BlockType  $typeOf
     */
    public function preload(iterable $blocks, Closure $typeOf): void
    {
        $stored = [];
        $this->collect($blocks, $typeOf, $stored, 0);

        foreach ($stored as $id => $values) {
            $field = $this->types->get($id);

            if ($field instanceof PreloadsValues) {
                $field->preload($values);
            }
        }
    }

    /**
     * @param  iterable<array-key, mixed>  $blocks
     * @param  Closure(string): ?BlockType  $typeOf
     * @param  array<string, list<mixed>>  $stored  by field type
     */
    private function collect(iterable $blocks, Closure $typeOf, array &$stored, int $depth): void
    {
        // The renderer stops at its own depth; this walk only has to stop somewhere.
        if ($depth > 16) {
            return;
        }

        foreach ($blocks as $node) {
            if (! is_array($node) || ! is_string($node['type'] ?? null)) {
                continue;
            }

            $type = $typeOf($node['type']);
            $values = is_array($node['values'] ?? null) ? $node['values'] : [];

            if (! $type instanceof BlockType) {
                continue;
            }

            foreach (Schema::fields($type->schema, $this->types) as $name => $field) {
                if (! array_key_exists($name, $values)) {
                    continue;
                }

                $value = $values[$name];
                $id = (string) ($field['type'] ?? '');

                if ($id === 'wx-blocks') {
                    if (is_iterable($value)) {
                        $this->collect($value, $typeOf, $stored, $depth + 1);
                    }

                    continue;
                }

                if (! $this->types->get($id) instanceof PreloadsValues) {
                    continue;
                }

                // The language being read, the way `resolve()` picks it.
                if ($this->locales->isMap($value, ($field['localized'] ?? false) === true)) {
                    $value = $this->pick($value);
                }

                $stored[$id][] = $value;
            }
        }
    }

    /**
     * A resolved value with its shortcodes printed: a text field's as {@see ShortcodeText}, a rich
     * text field's inside its HTML, and the same for the fields of every repeater item.
     *
     * A text input of another kind — `email`, `url`, `tel` — is left as it is: its value goes
     * into an attribute, where HTML has no business, and its rules refuse a bracket anyway.
     *
     * @param  array<string, mixed>|null  $node
     */
    private function withShortcodes(mixed $value, ?array $node): mixed
    {
        if ($node === null) {
            return $value;
        }

        $type = (string) ($node['type'] ?? '');

        if (is_string($value) && in_array($type, ['wx-input', 'wx-textarea'], true)) {
            return in_array($node['props']['type'] ?? 'text', ['text', 'search'], true) ? $this->shortcodes->resolve($value) : $value;
        }

        if (is_string($value) && $type === 'wx-rich-text') {
            return $this->shortcodes->htmlIn($value);
        }

        if (is_array($value) && $type === 'wx-repeater') {
            $children = Tree::fields(Tree::children($node));

            return array_map(function (mixed $item) use ($children): mixed {
                if (! is_array($item)) {
                    return $item;
                }

                foreach ($children as $child) {
                    $name = (string) ($child['name'] ?? '');

                    if (array_key_exists($name, $item)) {
                        $item[$name] = $this->withShortcodes($item[$name], $child);
                    }
                }

                return $item;
            }, $value);
        }

        return $value;
    }

    /**
     * The language the site is being read in, then the site's default, then its fallback —
     * the chain every localized value is read through.
     *
     * @param  array<string, mixed>  $translations
     */
    private function pick(array $translations): mixed
    {
        foreach ($this->locales->chain() as $code) {
            $candidate = $translations[$code] ?? null;

            if ($candidate !== null && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }
}
