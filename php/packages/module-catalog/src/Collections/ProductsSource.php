<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Collections;

use WebxUi\Admin\Collections\CollectionSource;
use WebxUi\Admin\Collections\Selection;
use WebxUi\Catalog\Rendering\ProductQuery;

/**
 * The products a `wx-collection` field shows: `{ "props": { "source": "products" } }`.
 *
 * A thin layer over {@see ProductQuery}, so an element here is the card `products()` gives a
 * template. No categories to choose from in the field: the catalogue's categories are a tree
 * with their own API, not the flat list `wx-categories` reads — a shelf of one category is
 * `products()->category(…)` in the block's template. No markup either: a product's own page
 * carries its `Product`, and a list of cards says nothing schema.org would add to it.
 */
final class ProductsSource implements CollectionSource
{
    public const KEY = 'products';

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return (string) __('webx-catalog::module.products');
    }

    public function categories(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return [];
    }

    public function supportsMarkup(): bool
    {
        return false;
    }

    public function permission(): string
    {
        return 'catalog.view';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(Selection $selection, string $locale): array
    {
        return (new ProductQuery)->selected($selection)->locale($locale)->get();
    }
}
