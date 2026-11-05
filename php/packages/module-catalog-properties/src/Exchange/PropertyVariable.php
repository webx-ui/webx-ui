<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Exchange;

use Illuminate\Support\Collection;
use WebxUi\Catalog\Variables\FirstValues;
use WebxUi\Catalog\Variables\ProductVariable;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\Templates\Variable;

/**
 * One property as a template reads it: what a buyer would, in the language of the feed — names
 * through `;`, a number without its unit, `1` or nothing for yes or no. A value outside the set of
 * the product's category is no value, as in a file.
 */
final readonly class PropertyVariable implements FirstValues, ProductVariable
{
    public function __construct(
        private Property $property,
        private ExportPage $page,
        private string $locale,
    ) {}

    public function describe(): Variable
    {
        return new Variable('property.'.PropertyExchangeColumn::keyOf($this->property, $this->locale), $this->property->displayName($this->locale));
    }

    public function values(Collection $products, string $locale): array
    {
        $values = [];

        foreach ($this->page->of($products, $this->property) as $id => [$inSet, $value]) {
            $values[$id] = $inSet ? $this->text($value, $locale) : '';
        }

        return $values;
    }

    /** A name of a select may have a `;` in it, so the first one is taken before the names are joined. */
    public function firstValues(Collection $products, string $locale): array
    {
        $values = [];

        foreach ($this->page->of($products, $this->property) as $id => [$inSet, $value]) {
            $first = $this->property->type === Property::SELECT && is_array($value) ? array_slice($value, 0, 1) : $value;
            $values[$id] = $inSet ? $this->text($first, $locale) : '';
        }

        return $values;
    }

    private function text(mixed $value, string $locale): string
    {
        return match ($this->property->type) {
            Property::SELECT => implode(';', array_filter(array_map(
                fn (mixed $id): string => $this->page->names($locale)[(int) $id] ?? '',
                $value === null ? [] : (array) $value,
            ), static fn (string $name): bool => $name !== '')),
            Property::NUMBER => is_numeric($value) ? PropertyExchangeColumn::number((float) $value) : '',
            Property::BOOL => $value === true ? '1' : '',
            default => is_array($value) && is_string($value[$locale] ?? null) ? $value[$locale] : '',
        };
    }
}
