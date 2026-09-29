<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

/**
 * What a facet is shaped like, which decides how the engine counts it and how the filter draws
 * it (§7.1). One kind per shape rather than per module: a brand and a colour are both terms, and
 * the category and a tree of materials are both trees (§8.3 of the architecture).
 */
enum FacetKind: string
{
    /** Values with a count each: brands, colours. Several may be chosen. */
    case Terms = 'terms';

    /** A number between two ends: the price. The engine answers the lowest and the highest. */
    case Range = 'range';

    /** On or off, with the number of products it leaves: "in stock". */
    case Toggle = 'toggle';

    /** Values with ancestors; choosing one means any of its descendants. Counts include them. */
    case Tree = 'tree';
}
