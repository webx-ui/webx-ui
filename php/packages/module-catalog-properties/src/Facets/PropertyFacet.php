<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Facets;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\Catalog\Facets\AbstractFacet;
use WebxUi\Catalog\Facets\BatchCountedFacet;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\CatalogProperties\Models\Property;

/**
 * A property as a facet (§5 of the properties spec). One class per shape — a reference book, a
 * tree, a slider, intervals, a toggle — over one table of values, and all of them counted in
 * batches: every facet of properties that is not chosen counts over the same products, so one
 * query answers for all of one kind ({@see FacetRows}).
 *
 * The key is `p.{id}`, one in every language and never in an address; the code is the property's
 * own in the language asked, falling back through the languages as any translation does.
 *
 * A value of a property outside the set of the product's main category is not in any count and
 * matches no choice (decision 6): kept, not shown.
 */
abstract class PropertyFacet extends AbstractFacet implements BatchCountedFacet
{
    public function __construct(protected readonly Property $property) {}

    public static function for(Property $property): self
    {
        return match (true) {
            $property->isTree() => new TreeValueFacet($property),
            $property->isSelect() && $property->value_order === Property::MANUAL => new OrderedValueFacet($property),
            $property->isSelect() => new ValueFacet($property),
            $property->usesIntervals() => new IntervalFacet($property),
            $property->isNumber() => new RangeFacet($property),
            default => new ToggleFacet($property),
        };
    }

    public function property(): Property
    {
        return $this->property;
    }

    public function key(): string
    {
        return $this->property->facetKey();
    }

    public function code(string $locale): string
    {
        return $this->property->codeIn($locale);
    }

    public function label(): string
    {
        return $this->property->displayName();
    }

    public function indexable(): bool
    {
        return $this->property->is_indexable;
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function applySql(Builder $query, FacetValue $value): void
    {
        $matching = $query->getQuery()->newQuery()
            ->from(FacetRows::TABLE)
            ->select(FacetRows::TABLE.'.product_id')
            ->where(FacetRows::TABLE.'.property_id', $this->property->id);

        $this->narrow($matching, $value);
        Container::getInstance()->make(PropertySets::class)->whereInSet($matching, FacetRows::TABLE);

        $query->whereIn($query->getModel()->qualifyColumn('id'), $matching);
    }

    public function sqlValues(QueryBuilder $products): QueryBuilder
    {
        return $this->sqlValuesMany([$this->key()], $products);
    }

    public function sqlValuesMany(array $keys, QueryBuilder $products): QueryBuilder
    {
        return FacetRows::of($keys, $products);
    }

    /** The rows of the values table that match the choice. */
    abstract protected function narrow(QueryBuilder $rows, FacetValue $value): void;

    /**
     * @param  list<string>  $values
     * @return list<int>
     */
    protected static function ids(array $values): array
    {
        return array_values(array_filter(array_map('intval', $values), static fn (int $id): bool => $id > 0));
    }
}
