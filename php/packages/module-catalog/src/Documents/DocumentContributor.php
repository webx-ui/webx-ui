<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Documents;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Facets\IndexField;
use WebxUi\Catalog\Models\Product;

/**
 * A module's share of a product's search document (§7.3, §4.2 of the architecture).
 *
 * A batch at a time, never a product: the worker asks every contributor about the same five
 * hundred products in one pass, and a contributor answers with one query per batch, not one per
 * product.
 */
interface DocumentContributor
{
    /** @return list<IndexField> */
    public function fields(): array;

    /**
     * @param  Collection<int, Product>  $products
     * @param  list<string>  $locales  The site's languages: a localised field is one per language.
     * @return array<int, array<string, mixed>> product id → field → value
     */
    public function contribute(Collection $products, array $locales): array;
}
