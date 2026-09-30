<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Storefront;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Storefront\StorefrontPart;

/**
 * «Specifications» on the page of a product (`catalog.product.tabs`): the properties marked «on
 * the page», by group in the order of the set. A reference book's value whose first level is an
 * open page of the category links to it.
 *
 * The values are the ones {@see CardProperties} loads for the same page — the core prepares every
 * part of a page in one go, and the card's is always among them — so this part reads nothing of
 * its own: a second load would be the same query again.
 */
final class SpecsTable implements StorefrontPart
{
    public function point(): string
    {
        return 'catalog.product.tabs';
    }

    public function view(): string
    {
        return 'webx-catalog-properties::table';
    }

    public function prepare(Collection $products): array
    {
        return ['productProperties' => Container::getInstance()->make(ProductProperties::class)];
    }
}
