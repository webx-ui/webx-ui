<?php

declare(strict_types=1);

namespace WebxUi\CatalogLabels\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Documents\DocumentContributor;
use WebxUi\CatalogLabels\Models\Label;

/**
 * The labels in a product's search document: the ids of those in the filter, the field the facet
 * filters by in an engine that keeps an index.
 */
final class LabelsDocument implements DocumentContributor
{
    public function fields(): array
    {
        return [(new LabelFacet)->field()];
    }

    public function contribute(Collection $products, array $locales): array
    {
        $documents = [];

        foreach ($products as $product) {
            $documents[(int) $product->id] = ['labels' => []];
        }

        $filterable = static function (Builder $query): void {
            $query->where('catalog_labels.is_visible', true);
        };

        foreach (Labels::on($products->modelKeys(), narrow: $filterable) as $id => $labels) {
            $documents[$id] = ['labels' => array_map(static fn (Label $label): int => $label->id, $labels)];
        }

        return $documents;
    }
}
