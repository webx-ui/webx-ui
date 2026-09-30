<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Catalog;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection as Rows;
use WebxUi\Catalog\Documents\DocumentContributor;
use WebxUi\Catalog\Facets\IndexField;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyInterval;
use WebxUi\CatalogProperties\Models\PropertyValue;

/**
 * The properties in a product's search document (§6 of the properties spec) — only of the set of
 * its main category (decision 6):
 *
 * - `pv` — ids of the values of reference books, with their ancestors;
 * - `pi` — ids of the intervals a number falls into, so the facet of intervals counts nothing;
 * - `pn` — the numbers by property id, `{ "12": 1.35 }`, as JSON: a new property is no new field;
 * - `pb` — ids of the properties that say «yes»;
 * - `pt_{locale}` — the names of values and the texts of the properties searched by value.
 *
 * A batch at a time: the values of every product in one query, the words of the values in a second.
 */
final class PropertiesDocument implements DocumentContributor
{
    public function __construct(
        private readonly Properties $properties,
        private readonly PropertySets $sets,
        private readonly ProductValues $values,
    ) {}

    public function fields(): array
    {
        return [
            new IndexField('pv', IndexField::INT, multi: true),
            new IndexField('pi', IndexField::INT, multi: true),
            new IndexField('pn', IndexField::STRING),
            new IndexField('pb', IndexField::INT, multi: true),
            new IndexField('pt', IndexField::TEXT, localized: true),
        ];
    }

    public function contribute(Collection $products, array $locales): array
    {
        $stored = $this->values->read(array_map('intval', $products->modelKeys()));
        $all = $this->properties->all();
        $bookIds = [];

        foreach ($stored as $values) {
            foreach ($values as $property => $value) {
                if (($all[$property] ?? null)?->isSelect()) {
                    $bookIds = [...$bookIds, ...(array) $value];
                }
            }
        }

        $book = $this->book(array_values(array_unique($bookIds)));
        $intervals = PropertyInterval::query()->whereIn('property_id', array_keys($all))->orderBy('position')->get()->groupBy('property_id');
        $documents = [];

        foreach ($products as $product) {
            $id = (int) $product->id;
            $document = ['pv' => [], 'pi' => [], 'pn' => [], 'pb' => []];
            $words = array_fill_keys($locales, []);

            foreach ($this->sets->effective($product->category_id) as $propertyId) {
                $property = $all[$propertyId] ?? null;
                $value = $stored[$id][$propertyId] ?? null;

                if ($property === null || $value === null) {
                    continue;
                }

                match ($property->type) {
                    Property::SELECT => $this->select($property, (array) $value, $book, $document, $words),
                    Property::NUMBER => $this->number($property, (float) $value, $intervals->get($propertyId), $document),
                    Property::BOOL => $document['pb'][] = $propertyId,
                    default => $this->text($property, (array) $value, $words),
                };
            }

            $document['pv'] = array_values(array_unique($document['pv']));
            $document['pn'] = $document['pn'] === [] ? null : json_encode($document['pn']);

            foreach ($words as $locale => $list) {
                $document['pt_'.$locale] = trim(implode(' ', array_unique($list)));
            }

            $documents[$id] = $document;
        }

        return $documents;
    }

    /**
     * @param  list<mixed>  $ids
     * @param  array<int, PropertyValue>  $book
     * @param  array<string, mixed>  $document
     * @param  array<string, list<string>>  $words
     */
    private function select(Property $property, array $ids, array $book, array &$document, array &$words): void
    {
        foreach ($ids as $valueId) {
            $value = $book[(int) $valueId] ?? null;

            if ($value === null) {
                continue;
            }

            // The value with every ancestor: choosing «Metal» finds the «Steel» (§8.3 of the architecture).
            foreach ($book as $above) {
                if ($above->property_id === $value->property_id && $above->lft <= $value->lft && $above->rgt >= $value->rgt) {
                    $document['pv'][] = (int) $above->id;
                }
            }

            if ($property->is_searchable) {
                foreach (array_keys($words) as $locale) {
                    $words[$locale][] = $value->displayName($locale);
                }
            }
        }
    }

    /**
     * @param  Rows<int, PropertyInterval>|null  $intervals
     * @param  array<string, mixed>  $document
     */
    private function number(Property $property, float $number, ?Rows $intervals, array &$document): void
    {
        $document['pn'][(string) $property->id] = $number;

        foreach ($intervals ?? [] as $interval) {
            if ($interval->contains($number)) {
                $document['pi'][] = (int) $interval->id;
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $text
     * @param  array<string, list<string>>  $words
     */
    private function text(Property $property, array $text, array &$words): void
    {
        if (! $property->is_searchable) {
            return;
        }

        foreach (array_keys($words) as $locale) {
            if (isset($text[$locale]) && is_string($text[$locale])) {
                $words[$locale][] = $text[$locale];
            }
        }
    }

    /**
     * The values asked for and all their ancestors, in one query.
     *
     * @param  list<mixed>  $ids
     * @return array<int, PropertyValue>
     */
    private function book(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $found = PropertyValue::query()
            ->whereExists(static function (QueryBuilder $below) use ($ids): void {
                $below->selectRaw('1')
                    ->from('catalog_property_values as below')
                    ->whereIn('below.id', array_map('intval', $ids))
                    ->whereColumn('below.property_id', 'catalog_property_values.property_id')
                    ->whereColumn('below.lft', '>=', 'catalog_property_values.lft')
                    ->whereColumn('below.rgt', '<=', 'catalog_property_values.rgt');
            })
            ->get();

        return $found->keyBy('id')->all();
    }
}
