<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Rendering;

use WebxUi\Admin\Screens\FieldTypes;
use WebxUi\Admin\Screens\ResolvesMissing;
use WebxUi\Admin\Screens\ScreenValues;
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
 * The other direction is {@see ContentValues}: the same walk over the same schema, asking the
 * type what to keep rather than what to hand over.
 */
final readonly class Values
{
    public function __construct(
        private FieldTypes $types,
        private Locales $locales,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function resolve(BlockType $type, array $values): array
    {
        $fields = Schema::fields($type->schema, $this->types);
        $resolved = [];

        // A field nobody wrote is null in the template — except where the type says what an
        // absent value means ({@see ResolvesMissing}): a collection block never touched is the
        // whole collection, not an empty list.
        foreach ($fields as $name => $node) {
            $field = $this->types->get((string) ($node['type'] ?? ''));

            if ($field instanceof ResolvesMissing && ! array_key_exists($name, $values)) {
                $resolved[$name] = $field->resolve(null, $node);
            }
        }

        foreach ($values as $name => $value) {
            $node = $fields[(string) $name] ?? null;

            // A localized field keeps a language map, and a template wants one language. This
            // is the same step a described screen takes on the way to the site
            // ({@see ScreenValues::resolve()}) — without it the template is handed the map,
            // Blade refuses to print an array, and the block renders as nothing at all.
            if ($node !== null && ($node['localized'] ?? false) === true && is_array($value)) {
                $value = $this->pick($value);
            }

            $field = $node === null ? null : $this->types->get((string) ($node['type'] ?? ''));

            $resolved[$name] = $field === null || $node === null ? $value : $field->resolve($value, $node);
        }

        return $resolved;
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
