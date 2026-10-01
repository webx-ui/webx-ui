<?php

declare(strict_types=1);

namespace WebxUi\CatalogStock\Catalog;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Documents\DocumentContributor;
use WebxUi\Catalog\Facets\IndexField;

/**
 * The stock in a product's search document: the status the facet filters by, and whether it can
 * be bought — what a future "only in stock" switch and "in stock first" order will read (§11).
 */
final class StockDocument implements DocumentContributor
{
    public function fields(): array
    {
        return [(new StockFacet)->field(), new IndexField('purchasable', IndexField::BOOL)];
    }

    public function contribute(Collection $products, array $locales): array
    {
        $documents = [];
        $of = Stock::of($products->modelKeys());

        foreach ($products as $product) {
            $status = $of[(int) $product->id] ?? null;
            $documents[(int) $product->id] = [
                // Only a status in the filter, as the facet counts only those: a hidden or binned
                // one is no value in an index either (§6 of the Manticore spec).
                'stock' => $status !== null && $status->is_visible && $status->deleted_at === null ? $status->id : null,
                'purchasable' => $status === null || $status->is_purchasable,
            ];
        }

        return $documents;
    }
}
