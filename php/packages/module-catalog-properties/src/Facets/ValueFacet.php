<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Facets;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use WebxUi\Catalog\Facets\FacetKind;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\IndexField;
use WebxUi\CatalogProperties\Models\PropertyValue;
use WebxUi\Localization\Locales;

/**
 * A reference book as terms: `color_black`. The value is the id of the value; its slug is the
 * value's in the language of the page, falling back as a translation does, and the id where no
 * language has one.
 */
class ValueFacet extends PropertyFacet
{
    public function kind(): FacetKind
    {
        return FacetKind::Terms;
    }

    public function field(): IndexField
    {
        return new IndexField('pv', IndexField::INT, multi: true);
    }

    /**
     * @param  list<string>  $values
     * @return array<array-key, string>
     */
    public function labels(array $values, string $locale): array
    {
        $labels = [];

        foreach ($this->load($values) as $value) {
            $labels[(string) $value->id] = $value->displayName($locale);
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

        foreach ($this->load($values) as $value) {
            $slugs[(string) $value->id] = self::slugOf($value, $locale);
        }

        return $slugs;
    }

    /**
     * @param  list<string>  $slugs
     * @return array<array-key, string>
     */
    public function resolveSlugs(array $slugs, string $locale): array
    {
        if ($slugs === []) {
            return [];
        }

        $chain = Container::getInstance()->make(Locales::class)->chain($locale);
        $numeric = array_values(array_filter($slugs, 'ctype_digit'));

        $candidates = PropertyValue::query()
            ->where('property_id', $this->property->id)
            ->where(static function ($any) use ($chain, $slugs, $numeric): void {
                foreach ($chain as $one) {
                    $any->orWhereIn('slug->'.$one, $slugs);
                }

                if ($numeric !== []) {
                    $any->orWhereIn('id', array_map('intval', $numeric));
                }
            })
            ->get();

        $resolved = [];

        foreach ($candidates as $value) {
            $slug = self::slugOf($value, $locale);

            if (in_array($slug, $slugs, true)) {
                $resolved[$slug] = (string) $value->id;
            }
        }

        return $resolved;
    }

    public static function slugOf(PropertyValue $value, string $locale): string
    {
        $slug = $value->getTranslation('slug', $locale);

        return is_string($slug) && $slug !== '' ? $slug : (string) $value->id;
    }

    protected function narrow(QueryBuilder $rows, FacetValue $value): void
    {
        $rows->whereIn(FacetRows::TABLE.'.value_id', self::ids($value->values) ?: [0]);
    }

    /**
     * @param  list<string>  $values
     * @return Collection<int, PropertyValue>
     */
    protected function load(array $values): Collection
    {
        return PropertyValue::query()
            ->where('property_id', $this->property->id)
            ->whereKey(self::ids($values))
            ->orderBy('lft')
            ->get();
    }
}
