<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Panel;

use Illuminate\Database\Eloquent\Collection;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Panel\ColumnSource;
use WebxUi\Catalog\Panel\ProductColumn;
use WebxUi\CatalogProperties\Catalog\ProductValues;
use WebxUi\CatalogProperties\Catalog\Properties;
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyValue;

/**
 * The properties marked «column in the list» as columns of the panel's list of products (§7.2 of
 * the properties spec): the value as words in the panel's language, units and all — `Black, Grey`,
 * `⌀12 mm`, `Yes`. A value outside the set of the product's main category is not shown, as it is
 * not shown anywhere else (decision 6).
 *
 * Every column of a page is read by one pass: the first column asked reads the values of all of
 * them, and the others take theirs from it. The pass is kept by the page's collection — the one
 * object every column is handed — so a long-lived worker does not show one request's values in
 * the next.
 */
final class PropertyColumns implements ColumnSource
{
    /** @var \WeakMap<Collection<int, Product>, array<int, array<int, string>>> */
    private \WeakMap $pages;

    public function __construct(
        private readonly Properties $properties,
        private readonly PropertySets $sets,
        private readonly ProductValues $values,
    ) {
        $this->pages = new \WeakMap;
    }

    public function columns(): array
    {
        $columns = [];

        foreach ($this->properties->all() as $property) {
            if ($property->in_list) {
                $columns[] = $this->column($property);
            }
        }

        return $columns;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, string> product id → the words of one property
     */
    public function cellsOf(Property $property, Collection $products): array
    {
        $this->pages[$products] ??= $this->read($products);
        $cells = [];

        foreach ($this->pages[$products] as $product => $row) {
            if (isset($row[(int) $property->id])) {
                $cells[$product] = $row[(int) $property->id];
            }
        }

        return $cells;
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array<int, array<int, string>> product id → property id → words
     */
    private function read(Collection $products): array
    {
        $listed = array_filter($this->properties->all(), static fn (Property $property): bool => (bool) $property->in_list);
        $stored = $this->values->read(array_map('intval', $products->modelKeys()));
        $locale = app()->getLocale();

        $valueIds = [];

        foreach ($stored as $held) {
            foreach ($held as $property => $value) {
                if (isset($listed[$property]) && $listed[$property]->isSelect()) {
                    array_push($valueIds, ...array_map('intval', (array) $value));
                }
            }
        }

        $titles = $valueIds === [] ? [] : PropertyValue::query()
            ->whereKey(array_unique($valueIds))
            ->get()
            ->mapWithKeys(static fn (PropertyValue $value): array => [(int) $value->id => $value->displayName($locale)])
            ->all();

        $cells = [];

        foreach ($products as $product) {
            $id = (int) $product->id;
            $set = array_flip($this->sets->effective($product->category_id));

            foreach ($stored[$id] ?? [] as $property => $value) {
                if (! isset($listed[$property], $set[$property])) {
                    continue;
                }

                $cells[$id][$property] = $this->words($listed[$property], $value, $titles, $locale);
            }
        }

        return $cells;
    }

    /**
     * @param  array<int, string>  $titles
     */
    private function words(Property $property, mixed $value, array $titles, string $locale): string
    {
        return match ($property->type) {
            Property::SELECT => implode(', ', array_map(
                static fn (int $id): string => $titles[$id] ?? '#'.$id,
                array_map('intval', (array) $value),
            )),
            Property::NUMBER => $property->formatNumber((float) $value, $locale),
            Property::BOOL => (string) __('webx-catalog-properties::product.yes'),
            default => $this->text((array) $value, $locale),
        };
    }

    /**
     * @param  array<array-key, mixed>  $text
     */
    private function text(array $text, string $locale): string
    {
        $words = $text[$locale] ?? null;

        if (! is_string($words) || $words === '') {
            $words = (string) (array_values(array_filter($text, static fn ($one): bool => is_string($one) && $one !== ''))[0] ?? '');
        }

        return $words;
    }

    private function column(Property $property): ProductColumn
    {
        $source = $this;

        return new class($property, $source) implements ProductColumn
        {
            public function __construct(
                private readonly Property $property,
                private readonly PropertyColumns $source,
            ) {}

            public function key(): string
            {
                return $this->property->facetKey();
            }

            public function label(): string
            {
                return $this->property->displayName();
            }

            public function values(Collection $products): array
            {
                return $this->source->cellsOf($this->property, $products);
            }

            public function sort(): ?string
            {
                return null;
            }
        };
    }
}
