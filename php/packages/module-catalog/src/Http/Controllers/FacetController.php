<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Catalog\Facets\Facet;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Sorts\Sort;
use WebxUi\Catalog\Sorts\Sorts;

/**
 * `GET /api/cms/catalog/facets` (§11.2): the registry as the panel needs it — every facet with
 * its key, its code in an address, its kind and its name, in the registry's order — for the
 * filter of the list and the «Filters» tab of a category. The sorts come along: the list offers
 * every one of them, and the registry is the one place that knows which exist.
 */
final class FacetController
{
    public function __invoke(Facets $facets, Sorts $sorts): JsonResponse
    {
        return new JsonResponse([
            'data' => array_map(static fn (Facet $facet): array => [
                'key' => $facet->key(),
                'code' => $facet->code(),
                'kind' => $facet->kind()->value,
                'label' => $facet->label(),
                'indexable' => $facet->indexable(),
            ], $facets->all()),
            'meta' => [
                'sorts' => array_map(static fn (Sort $sort): array => ['key' => $sort->key(), 'label' => $sort->label()], $sorts->all()),
            ],
        ]);
    }
}
