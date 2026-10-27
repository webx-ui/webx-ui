<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests\Fixtures;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use WebxUi\Admin\Testing\SchemaSnapshots;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogBrands\Models\Brand;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogLabels\Models\Label;
use WebxUi\CatalogLandings\CatalogLandingsServiceProvider;
use WebxUi\CatalogLandings\Models\Landing;
use WebxUi\CatalogProperties\Catalog\ProductValues;
use WebxUi\CatalogProperties\Catalog\PropertySets;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogProperties\Models\PropertyInterval;
use WebxUi\CatalogProperties\Models\PropertyValue;
use WebxUi\CatalogStock\Models\StockStatus;
use WebxUi\CatalogStock\StockServiceProvider;

/**
 * A shop the size of the demo's, for the query budgets of the storefront: fifteen categories
 * three levels deep, a hundred and forty products with a picture, a price and, every fifth, a
 * second shelf — and whatever satellite the test has booted filled in the way its demo fills it:
 * brands, labels, stock, properties with their sets and values, a few landings.
 *
 * Made up in code like the demo: a noun of the shelf and an adjective, a formula for the price.
 * The satellites are seeded only when their provider is loaded, so the same fixture serves the
 * core's test and each satellite's.
 */
final class Shop
{
    public const PRODUCTS = 140;

    /** Parent → children; the leaves hold the products. Fifteen categories. */
    private const TREE = [
        'Clothing' => ['Men' => ['Shirts', 'Trousers'], 'Women' => ['Dresses', 'Skirts']],
        'Shoes' => ['Sneakers' => [], 'Boots' => []],
        'Home' => ['Kitchen' => ['Cookware', 'Knives'], 'Textiles' => []],
    ];

    private const NOUNS = [
        'Shirts' => 'shirt', 'Trousers' => 'trousers', 'Dresses' => 'dress', 'Skirts' => 'skirt',
        'Sneakers' => 'sneakers', 'Boots' => 'boots', 'Cookware' => 'pan', 'Knives' => 'knife',
        'Textiles' => 'towel',
    ];

    private const ADJECTIVES = [
        'Classic', 'Linen', 'Everyday', 'Travel', 'Summer', 'Winter', 'Studio', 'Harbour',
        'Field', 'City', 'Coastal', 'Mountain', 'Heritage', 'Soft', 'Light', 'Sturdy', 'Weekend',
    ];

    private const BRANDS = ['northwind', 'contoso', 'fabrikam', 'tailspin', 'litware', 'adatum', 'proseware', 'woodgrove'];

    /** code => [title, tone, badge, in the filter] */
    private const LABELS = [
        'top' => ['Top', 'primary', true, true],
        'sale' => ['Sale', 'danger', true, true],
        'new' => ['New', 'success', true, true],
        'newsletter' => ['Newsletter', 'neutral', false, false],
    ];

    /** code => [type, title, attributes] */
    private const PROPERTIES = [
        'color' => ['select', 'Colour', [
            'is_multiple' => true, 'value_order' => 'manual', 'has_color' => true, 'is_filterable' => true,
            'is_indexable' => true, 'in_card' => true, 'in_list' => true,
        ]],
        'material' => ['select', 'Material', ['is_tree' => true, 'is_filterable' => true, 'is_indexable' => true]],
        'weight' => ['number', 'Weight', ['precision' => 2, 'is_filterable' => true, 'is_indexable' => true, 'filter_mode' => 'intervals']],
        'diagonal' => ['number', 'Diagonal', ['precision' => 1, 'is_filterable' => true, 'filter_mode' => 'slider', 'in_list' => true]],
        'wi-fi' => ['bool', 'Wi-Fi', ['is_filterable' => true]],
        'contents' => ['text', 'Contents', []],
    ];

