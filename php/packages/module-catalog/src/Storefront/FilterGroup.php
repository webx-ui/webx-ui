<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Storefront;

use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\FacetKind;

/**
 * One facet of the filter, ready to draw: its options, or for a range the ends, what is chosen,
 * and where the form without JavaScript sends the numbers (§10.2).
 */
final class FilterGroup
{
    /**
     * @param  list<FilterOption>  $options
     * @param  array{min: float|null, max: float|null, from: float|null, to: float|null, action: string, field: string}|null  $range
     */
    public function __construct(
        public readonly Facet $facet,
        public readonly array $options = [],
        public readonly ?array $range = null,
        public readonly ?string $resetUrl = null,
    ) {}

    public function kind(): FacetKind
    {
        return $this->facet->kind();
    }

    /** The partial that draws this kind: `webx-catalog::filter.terms` and so on. */
    public function view(): string
    {
        return 'webx-catalog::filter.'.$this->kind()->value;
    }

    public function isEmpty(): bool
    {
        return $this->options === [] && ($this->range === null || $this->range['min'] === null);
    }
}
