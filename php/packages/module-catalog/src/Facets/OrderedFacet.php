<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Facets;

/**
 * A facet of terms whose values stand in an order an editor chose — labels, stock statuses — rather
 * than in the alphabet the filter sorts terms by otherwise.
 *
 * {@see Facet::labels()} of such a facet answers in that order, and the filter keeps it: "Top,
 * Sale, New" is a sentence somebody arranged, and alphabetised it is "New, Sale, Top".
 */
interface OrderedFacet extends Facet {}
