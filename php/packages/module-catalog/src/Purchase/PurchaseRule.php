<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Purchase;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Models\Product;

/**
 * One link of the chain that decides whether a product can be bought (§7.5, §4.4 of the
 * architecture): the stock's "out of stock", the links' "discontinued, take this instead", the
 * core's "not on sale" and "price on request".
 *
 * A batch at a time: a category page asks about its whole grid at once.
 */
interface PurchaseRule
{
    /**
     * The products this rule refuses, and why. A product it has nothing against is left out.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, Verdict> product id → a refusal
     */
    public function refuse(Collection $products): array;
}
