<?php

declare(strict_types=1);

namespace WebxUi\Cart\Contracts;

use WebxUi\Cart\PurchasableChoice;

/**
 * A provider whose records a manager can find by a word: the panel's «New order» looks for what
 * to put in a line through it. Optional on top of `Purchasable`, like the options — a provider
 * without it is simply not offered in the search.
 */
interface FindsPurchasables
{
    /**
     * Records matching the term — by name, article number, whatever tells them apart — the best
     * first. The unbuyable ones may come too, marked `available = false` in their line: the
     * manager sees why a product is not offered rather than not finding it at all.
     *
     * @return list<PurchasableChoice>
     */
    public function find(string $term, string $locale, int $limit): array;
}
