<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Storefront;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Rendering\ProductQuery;
use WebxUi\CatalogProperties\Catalog\Properties;
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\CatalogProperties\Facets\FacetRows;
use WebxUi\CatalogProperties\Facets\RangeFacet;
use WebxUi\CatalogProperties\Facets\ToggleFacet;
use WebxUi\CatalogProperties\Facets\TreeValueFacet;
use WebxUi\CatalogProperties\Facets\ValueFacet;
use WebxUi\CatalogProperties\Models\Property;

/**
 * `products()->property(…)` — a shelf of a template narrowed by a property (§8.3 of the
 * properties spec), a macro over `narrowedBy` as the dictionaries' are:
 *
 *     products()->property('color', 'black')             // a value, by slug in the language read or by id
 *     products()->property('color', ['black', 'grey'])   // any of them
 *     products()->property('material', 'metal')          // a node of a tree takes what is under it
 *     products()->property('weight', min: 1, max: 2)     // both ends included; either may be left out
 *     products()->property('wifi')                       // «yes»
 *
 * The property is its code in the language read, or its id. Without a value it is «has any value».
 * Only a value of the set of the product's main category counts (decision 6), as in the filter.
 * A property or a value nobody has matches no product rather than all of them. Each property is a
 * step of its own, so two properties narrow together and the same one again replaces the first.
 */
final class PropertyQuery
{
    public static function register(): void
    {
        ProductQuery::macro('property', function (int|string $property, mixed $value = null, int|float|null $min = null, int|float|null $max = null): ProductQuery {
            /** @var ProductQuery $this */
            return $this->narrowedBy('property:'.$property, static function (Builder $query, string $locale) use ($property, $value, $min, $max): void {
                PropertyQuery::narrow($query, $locale, $property, $value, $min, $max);
            });
        });
    }

    /**
     * @param  Builder<covariant Product>  $query
     */
    public static function narrow(Builder $query, string $locale, int|string $given, mixed $value, int|float|null $min, int|float|null $max): void
    {
        $property = self::find($given, $locale);

        if ($property === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        /** @var Builder<Product> $query */
        match (true) {
            $property->isNumber() && ($min !== null || $max !== null || is_numeric($value)) => (new RangeFacet($property))->applySql(
                $query,
                is_numeric($value) ? FacetValue::range((float) $value, (float) $value) : FacetValue::range($min === null ? null : (float) $min, $max === null ? null : (float) $max),
            ),
            $property->isBool() => (new ToggleFacet($property))->applySql($query, FacetValue::of([ToggleFacet::ON])),
            $property->isSelect() && $value !== null && $value !== [] => self::values($query, $property, $value, $locale),
            default => self::any($query, $property),
        };
    }

    /**
     * @param  Builder<Product>  $query
     */
    private static function values(Builder $query, Property $property, mixed $value, string $locale): void
    {
        $facet = $property->isTree() ? new TreeValueFacet($property) : new ValueFacet($property);
        $given = array_values(array_filter(
            array_map(static fn (mixed $one): string => trim((string) (is_scalar($one) ? $one : '')), is_array($value) ? $value : [$value]),
            static fn (string $one): bool => $one !== '',
        ));

        $ids = [
            ...array_filter($given, 'ctype_digit'),
            ...array_values($facet->resolveSlugs(array_values(array_filter($given, static fn (string $one): bool => ! ctype_digit($one))), $locale)),
        ];

        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $facet->applySql($query, $facet->normalise(FacetValue::of(array_values(array_unique($ids)))));
    }

    /**
     * Any value of the property, of the set.
     *
     * @param  Builder<Product>  $query
     */
    private static function any(Builder $query, Property $property): void
    {
        $having = $query->getQuery()->newQuery()
            ->from(FacetRows::TABLE)
            ->select(FacetRows::TABLE.'.product_id')
            ->where(FacetRows::TABLE.'.property_id', $property->id);

        Container::getInstance()->make(PropertySets::class)->whereInSet($having, FacetRows::TABLE);

        $query->whereIn($query->getModel()->qualifyColumn('id'), $having);
    }

    private static function find(int|string $given, string $locale): ?Property
    {
        $properties = Container::getInstance()->make(Properties::class);
        $given = trim((string) $given);

        if (ctype_digit($given)) {
            return $properties->find((int) $given);
        }

        foreach ($properties->all() as $property) {
            if ($property->codeIn($locale) === $given) {
                return $property;
            }
        }

        return null;
    }
}
