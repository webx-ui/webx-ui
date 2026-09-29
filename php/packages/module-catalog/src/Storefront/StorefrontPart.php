<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Storefront;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Models\Product;

/**
 * What a satellite puts into a point of the storefront (§10.1): the labels' badges on a card, the
 * stock's line under the price, the links' replacements on the page of a product no longer sold.
 *
 * `@webxPart` alone is one component per point, which a site overrides (§19, K1's summary); a
 * point several modules write into needs a list. This is that list's entry: a view, and what it
 * needs for a whole page of products in one query — a grid of twenty-four cards must not be
 * twenty-four queries per satellite.
 */
interface StorefrontPart
{
    /** `catalog.card.badges`, `catalog.card.meta`, `catalog.product.aside`, `catalog.product.tabs`, `catalog.product.unavailable`. */
    public function point(): string;

    /** The Blade view printed in the point, with the point's data and whatever {@see prepare()} gave. */
    public function view(): string;

    /**
     * Load what the view will need for every product on the page, once.
     *
     * @param  Collection<int, Product>  $products
     * @return array<string, mixed> passed to the view of every product on the page
     */
    public function prepare(Collection $products): array;
}
