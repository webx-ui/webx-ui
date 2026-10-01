<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests\Fixtures;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Documents\DocumentContributor;
use WebxUi\Catalog\Facets\IndexField;

/**
 * The fixtures' share of the search document: the colours of {@see ColourFacet} and the values of
 * {@see PropertySource}, the way a satellite puts its facets into an engine that keeps an index.
 */
final class FixtureDocument implements DocumentContributor
{
    public function fields(): array
    {
        return [
            (new ColourFacet)->field(),
            new IndexField(PropertyFacet::FIELD, IndexField::STRING, multi: true),
        ];
    }

    public function contribute(Collection $products, array $locales): array
    {
        $ids = $products->modelKeys();
        $documents = [];

        foreach ($ids as $id) {
            $documents[(int) $id] = ['colour' => [], PropertyFacet::FIELD => []];
        }

        foreach (DB::table(ColourFacet::TABLE)->whereIn('product_id', $ids)->get() as $row) {
            $documents[(int) $row->product_id]['colour'][] = (string) $row->colour;
        }

        foreach (DB::table(PropertySource::TABLE)->whereIn('product_id', $ids)->get() as $row) {
            $documents[(int) $row->product_id][PropertyFacet::FIELD][] = $row->property.':'.$row->value;
        }

        return $documents;
    }
}
