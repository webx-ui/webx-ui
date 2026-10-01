<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Facets;

use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\IndexField;
use WebxUi\CatalogProperties\Models\Property;

/**
 * A yes/no as a toggle: `wifi_yes`, the slug of «yes» the property's own in each language. Only
 * «yes» is stored, so «no» is simply not chosen (§3.1 of the properties spec).
 */
final class ToggleFacet extends PropertyFacet
{
    public const ON = '1';

    public function kind(): FacetKind
    {
        return FacetKind::Toggle;
    }

    public function indexable(): bool
    {
        return false;
    }

    public function field(): IndexField
    {
        return new IndexField('pb', IndexField::INT, multi: true);
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function labels(array $values, string $locale): array
    {
        return in_array(self::ON, $values, true) ? [self::ON => $this->property->displayName($locale)] : [];
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function slugs(array $values, string $locale): array
    {
        return in_array(self::ON, $values, true) ? [self::ON => $this->property->toggleSlug($locale)] : [];
    }

    /**
     * @param  list<string>  $slugs
     * @return array<array-key, string>
     */
    public function resolveSlugs(array $slugs, string $locale): array
    {
        $yes = $this->property->toggleSlug($locale);

        return in_array($yes, $slugs, true) ? [$yes => self::ON] : [];
    }

    /** `pb` holds the ids of the properties that say yes: «yes» here is this property's id. */
    public function indexValues(FacetValue $value): FacetValue
    {
        return $value->isEmpty() ? $value : FacetValue::of([(string) $this->property->id]);
    }

    public function splitIndexCounts(array $keys, array $counts): array
    {
        $split = [];

        foreach ($keys as $key) {
            $id = Property::idOfFacet($key);
            $split[$key] = $id !== null && isset($counts[$id]) ? [self::ON => $counts[$id]] : [];
        }

        return $split;
    }

    protected function narrow(QueryBuilder $rows, FacetValue $value): void
    {
        $rows->where(FacetRows::TABLE.'.flag', true);
    }
}
