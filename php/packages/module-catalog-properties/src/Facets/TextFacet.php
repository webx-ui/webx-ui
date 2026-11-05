<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Facets;

use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\IndexField;
use WebxUi\Catalog\Facets\TypedFacet;
use WebxUi\CatalogProperties\Models\Property;

/**
 * A text property in a selection (§5.1 of the feeds spec): the whole value in one language. It is
 * never a facet of the storefront, so it has no address, no count and no place in an index: only
 * {@see PropertySource::allFacets()} makes one.
 */
final class TextFacet extends PropertyFacet implements TypedFacet
{
    public function __construct(Property $property, private readonly string $locale)
    {
        parent::__construct($property);
    }

    public function kind(): FacetKind
    {
        return FacetKind::Terms;
    }

    public function indexable(): bool
    {
        return false;
    }

    public function field(): IndexField
    {
        return new IndexField('pt', IndexField::STRING, multi: true);
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function labels(array $values, string $locale): array
    {
        return array_combine($values, $values);
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function slugs(array $values, string $locale): array
    {
        return [];
    }

    /**
     * @param  list<string>  $slugs
     * @return array<array-key, string>
     */
    public function resolveSlugs(array $slugs, string $locale): array
    {
        return [];
    }

    protected function narrow(QueryBuilder $rows, FacetValue $value): void
    {
        $rows->whereIn(FacetRows::TABLE.'.text->'.$this->locale, $value->values);
    }
}
