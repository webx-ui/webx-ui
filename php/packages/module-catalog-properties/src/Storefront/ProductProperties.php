<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Storefront;

use Illuminate\Support\Facades\DB;
use WebxUi\Catalog\Facets\CategoryFacets;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Filter\FilterContext;
use WebxUi\Catalog\Filter\FilterState;
use WebxUi\Catalog\Filter\FilterUrls;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogProperties\Catalog\ProductValues;
use WebxUi\CatalogProperties\Catalog\Properties;
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyGroup;
use WebxUi\CatalogProperties\Models\PropertyValue;
use WebxUi\Localization\Locales;

/**
 * The properties of products as the storefront shows them (§8.1, §8.3 of the properties spec):
 * the card's short list, the page's table, `$product->properties()`.
 *
 * Only the set of the product's main category, in the set's order (decision 5); a value of any
 * other property is kept and shown nowhere (decision 6). A page of products is read at once —
 * {@see load()} is one query for the values with the words of the reference books joined in, and
 * one for the groups — so a grid of twenty-four cards is not twenty-four queries. A product not
 * loaded is read on its own the first time it is asked about.
 *
 * The addresses of first levels are the costly part — a filter's context per category — so they
 * are made only where they are printed: the table and `properties()`, once per product.
 */
final class ProductProperties
{
    /** @var array<int, array<int, list<object>>> product → property → stored rows */
    private array $rows = [];

    /** @var array<int, list<ShownProperty>> product → what {@see of()} gave, with addresses */
    private array $shown = [];

    /** @var array<int, PropertyGroup>|null */
    private ?array $groups = null;

    public function __construct(
        private readonly Properties $properties,
        private readonly PropertySets $sets,
        private readonly CategoryFacets $categoryFacets,
        private readonly FilterUrls $urls,
        private readonly Locales $locales,
    ) {}

    /**
     * Read a page of products at once, forgetting the last page's: a worker serving the next
     * request from the same process must not print this one's values.
     *
     * @param  iterable<Product>  $products
     */
    public function load(iterable $products): void
    {
        $ids = [];

        foreach ($products as $product) {
            $ids[] = (int) $product->id;
        }

        $this->reset();
        $this->fetch($ids);
    }

    public function reset(): void
    {
        $this->rows = [];
        $this->shown = [];
        $this->groups = null;
    }

    /**
     * Every property of the set that the product has a value of, in the set's order, with the
     * addresses of the first levels.
     *
     * @return list<ShownProperty>
     */
    public function of(Product $product): array
    {
        return $this->shown[(int) $product->id] ??= $this->build($product, true);
    }

    /**
     * The card's short list: the properties marked «in the card». No addresses — a card links to
     * its product, not to a filter.
     *
     * @return list<ShownProperty>
     */
    public function card(Product $product): array
    {
        $shown = $this->shown[(int) $product->id] ?? $this->build($product, false);

        return array_values(array_filter($shown, static fn (ShownProperty $one): bool => (bool) $one->property->in_card));
    }

    /**
     * The table of the page: the properties marked «on the page», by group — the groups in the
     * order their first property stands in the set, then the properties with no group, or with a
     * hidden one, as a last block without a heading.
     *
     * @return list<array{group: array{id: int, title: string}|null, properties: list<ShownProperty>}>
     */
    public function table(Product $product): array
    {
        $blocks = [];
        $loose = [];

        foreach ($this->of($product) as $shown) {
            if (! $shown->property->on_page) {
                continue;
            }

            if ($shown->group === null) {
                $loose[] = $shown;

                continue;
            }

            $blocks[$shown->group['id']] ??= ['group' => $shown->group, 'properties' => []];
            $blocks[$shown->group['id']]['properties'][] = $shown;
        }

        return [...array_values($blocks), ...($loose === [] ? [] : [['group' => null, 'properties' => $loose]])];
    }

    /**
     * @return list<ShownProperty>
     */
    private function build(Product $product, bool $withUrls): array
    {
        $id = (int) $product->id;
        $this->fetch([$id]);

        $locale = $this->locales->current();
        $all = $this->properties->all();
        $groups = $this->groups();
        $pending = [];

        foreach ($this->sets->effective($product->category_id === null ? null : (int) $product->category_id) as $propertyId) {
            $property = $all[$propertyId] ?? null;
            $rows = $this->rows[$id][$propertyId] ?? [];

            if ($property === null || $rows === []) {
                continue;
            }

            $group = $property->group_id === null ? null : ($groups[(int) $property->group_id] ?? null);
            $pending[$propertyId] = [
                'property' => $property,
                'group' => $group === null ? null : ['id' => (int) $group->id, 'title' => $this->title($group, $locale)],
                ...$this->shape($property, $rows, $locale),
            ];
        }

        $urls = $withUrls ? $this->firstLevels($product, $pending, $locale) : [];
        $shown = [];

        foreach ($pending as $propertyId => $one) {
            $values = array_map(static fn (array $value): array => [...$value, 'url' => $urls[$propertyId][$value['id']] ?? null], $one['values']);

            $shown[] = new ShownProperty(
                property: $one['property'],
                code: $one['property']->codeIn($locale),
                label: $one['property']->displayName($locale),
                value: $one['value'],
                formatted: $one['formatted'],
                group: $one['group'],
                values: $values,
                url: count($values) === 1 ? $values[0]['url'] : null,
            );
        }

        return $shown;
    }

