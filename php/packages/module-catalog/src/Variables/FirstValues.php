<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Variables;

use Illuminate\Support\Collection;
use WebxUi\Catalog\Models\Product;

/**
 * A variable that may hold several values in one text. Whoever needs just one asks here instead of
 * splitting the text: a separator can also be a part of a value.
 */
interface FirstValues
{
    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, string> product id → its first value
     */
    public function firstValues(Collection $products, string $locale): array;
}
