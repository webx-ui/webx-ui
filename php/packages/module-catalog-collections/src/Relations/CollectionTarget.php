<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Relations\RelationTarget;
use WebxUi\CatalogCollections\Models\ProductCollection;

/**
 * The collections of the site, for whatever chooses one: the block `product-collection` first
 * (`wx-catalog-collection`, §4.2).
 *
 * No permission: the name of a collection is no secret, and whoever puts one on a page is not
 * somebody who has to be able to build one. A collection that is off stays chosen and is marked in
 * the picker: the site prints nothing for it until it is turned on again.
 */
final class CollectionTarget extends RelationTarget
{
    public const KEY = 'catalog-collection';

    public function __construct()
    {
        parent::__construct(
            key: self::KEY,
            model: ProductCollection::class,
            permission: null,
            label: 'webx-catalog-collections::module.collection',
        );
    }

    public function visible(Model $record, ?string $locale = null): bool
    {
        return $record instanceof ProductCollection && $record->is_enabled && ! $record->trashed();
    }

    /**
     * The row with the key under its own name: `wx-catalog-collection` keeps the key rather than
     * the id, and a picker that read it off the subtitle would break the day the subtitle says more.
     *
     * @return array{id: int, title: string, subtitle: string|null, thumb: string|null, visible: bool, trashed: bool, key?: string}
     */
    public function row(Model $record, string $locale): array
    {
        $row = parent::row($record, $locale);

        if ($record instanceof ProductCollection) {
            $row['key'] = $record->key;
        }

        return $row;
    }

    protected function subtitle(Model $record, string $locale): ?string
    {
        return $record instanceof ProductCollection ? $record->key : null;
    }

    /**
     * By the title, as every target, and by the key: the field names a stored key by asking for
     * exactly it, so the collection whose key it is comes first.
     *
     * @param  Builder<Model>  $query
     */
    protected function search(Builder $query, string $term, string $locale): void
    {
        $titles = $query->getModel()->newQuery();
        parent::search($titles, $term, $locale);

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
        $key = $query->getModel()->qualifyColumn('key');

        $query->where(static function (Builder $where) use ($titles, $key, $like): void {
            $where->addNestedWhereQuery($titles->getQuery())->orWhere($key, 'like', $like);
        })->orderByRaw($key.' = ? desc', [$term]);
    }
}