    /**
     * The stored rows as the type's value and its words.
     *
     * @param  list<object>  $rows
     * @return array{value: mixed, formatted: string, values: list<array{id: int, label: string, color: string|null}>}
     */
    private function shape(Property $property, array $rows, string $locale): array
    {
        if ($property->isSelect()) {
            $values = [];

            foreach ($rows as $row) {
                if ($row->value_id === null || $row->book_title === null) {
                    continue;
                }

                $book = (new PropertyValue)->newFromBuilder(['id' => $row->value_id, 'title' => $row->book_title, 'slug' => $row->book_slug]);
                $values[] = [
                    'id' => (int) $row->value_id,
                    'label' => $book->displayName($locale),
                    'color' => $property->has_color && is_string($row->book_color) && $row->book_color !== '' ? $row->book_color : null,
                ];
            }

            $ids = array_map(static fn (array $value): int => $value['id'], $values);

            return [
                'value' => $property->is_multiple ? $ids : ($ids[0] ?? null),
                'formatted' => implode(', ', array_map(static fn (array $value): string => $value['label'], $values)),
                'values' => $values,
            ];
        }

        $row = $rows[0];
        $text = $property->isText() ? (array) json_decode((string) $row->text, true) : [];

        return match ($property->type) {
            Property::NUMBER => ['value' => (float) $row->number, 'formatted' => $property->formatNumber((float) $row->number, $locale), 'values' => []],
            Property::BOOL => ['value' => true, 'formatted' => (string) __('webx-catalog-properties::product.yes', [], $locale), 'values' => []],
            default => ['value' => $text, 'formatted' => $this->textIn($text, $locale), 'values' => []],
        };
    }

    /**
     * The addresses of the product's reference-book values whose first level is an open page of
     * its main category: one filter context, one call for all of them.
     *
     * @param  array<int, array{property: Property, values: list<array{id: int}>}>  $pending
     * @return array<int, array<int, string>> property → value → address
     */
    private function firstLevels(Product $product, array $pending, string $locale): array
    {
        $category = $product->category;

        if (! $category instanceof Category || ! $category->hasUrlIn($locale)) {
            return [];
        }

        $context = null;
        $states = [];
        $where = [];

        foreach ($pending as $propertyId => $one) {
            if ($one['values'] === []) {
                continue;
            }

            $context ??= new FilterContext(
                FilterContext::CATEGORY,
                $category->routeCanonical($locale)->path ?? $category->routePath($locale),
                $locale,
                $this->categoryFacets->visible($category),
                $category,
            );

            $key = $one['property']->facetKey();

            if ($context->facet($key) === null) {
                continue;
            }

            foreach ($one['values'] as $value) {
                $state = FilterState::of([$key => FacetValue::of([(string) $value['id']])]);

                if ($this->urls->indexable($context, $state)) {
                    $states[] = $state;
                    $where[] = [$propertyId, $value['id']];
                }
            }
        }

        if ($context === null || $states === []) {
            return [];
        }

        $urls = [];

        foreach ($this->urls->urls($context, $states) as $i => $url) {
            $urls[$where[$i][0]][$where[$i][1]] = $url;
        }

        return $urls;
    }

    /**
     * @param  list<int>  $ids
     */
    private function fetch(array $ids): void
    {
        $missing = array_values(array_diff($ids, array_keys($this->rows)));

        if ($missing === []) {
            return;
        }

        foreach ($missing as $id) {
            $this->rows[$id] = [];
        }

        $rows = DB::table(ProductValues::TABLE.' as stored')
            ->leftJoin('catalog_property_values as book', 'book.id', '=', 'stored.value_id')
            ->whereIn('stored.product_id', $missing)
            ->orderBy('stored.product_id')
            ->orderBy('book.lft')
            ->orderBy('stored.id')
            ->get([
                'stored.product_id', 'stored.property_id', 'stored.value_id', 'stored.number', 'stored.flag', 'stored.text',
                'book.title as book_title', 'book.slug as book_slug', 'book.color as book_color',
            ]);

        foreach ($rows as $row) {
            $this->rows[(int) $row->product_id][(int) $row->property_id][] = $row;
        }
    }

    /**
     * The visible groups, once per page: a hidden group hides nothing, it only loses its heading.
     *
     * @return array<int, PropertyGroup>
     */
    private function groups(): array
    {
        return $this->groups ??= PropertyGroup::query()->where('is_visible', true)->get()->keyBy('id')->all();
    }

    private function title(PropertyGroup $group, string $locale): string
    {
        $title = $group->getTranslation('title', $locale);

        return is_string($title) && trim($title) !== '' ? $title : '#'.$group->id;
    }

    /**
     * @param  array<array-key, mixed>  $text
     */
    private function textIn(array $text, string $locale): string
    {
        foreach ([$locale, ...$this->locales->chain($locale), ...array_keys($text)] as $one) {
            if (isset($text[$one]) && is_string($text[$one]) && $text[$one] !== '') {
                return $text[$one];
            }
        }

        return '';
    }
}
