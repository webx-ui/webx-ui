<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Facets;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\IndexField;
use WebxUi\Catalog\Facets\OrderedFacet;
use WebxUi\CatalogProperties\Models\PropertyInterval;

/**
 * A number filtered by intervals the admin set — `weight_up-to-1-kg` (decision 11). The product
 * keeps its number; the facet lays it out: `[min, max)`, a null end open, and intervals that
 * overlap each counted honestly. Always in their position — lowest first is the admin's care.
 */
final class IntervalFacet extends PropertyFacet implements OrderedFacet
{
    /** @var Collection<int, PropertyInterval>|null */
    private ?Collection $intervals = null;

    public function kind(): FacetKind
    {
        return FacetKind::Terms;
    }

    public function field(): IndexField
    {
        return new IndexField('pi', IndexField::INT, multi: true);
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function labels(array $values, string $locale): array
    {
        $labels = [];

        foreach ($this->load($values) as $interval) {
            $labels[(string) $interval->id] = $interval->displayName($locale);
        }

        return $labels;
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function slugs(array $values, string $locale): array
    {
        $slugs = [];

        foreach ($this->load($values) as $interval) {
            $slugs[(string) $interval->id] = self::slugOf($interval, $locale);
        }

        return $slugs;
    }

    /**
     * @param  list<string>  $slugs
     * @return array<array-key, string>
     */
    public function resolveSlugs(array $slugs, string $locale): array
    {
        $resolved = [];

        foreach ($this->intervals() as $interval) {
            $slug = self::slugOf($interval, $locale);

            if (in_array($slug, $slugs, true)) {
                $resolved[$slug] = (string) $interval->id;
            }
        }

        return $resolved;
    }

    public static function slugOf(PropertyInterval $interval, string $locale): string
    {
        $slug = $interval->getTranslation('slug', $locale);

        return is_string($slug) && $slug !== '' ? $slug : (string) $interval->id;
    }

    protected function owners(array $values): array
    {
        return PropertyInterval::query()->whereKey($values)->pluck('property_id', 'id')
            ->mapWithKeys(static fn (mixed $property, mixed $id): array => [(int) $id => (int) $property])
            ->all();
    }

    protected function narrow(QueryBuilder $rows, FacetValue $value): void
    {
        $chosen = $this->load($value->values);

        if ($chosen->isEmpty()) {
            $rows->whereRaw('1 = 0');

            return;
        }

        $rows->where(static function (QueryBuilder $any) use ($chosen): void {
            foreach ($chosen as $interval) {
                $any->orWhere(static function (QueryBuilder $one) use ($interval): void {
                    FacetRows::within($one, FacetRows::TABLE.'.number', $interval->low(), $interval->high());
                });
            }
        });
    }

    /**
     * @param  list<string>  $values
     * @return Collection<int, PropertyInterval>
     */
    private function load(array $values): Collection
    {
        $ids = array_flip(self::ids($values));

        return $this->intervals()->filter(static fn (PropertyInterval $interval): bool => isset($ids[(int) $interval->id]))->values();
    }

    /**
     * A page asks for the same few intervals many times — labels, slugs, links — so once.
     *
     * @return Collection<int, PropertyInterval>
     */
    private function intervals(): Collection
    {
        return $this->intervals ??= PropertyInterval::query()
            ->where('property_id', $this->property->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }
}
