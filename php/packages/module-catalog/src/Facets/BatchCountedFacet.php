<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * A facet the engine counts together with its neighbours of the same source and kind, in one
 * query (§5.2 of the properties spec).
 *
 * Every facet that is not chosen counts over the same products — every choice applied — so one
 * `group by` answers for all of them; only a chosen facet needs a query of its own, because its
 * own choice must not narrow it. Two hundred properties and two choices are about five queries,
 * not two hundred.
 */
interface BatchCountedFacet extends Facet
{
    /**
     * Rows `(facet_key, product_id, value)` for every facet in `$keys` — all of one kind, all of
     * this facet's source — over the products `$products` selects (a query of one column, their
     * ids). The same pairs {@see Facet::sqlValues()} gives, with the key beside them.
     *
     * @param  list<string>  $keys
     */
    public function sqlValuesMany(array $keys, QueryBuilder $products): QueryBuilder;
}
