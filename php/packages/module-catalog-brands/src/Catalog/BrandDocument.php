<?php

declare(strict_types=1);

namespace WebxUi\CatalogBrands\Catalog;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Documents\DocumentContributor;

/**
 * The brand in a product's search document: the id the facet filters by — only a published
 * brand's, as the facet counts only those. Publishing or hiding a brand marks its products, so the
 * document follows.
 */
final class BrandDocument implements DocumentContributor
{
    public function fields(): array
    {
        return [(new BrandFacet)->field()];
    }

    public function contribute(Collection $products, array $locales): array
    {
        $documents = [];
        $of = Brands::of($products->modelKeys(), visible: true);

        foreach ($products as $product) {
            $documents[(int) $product->id] = ['brand' => ($of[(int) $product->id] ?? null)?->id];
        }

        return $documents;
    }
}
