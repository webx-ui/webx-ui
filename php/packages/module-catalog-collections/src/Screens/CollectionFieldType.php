<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Screens;

use WebxUi\Admin\Screens\ChecksNewValues;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\CatalogCollections\Models\ProductCollection;

/**
 * `wx-catalog-collection`: one collection, by its key, which is what the tag takes and what a block
 * of the site keeps (§4.2).
 *
 * A key no collection has is refused, but only when it is being written ({@see ChecksNewValues}): a
 * collection put in the bin after the page was saved must not stop the page from being saved again,
 * while a key typed by an agent or by hand is the collection that silently never appears. One in
 * the bin is not offered: its key is taken, and the site prints nothing for it.
 *
 * Empty is "none", stored as null.
 */
final class CollectionFieldType implements ChecksNewValues, FieldType
{
    /** How many keys a refusal lists: enough to pick from, not a page of them. */
    private const LISTED = 20;

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'string', 'max:'.ProductCollection::KEY_LENGTH];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): ?string
    {
        return self::key($value);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): ?string
    {
        return self::key($stored);
    }

    /**
     * A key no collection has, in a value written now, with the keys there are, so whoever wrote
     * it, an agent most of all, can put the right one in the next call.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    public function newValueProblems(mixed $value, array $node): array
    {
        $key = self::key($value);

        if ($key === null || ProductCollection::query()->where('key', $key)->exists()) {
            return [];
        }

        $keys = ProductCollection::query()->orderBy('id')->limit(self::LISTED)->pluck('key')->all();

        return [$keys === []
            ? (string) __('webx-catalog-collections::errors.key-unknown-none', ['key' => $key])
            : (string) __('webx-catalog-collections::errors.key-unknown', ['key' => $key, 'keys' => implode(', ', $keys)])];
    }

    private static function key(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $key = trim($value);

        return $key === '' ? null : $key;
    }
}
