<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Facets\AbstractFacet;
use WebxUi\Catalog\Facets\BatchCountedFacet;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Models\Product;

/**
 * One property of {@see PropertySource} as a facet of terms.
 */
final class PropertyFacet extends AbstractFacet implements BatchCountedFacet
{
    public function __construct(
        private readonly string $property,
        private readonly PropertySource $source,
    ) {}

    public function key(): string
    {
        return 'p.'.$this->property;
    }

    protected function baseCode(): string
    {
        return $this->property;
    }

    public function kind(): FacetKind
    {
        return FacetKind::Terms;
    }

    public function label(): string
    {
        return ucfirst($this->property);
    }

    /**
     * @param  list<string>  $slugs
     * @return array<array-key, string>
     */
    public function resolveSlugs(array $slugs, string $locale): array
    {
        $known = DB::table(PropertySource::TABLE)->where('property', $this->property)->whereIn('value', $slugs)->distinct()->pluck('value')->all();

        return array_combine($known, $known);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function applySql(Builder $query, FacetValue $value): void
    {
        $query->whereIn($query->getModel()->qualifyColumn('id'), DB::table(PropertySource::TABLE)
            ->select('product_id')
            ->where('property', $this->property)
            ->whereIn('value', $value->values));
    }

    public function sqlValues(QueryBuilder $products): QueryBuilder
    {
        $this->source->singles[] = $this->key();

        return $products->newQuery()
            ->from(PropertySource::TABLE)
            ->select(['product_id', 'value'])
            ->where('property', $this->property)
            ->whereIn('product_id', clone $products);
    }

    public function sqlValuesMany(array $keys, QueryBuilder $products): QueryBuilder
    {
        $this->source->batches[] = $keys;
        $properties = array_map(static fn (string $key): string => substr($key, 2), $keys);

        return $products->newQuery()
            ->from(PropertySource::TABLE)
            ->selectRaw("'p.' || property as facet_key, product_id, value")
            ->whereIn('property', $properties)
            ->whereIn('product_id', clone $products);
    }
}
