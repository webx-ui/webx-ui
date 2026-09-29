<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Panel;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Models\Product;

/**
 * A column a satellite adds to the panel's list of products (§7.4): the stock, the brand.
 *
 * Its values come a page at a time, in one query — the list is twenty rows and must not become
 * twenty-one queries per column. A column that can be sorted by names a sort of the registry
 * (§7.2), so the list sorts through the engine like any other order.
 */
interface ProductColumn
{
    /** `[a-z0-9._-]`: the key of the value in each row. */
    public function key(): string;

    public function label(): string;

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, mixed> product id → a value the panel prints as it is
     */
    public function values(Collection $products): array;

    /** The key of the sort that orders by this column, or null when it does not sort. */
    public function sort(): ?string;
}
