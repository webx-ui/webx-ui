<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Facets;

use WebxUi\Catalog\Facets\OrderedFacet;

/**
 * A reference book whose values stand «by hand» (decision 16): the filter keeps the order an
 * editor dragged them into — the tree's `lft` — rather than the alphabet.
 */
final class OrderedValueFacet extends ValueFacet implements OrderedFacet {}
