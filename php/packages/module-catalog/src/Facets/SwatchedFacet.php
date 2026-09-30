<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

/**
 * A facet whose values carry a colour or a picture the filter draws before the label (§8.2 of
 * the properties spec). Data, not style: the partial draws a dot, the site decides what it looks like.
 */
interface SwatchedFacet extends Facet
{
    /**
     * For the values of one group of the filter at once, not one by one.
     *
     * @param  list<string>  $values
     * @return array<array-key, array{color: string|null, image: string|null}> value → swatch; a value with neither is left out.
     */
    public function swatches(array $values): array;
}
