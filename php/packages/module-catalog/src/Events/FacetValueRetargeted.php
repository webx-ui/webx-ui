<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Events;

use WebxUi\Catalog\Filter\FilterAliases;

/**
 * A facet's value stopped being itself (§9 of the landings spec): merged into another, or deleted
 * (`to` null). Fired from {@see FilterAliases::retarget()}, which every such place in the family
 * already calls, so whoever keeps values by id — a landing's set — listens to one event rather than
 * to every satellite's models.
 */
final class FacetValueRetargeted
{
    public function __construct(
        public readonly string $facetKey,
        public readonly string $from,
        public readonly ?string $to,
    ) {}
}
