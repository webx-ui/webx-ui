<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

/**
 * A facet whose values have ancestors (§8.3 of the architecture): what the filter needs to draw
 * the counted values as a tree rather than a list.
 */
interface TreeFacet extends Facet
{
    /**
     * @param  list<string>  $values
     * @return array<array-key, string|null> value → its parent's value, null at the top; in tree order.
     */
    public function parents(array $values): array;
}
