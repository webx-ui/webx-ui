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

    /**
     * The choice as it lies in {@see Facet::field()}, for an engine that keeps an index. A field
     * several facets share speaks the field's values, not the facet's: a property's «yes» is the
     * property's id among the ids of every property that says yes.
     */
    public function indexValues(FacetValue $value): FacetValue;

    /**
     * One count of a field the facets in `$keys` share — one `FACET` for all of them, as an
     * engine with an index asks it (decision 24 of the Manticore spec) — laid out per facet, in
     * each facet's own values. A value of the field that belongs to none of them is dropped; a
     * toggle answers its products under {@see FacetValue} `'1'`.
     *
     * @param  list<string>  $keys
     * @param  array<string, int>  $counts  the field's value → products
     * @return array<string, array<string, int>> facet key → value → products
     */
    public function splitIndexCounts(array $keys, array $counts): array;
}
