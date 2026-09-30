<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Storefront;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Storefront\StorefrontPart;

/**
 * The short list on a card of the catalogue (`catalog.card.meta`): the properties marked «in the
 * card», in the order of the set — «Diagonal: 15.6″».
 */
final class CardProperties implements StorefrontPart
{
    public function point(): string
    {
        return 'catalog.card.meta';
    }

    public function view(): string
    {
        return 'webx-catalog-properties::card';
    }

    public function prepare(Collection $products): array
    {
        // Scoped to the request: resolved here, not kept, so a worker's next request starts clean.
        $properties = Container::getInstance()->make(ProductProperties::class);
        $properties->load($products);

        return ['productProperties' => $properties];
    }
}