    /** code => [[title, colour, parent title], …] */
    private const VALUES = [
        'color' => [['Black', '#1f2328', null], ['White', '#ffffff', null], ['Grey', '#8c959f', null], ['Red', '#cf222e', null], ['Blue', '#0969da', null]],
        'material' => [['Metal', null, null], ['Steel', null, 'Metal'], ['Stainless', null, 'Steel'], ['Aluminium', null, 'Metal'], ['Plastic', null, null], ['Wood', null, null]],
    ];

    private const SETS = [
        'Clothing' => ['color'],
        'Shoes' => ['color', 'weight'],
        'Kitchen' => ['material', 'weight', 'wi-fi', 'contents'],
        'Cookware' => ['diagonal'],
    ];

    /** @var array<string, Category> by name */
    public array $categories = [];

    /** @var list<Product> in order of creation */
    public array $products = [];

    /** @var array<string, Brand> by slug */
    public array $brands = [];

    /** @var array<string, Property> by code */
    public array $properties = [];

    /** @var array<string, array<string, PropertyValue>> code → title → value */
    public array $values = [];

    /** @var array<string, Landing> by slug */
    public array $landings = [];

    public function __construct(private readonly Application $app) {}

    public static function seed(Application $app): self
    {
        $shop = new self($app);
        $shop->categoriesAndProducts();

        if ($app->providerIsLoaded(BrandsServiceProvider::class)) {
            $shop->brands();
        }

        if ($app->providerIsLoaded(LabelsServiceProvider::class)) {
            $shop->labels();
        }

        if ($app->providerIsLoaded(StockServiceProvider::class)) {
            $shop->stock();
        }

        if ($app->providerIsLoaded(CatalogPropertiesServiceProvider::class)) {
            $shop->properties();
        }

        // The satellites seeded a moment ago are the facets' sources, and the registry is kept
        // for a request: it began before them.
        $app->make(Facets::class)->flush();

        if ($app->providerIsLoaded(CatalogLandingsServiceProvider::class)) {
            $shop->landings();
        }

        return $shop;
    }

    /**
     * The rows of the first seed of this process and the keys of what it made, per key — for
     * `seedOnce()`.
     *
     * @var array<string, array{rows: array<string, list<array<string, mixed>>>, keys: array<string, mixed>}>
     */
    private static array $seeded = [];

    /**
     * The same shop as `seed()`, seeded for real once per `$key` (a test class, whose providers and
     * schema are its own) and copied back into every next test's fresh database: each test gets its
     * own rows and its own models, in 5 ms rather than half a second.
     */
    public static function seedOnce(Application $app, string $key): self
    {
        $pdo = DB::connection()->getPdo();

        if (! isset(self::$seeded[$key])) {
            $shop = self::seed($app);
            self::$seeded[$key] = ['rows' => SchemaSnapshots::take($pdo)['rows'], 'keys' => $shop->keys()];

            return $shop;
        }

        SchemaSnapshots::refill($pdo, self::$seeded[$key]['rows']);

        // As after a seed: the registry of facets began before the satellites' rows were there.
        $app->make(Facets::class)->flush();

        return (new self($app))->reload(self::$seeded[$key]['keys']);
    }

    public function category(string $name): Category
    {
        return $this->categories[$name];
    }

    /** A product of a leaf, by the leaf's name: the first, or the n-th. */
    public function productOf(string $leaf, int $skip = 0): Product
    {
        foreach ($this->products as $product) {
            if ($product->category_id === $this->categories[$leaf]->getKey() && $skip-- === 0) {
                return $product;
            }
        }

        throw new \LogicException("No product on the shelf [{$leaf}].");
    }

