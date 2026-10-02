<?php

declare(strict_types=1);

namespace WebxUi\CatalogProperties\Demo;

use Illuminate\Support\Facades\DB;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\CatalogProperties\Catalog\ProductValues;
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyGroup;
use WebxUi\CatalogProperties\Models\PropertyInterval;
use WebxUi\CatalogProperties\Models\PropertyValue;
use WebxUi\Localization\Locales;

/**
 * The properties of the demo shop (§12): one of every kind the card and the filter draw — a
 * multiple colour with swatches, a material as a tree, a weight in intervals, a diagonal on a
 * slider, a yes/no, a text — in two groups and one without.
 *
 * The sets sit on two branches of the core demo's tree, one at the top of «Clothing», so the
 * inheritance is seen; «Textiles» has none, and a few towels hold a colour anyway — a value outside
 * the set, which the card and the filter must not show. The products are the core demo's, counted
 * by their order.
 *
 * Only the groups and the properties go into the journal: values, intervals, sets and the values on
 * the products are rows of the property, and its deletion takes them.
 */
final class PropertiesDemo
{
    /** code => [type, title, group, attributes] */
    private const PROPERTIES = [
        'color' => ['select', 'Colour', 'main', [
            'is_multiple' => true, 'value_order' => 'manual', 'has_color' => true, 'is_filterable' => true,
            'is_indexable' => true, 'in_card' => true, 'in_list' => true,
        ]],
        'material' => ['select', 'Material', 'main', [
            'is_tree' => true, 'is_filterable' => true, 'is_indexable' => true,
        ]],
        'weight' => ['number', 'Weight', 'dimensions', [
            'precision' => 2, 'is_filterable' => true, 'is_indexable' => true, 'filter_mode' => 'intervals',
        ]],
        'diagonal' => ['number', 'Diagonal', 'dimensions', [
            'precision' => 1, 'is_filterable' => true, 'filter_mode' => 'slider', 'in_list' => true,
        ]],
        'wi-fi' => ['bool', 'Wi-Fi', 'main', ['is_filterable' => true]],
        // No group: the card shows it last, without a heading.
        'contents' => ['text', 'Contents', null, []],
    ];

    /** The words of a language that are not a title: code => [attribute => text]. */
    private const WORDS = [
        'weight' => ['unit_suffix' => ' kg'],
        'diagonal' => ['unit_suffix' => '″'],
        'wi-fi' => ['toggle_slug' => 'yes'],
    ];

    private const GROUPS = ['main' => 'Main', 'dimensions' => 'Dimensions'];

    /** code => [[title, colour, parent title], …] */
    private const VALUES = [
        'color' => [
            ['Black', '#1f2328', null],
            ['White', '#ffffff', null],
            ['Grey', '#8c959f', null],
            ['Red', '#cf222e', null],
            ['Blue', '#0969da', null],
        ],
        'material' => [
            ['Metal', null, null],
            ['Steel', null, 'Metal'],
            ['Stainless', null, 'Steel'],
            ['Aluminium', null, 'Metal'],
            ['Plastic', null, null],
            ['Wood', null, null],
        ],
    ];

    /** [title, slug, min, max] */
    private const INTERVALS = [
        ['up to 1 kg', 'up-to-1-kg', null, 1],
        ['1–3 kg', '1-3-kg', 1, 3],
        ['from 3 kg', 'from-3-kg', 3, null],
    ];

    /** The core demo's category, by its name => the properties it adds. */
    private const SETS = [
        'Clothing' => ['color'],
        'Shoes' => ['color', 'weight'],
        'Kitchen' => ['material', 'weight', 'wi-fi', 'contents'],
        'Cookware' => ['diagonal'],
    ];

