<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Exchange;

use Illuminate\Support\Collection;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogProperties\Catalog\ProductValues;
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\CatalogProperties\Models\Property;

/**
 * What every property column of one export reads about a page of products, read once for all of
 * them: the values held, and which properties are in the set of each main category. Fifty property
 * columns are one query a page, not fifty.
 *
 * A value outside the set (decision 6 of the properties spec) is not written: the form would refuse
 * it on the way back, and a file sent back untouched must change nothing.
 */
final class ExportPage
{
    /** @var list<int> */
    private array $ids = [];

    /** @var array<int, array<int, mixed>> */
    private array $values = [];

    /** @var array<int, array<int, true>> category id → properties of its set */
    private array $sets = [];

    /** @var array<int, ValuePaths> */
    private array $paths = [];

    public function __construct(
        private readonly ProductValues $stored,
        private readonly PropertySets $propertySets,
        private readonly string $locale,
    ) {}

    /**
     * Whether the property is in the set of the product's main category, and what it holds there.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, array{0: bool, 1: mixed}> product id → [in the set, value]
     */
    public function of(Collection $products, Property $property): array
    {
        $ids = $products->map(static fn (Product $product): int => (int) $product->id)->all();

        if ($ids !== $this->ids) {
            $this->ids = $ids;
            $this->values = $this->stored->read($ids);
        }

        $of = [];

        foreach ($products as $product) {
            /** @var Product $product */
            $category = (int) $product->category_id;
            $this->sets[$category] ??= array_fill_keys($this->propertySets->effective($category === 0 ? null : $category), true);
            $of[(int) $product->id] = [isset($this->sets[$category][(int) $property->id]), $this->values[(int) $product->id][(int) $property->id] ?? null];
        }

        return $of;
    }

    public function paths(Property $property): ValuePaths
    {
        return $this->paths[(int) $property->id] ??= new ValuePaths($property, $this->locale);
    }
}