    private function categoriesAndProducts(): void
    {
        $leaves = [];

        foreach (self::TREE as $top => $children) {
            $parent = $this->makeCategory($top, null);

            foreach ($children as $middle => $grandchildren) {
                $child = $this->makeCategory($middle, $parent);

                if ($grandchildren === []) {
                    $leaves[$middle] = $child;

                    continue;
                }

                foreach ($grandchildren as $leaf) {
                    $leaves[$leaf] = $this->makeCategory($leaf, $child);
                }
            }
        }

        $names = array_keys($leaves);

        for ($i = 0; $i < self::PRODUCTS; $i++) {
            $shelf = $names[$i % count($names)];
            $extra = $leaves[$names[($i + 3) % count($names)]];
            $name = self::ADJECTIVES[$i % count(self::ADJECTIVES)].' '.self::NOUNS[$shelf].' '.(intdiv($i, 9) + 1);
            $price = 9 + (($i * 37) % 180) + 0.9;

            $product = Product::query()->create([
                'name' => $name,
                'summary' => 'A made-up product: no such thing is for sale.',
                'description' => '<p>'.$name.' is part of a made-up catalogue.</p>',
                'sku' => sprintf('TEST-%04d', $i + 1),
                'category_id' => $leaves[$shelf]->getKey(),
                'price' => $price,
                'old_price' => $i % 6 === 0 ? round($price * 1.25, 2) : null,
                'unit' => $shelf === 'Cookware' ? 'set' : 'pcs',
                'priority' => $i % 17 === 0 ? 10 : 0,
                'is_published' => true,
            ]);

            if ($i % 5 === 0 && $extra->getKey() !== $leaves[$shelf]->getKey()) {
                $product->categories()->attach($extra->getKey());
            }

            // Rows rather than uploads: the budget is about the queries, not the bytes.
            foreach (range(0, $i % 3 === 0 ? 1 : 0) as $position) {
                ProductImage::query()->create([
                    'product_id' => $product->getKey(),
                    'path' => sprintf('catalog/%03d-%d.jpg', $i, $position),
                    'alt' => $name,
                    'width' => 800,
                    'height' => 600,
                    'size' => 2048,
                    'position' => $position,
                ]);
            }

            $this->products[] = $product;
        }
    }

    private function makeCategory(string $name, ?Category $parent): Category
    {
        $category = new Category([
            'name' => $name,
            'slug' => strtolower($name),
            'description' => '<p>'.$name.' of a made-up shop.</p>',
            'is_published' => true,
        ]);

        $parent === null ? $category->saveAsRoot() : $category->appendTo($parent);

        return $this->categories[$name] = $category->refresh();
    }

    private function brands(): void
    {
        $position = 0;

        foreach (self::BRANDS as $slug) {
            $this->brands[$slug] = Brand::query()->create([
                'title' => ucfirst($slug),
                'slug' => $slug,
                'description' => '<p>'.ucfirst($slug).' is a made-up brand.</p>',
                'is_featured' => $position % 3 === 0,
                // One hidden, as in the demo: its products show no brand.
                'is_visible' => $slug !== 'proseware',
                'position' => ++$position,
            ])->refresh();
        }

        $ids = array_values(array_map(static fn (Brand $brand): int => (int) $brand->getKey(), $this->brands));
        $rows = [];

        foreach ($this->products as $n => $product) {
            if ($n % 7 !== 3) {
                $rows[] = ['product_id' => $product->getKey(), 'brand_id' => $ids[($n + intdiv($n, count($ids))) % count($ids)]];
            }
        }

        DB::table(Brand::LINKS)->insert($rows);
    }

    private function labels(): void
    {
        $labels = [];
        $position = 0;

        foreach (self::LABELS as $code => [$title, $tone, $badge, $filter]) {
            $labels[$code] = Label::query()->create([
                'title' => $title,
                'code' => $code,
                'color' => $tone,
                'is_badge' => $badge,
                'is_visible' => $filter,
                'position' => ++$position,
            ])->getKey();
        }

        $rows = [];

        foreach ($this->products as $n => $product) {
            $on = array_keys(array_filter([
                'top' => $n % 7 === 0,
                'sale' => $product->old_price !== null,
                'new' => $n % 9 === 1,
                'newsletter' => $n % 13 === 0,
            ]));

            foreach ($on as $code) {
                $rows[] = ['label_id' => $labels[$code], 'product_id' => $product->getKey()];
            }
        }

        DB::table(Label::LINKS)->insert($rows);
    }