    public function __construct(
        private readonly Catalog $catalog,
        private readonly PropertySets $sets,
        private readonly Locales $locales,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        $ids = $ledger->idsOf('catalog', Product::class);

        if ($ids === []) {
            $ledger->note('The catalogue demo is not seeded; the properties have no products to describe.');

            return;
        }

        if (Property::withTrashed()->exists() || PropertyGroup::withTrashed()->exists()) {
            $ledger->note('The site already has properties; the demo left them alone.');

            return;
        }

        $locale = $this->locales->defaultCode();
        $groups = [];
        $properties = [];
        $position = 0;

        foreach (self::GROUPS as $key => $title) {
            $group = new PropertyGroup;
            $group->setTranslation('title', $locale, $title);
            $group->forceFill(['is_visible' => true, 'position' => ++$position])->save();
            $ledger->created($group, 'Property group '.$title);
            $groups[$key] = (int) $group->getKey();
        }

        foreach (self::PROPERTIES as $code => [$type, $title, $group, $attributes]) {
            $property = new Property;
            $property->setTranslation('title', $locale, $title);
            $property->setTranslation('code', $locale, $code);
            $property->fill([
                'type' => $type,
                'group_id' => $group === null ? null : $groups[$group],
                ...$attributes,
                ...array_map(static fn (string $word): array => [$locale => $word], self::WORDS[$code] ?? []),
            ]);
            $property->save();
            $ledger->created($property, 'Property '.$title);
            $properties[$code] = $property;
        }

        $values = [];

        foreach (self::VALUES as $code => $rows) {
            foreach ($rows as [$title, $color, $parent]) {
                $value = new PropertyValue(['property_id' => $properties[$code]->id, 'color' => $color]);
                $value->setTranslation('title', $locale, $title);
                $parent === null
                    ? $value->saveAsRoot()
                    : $value->appendTo(PropertyValue::query()->findOrFail($values[$code][$parent]));
                $values[$code][$title] = (int) $value->getKey();
            }
        }

        foreach (self::INTERVALS as $n => [$title, $slug, $min, $max]) {
            PropertyInterval::query()->create([
                'property_id' => $properties['weight']->id,
                'title' => [$locale => $title],
                'slug' => [$locale => $slug],
                'min' => $min,
                'max' => $max,
                'position' => $n,
            ]);
        }

        $categories = Category::query()->whereKey($ledger->idsOf('catalog', Category::class))->get()
            ->keyBy(static fn (Category $category): string => (string) $category->getTranslation('name', $locale, false));

        foreach (self::SETS as $name => $codes) {
            if ($categories->has($name)) {
                $this->sets->save($categories[$name], array_map(static fn (string $code): int => $properties[$code]->id, $codes));
            }
        }

        $this->products($ids, $categories->all(), $properties, $values, $locale);
        $this->catalog->touchQuery(Product::withTrashed()->whereKey($ids));
    }

    /**
     * @param  list<int|string>  $ids
     * @param  array<string, Category>  $categories  By name.
     * @param  array<string, Property>  $properties
     * @param  array<string, array<string, int>>  $values
     */
    private function products(array $ids, array $categories, array $properties, array $values, string $locale): void
    {
        $names = [];

        foreach ($categories as $name => $category) {
            $names[(int) $category->getKey()] = $name;
        }

        $colours = array_values($values['color']);
        $material = $values['material'];
        $rows = [];
        $row = static fn (int $product, Property $property, array $value): array => [
            'product_id' => $product, 'property_id' => $property->id,
            'value_id' => null, 'number' => null, 'flag' => null, 'text' => null, ...$value,
        ];

        foreach (Product::withTrashed()->whereKey($ids)->orderBy('id')->get(['id', 'category_id'])->values() as $n => $product) {
            $id = (int) $product->getKey();
            $category = $product->category_id === null ? null : Category::query()->find($product->category_id);
            // The names of the category and of everything above it.
            $branch = $category === null ? [] : Category::query()
                ->where('lft', '<=', $category->lft)->where('rgt', '>=', $category->rgt)->pluck('id')
                ->map(static fn (int|string $key): ?string => $names[(int) $key] ?? null)->all();
            $in = static fn (string $name): bool => in_array($name, $branch, true);

            if ($in('Clothing') || $in('Shoes')) {
                // One colour, or two on every third.
                $rows[] = $row($id, $properties['color'], ['value_id' => $colours[$n % 5]]);

                if ($n % 3 === 0) {
                    $rows[] = $row($id, $properties['color'], ['value_id' => $colours[($n + 2) % 5]]);
                }
            }

            if ($in('Shoes')) {
                $rows[] = $row($id, $properties['weight'], ['number' => 0.4 + ($n % 13) / 10]);
            }

            if ($in('Kitchen')) {
                $knife = $in('Knives');
                $rows[] = $row($id, $properties['material'], ['value_id' => $knife
                    ? [$material['Stainless'], $material['Steel'], $material['Wood']][$n % 3]
                    : [$material['Stainless'], $material['Aluminium'], $material['Steel']][$n % 3]]);
                $rows[] = $row($id, $properties['weight'], ['number' => $knife ? 0.1 + ($n % 4) / 10 : 0.5 + ($n % 8) / 2]);

                if ($n % 5 === 0) {
                    $rows[] = $row($id, $properties['wi-fi'], ['flag' => true]);
                }

                if ($n % 2 === 0) {
                    $rows[] = $row($id, $properties['contents'], ['text' => json_encode([$locale => 'The item, a manual, a box'])]);
                }
            }

            if ($in('Cookware')) {
                $rows[] = $row($id, $properties['diagonal'], ['number' => 16 + ($n % 7) * 2]);
            }

            // Outside the set: «Textiles» adds nothing, and a few towels hold a colour anyway.
            if ($in('Textiles') && $n % 8 < 2) {
                $rows[] = $row($id, $properties['color'], ['value_id' => $colours[$n % 5]]);
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table(ProductValues::TABLE)->insert($chunk);
        }
    }
}
