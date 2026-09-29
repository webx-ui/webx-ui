<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Popularity;

/**
 * Something that says how popular a product is (§7.8, §9): the core's views, a future cart's
 * sales. Weighed by `webx-catalog.popularity.weights.<key>`; a signal without a weight counts
 * for nothing, so a satellite adds one without changing anybody's order until a site asks.
 */
interface PopularitySignal
{
    /** `[a-z0-9_]`: the name its weight is configured under. */
    public function key(): string;

    /**
     * @param  list<int>  $productIds
     * @return array<int, float> product id → value; a product without one is left out
     */
    public function values(array $productIds): array;
}