    private function stock(): void
    {
        $statuses = StockStatus::query()->pluck('id', 'code')->all();
        $rows = [];

        foreach ($this->products as $n => $product) {
            $code = match (true) {
                $n % 10 === 3 => 'out-of-stock',
                $n % 10 === 7 => 'on-order',
                $n % 4 === 0 => 'in-stock',
                default => null,
            };

            if ($code !== null && isset($statuses[$code])) {
                $rows[] = ['product_id' => $product->getKey(), 'status_id' => $statuses[$code]];
            }
        }

        DB::table(StockStatus::LINKS)->insert($rows);
    }

    private function properties(): void
    {
        foreach (self::PROPERTIES as $code => [$type, $title, $attributes]) {
            $this->properties[$code] = Property::query()->create([
                'title' => $title,
                'code' => $code,
                'type' => $type,
                ...$attributes,
            ])->refresh();
        }

        foreach (self::VALUES as $code => $rows) {
            foreach ($rows as [$title, $color, $parent]) {
                $value = new PropertyValue(['property_id' => $this->properties[$code]->getKey(), 'color' => $color, 'title' => $title]);
                $parent === null ? $value->saveAsRoot() : $value->appendTo($this->values[$code][$parent]->refresh());
                $this->values[$code][$title] = $value->refresh();
            }
        }

        foreach ([['up to 1 kg', 'up-to-1-kg', null, 1], ['1–3 kg', '1-3-kg', 1, 3], ['from 3 kg', 'from-3-kg', 3, null]] as $n => [$title, $slug, $min, $max]) {
            PropertyInterval::query()->create([
                'property_id' => $this->properties['weight']->getKey(),
                'title' => $title,
                'slug' => $slug,
                'min' => $min,
                'max' => $max,
                'position' => $n,
            ]);
        }

        $sets = $this->app->make(PropertySets::class);

        foreach (self::SETS as $name => $codes) {
            $sets->save($this->categories[$name], array_map(fn (string $code): int => (int) $this->properties[$code]->getKey(), $codes));
        }

        $branchOf = [];

        foreach ($this->categories as $name => $category) {
            $branchOf[(int) $category->getKey()] = array_keys(array_filter(
                $this->categories,
                static fn (Category $other): bool => $other->lft <= $category->lft && $other->rgt >= $category->rgt,
            ));
        }

        $colours = array_values($this->values['color']);
        $material = $this->values['material'];
        $rows = [];
        $row = fn (Product $product, string $code, array $value): array => [
            'product_id' => $product->getKey(), 'property_id' => $this->properties[$code]->getKey(),
            'value_id' => null, 'number' => null, 'flag' => null, 'text' => null, ...$value,
        ];

        foreach ($this->products as $n => $product) {
            $branch = $branchOf[(int) $product->category_id];
            $in = static fn (string $name): bool => in_array($name, $branch, true);

            if ($in('Clothing') || $in('Shoes')) {
                $rows[] = $row($product, 'color', ['value_id' => $colours[$n % 5]->getKey()]);

                if ($n % 3 === 0) {
                    $rows[] = $row($product, 'color', ['value_id' => $colours[($n + 2) % 5]->getKey()]);
                }
            }

            if ($in('Shoes')) {
                $rows[] = $row($product, 'weight', ['number' => 0.4 + ($n % 13) / 10]);
            }

            if ($in('Kitchen')) {
                $knife = $in('Knives');
                $rows[] = $row($product, 'material', ['value_id' => ($knife
                    ? [$material['Stainless'], $material['Steel'], $material['Wood']]
                    : [$material['Stainless'], $material['Aluminium'], $material['Steel']])[$n % 3]->getKey()]);
                $rows[] = $row($product, 'weight', ['number' => $knife ? 0.1 + ($n % 4) / 10 : 0.5 + ($n % 8) / 2]);

                if ($n % 5 === 0) {
                    $rows[] = $row($product, 'wi-fi', ['flag' => true]);
                }

                if ($n % 2 === 0) {
                    $rows[] = $row($product, 'contents', ['text' => json_encode(['en' => 'The item, a manual, a box'])]);
                }
            }

            if ($in('Cookware')) {
                $rows[] = $row($product, 'diagonal', ['number' => 16 + ($n % 7) * 2]);
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table(ProductValues::TABLE)->insert($chunk);
        }
    }

    private function landings(): void
    {
        $sets = [
            'black-clothing' => ['Clothing', [$this->properties['color']->facetKey() => ['values' => [(string) $this->values['color']['Black']->getKey()]]], true, 3],
            'white-clothing' => ['Clothing', [$this->properties['color']->facetKey() => ['values' => [(string) $this->values['color']['White']->getKey()]]], true, 0],
            'adatum-clothing' => ['Clothing', ['brand' => ['values' => [(string) $this->brands['adatum']->getKey()]]], true, 0],
            'clothing-under-50' => ['Clothing', ['price' => ['min' => null, 'max' => 50]], true, 0],
            'woodgrove-shoes' => ['Shoes', ['brand' => ['values' => [(string) $this->brands['woodgrove']->getKey()]]], true, 0],
            'black-shoes' => ['Shoes', [$this->properties['color']->facetKey() => ['values' => [(string) $this->values['color']['Black']->getKey()]]], true, 0],
        ];
        $position = 0;

        foreach ($sets as $slug => [$base, $filters, $onCategory, $recommended]) {
            $category = $this->categories[$base];
            $landing = Landing::query()->create([
                'category_id' => $category->getKey(),
                'filters' => $filters,
                'name' => ucfirst(str_replace('-', ' ', $slug)),
                'slug' => $slug,
                'text_above' => '<p>A made-up collection.</p>',
                'on_category' => $onCategory,
                'position' => ++$position,
                'is_published' => true,
            ]);

            if ($recommended > 0) {
                $picked = Product::query()
                    ->whereIn('category_id', $category->subtree()->pluck('id'))
                    ->orderBy('id')
                    ->limit($recommended)
                    ->pluck('id');
                $landing->recommended()->attach($picked->mapWithKeys(static fn (mixed $id, int $i): array => [(int) $id => ['position' => $i]])->all());
            }

            $this->landings[$slug] = $landing->refresh();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function keys(): array
    {
        $ids = static fn (array $models): array => array_map(static fn ($model): int => (int) $model->getKey(), $models);

        return [
            'categories' => $ids($this->categories),
            'products' => $ids($this->products),
            'brands' => $ids($this->brands),
            'properties' => $ids($this->properties),
            'values' => array_map($ids, $this->values),
            'landings' => $ids($this->landings),
        ];
    }

    /**
     * Fresh models for the keys of `keys()`, under the same names and in the same order.
     *
     * @param  array<string, mixed>  $keys
     */
    private function reload(array $keys): self
    {
        /**
         * @template TModel of \Illuminate\Database\Eloquent\Model
         *
         * @param  class-string<TModel>  $class
         * @param  array<array-key, int>  $ids
         * @return array<array-key, TModel>
         */
        $find = static function (string $class, array $ids): array {
            $found = $class::query()->findMany(array_values($ids))->keyBy(static fn ($model): int => (int) $model->getKey());

            return array_map(static fn (int $id) => $found[$id], $ids);
        };

        $this->categories = $find(Category::class, $keys['categories']);
        $this->products = array_values($find(Product::class, $keys['products']));

        if ($keys['brands'] !== []) {
            $this->brands = $find(Brand::class, $keys['brands']);
        }

        if ($keys['properties'] !== []) {
            $this->properties = $find(Property::class, $keys['properties']);
            $this->values = array_map(static fn (array $ids): array => $find(PropertyValue::class, $ids), $keys['values']);
        }

        if ($keys['landings'] !== []) {
            $this->landings = $find(Landing::class, $keys['landings']);
        }

        return $this;
    }
}
