<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

/**
 * A facet that words the title of its own first level: «Laptops in black» where the core's
 * template «{category} {value}» (decision 17 of the architecture) would say «Laptops Black».
 * Null is the core's template.
 */
interface TitledFacet extends Facet
{
    /**
     * @param  string  $where  the category's name, or a brand's, or the root's
     * @param  string  $label  the chosen value's label
     */
    public function filterTitle(string $where, string $label, string $locale): ?string;
}
