<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Shortcodes\Shortcodes;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Catalog\Exchange\ExchangeColumn;
use WebxUi\Catalog\Exchange\ExchangeColumns;
use WebxUi\Catalog\Exchange\ImportContext;
use WebxUi\Catalog\Exchange\PrivateColumn;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductBarcode;
use WebxUi\Catalog\Models\ProductImage;
use WebxUi\Catalog\Tests\Fixtures\Shop;
use WebxUi\Catalog\Variables\ComputedVariable;
use WebxUi\Catalog\Variables\HtmlVariable;
use WebxUi\Catalog\Variables\ProductVariables;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogProperties\Catalog\ProductValues;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\CatalogStock\Models\StockStatus;
use WebxUi\CatalogStock\StockServiceProvider;
use WebxUi\Templates\Cdata;
use WebxUi\Templates\Template;
use WebxUi\Templates\Variable;

/**
 * The variables of §4.2–§4.3 of the feeds spec over a shop with every satellite: all of them for a
 * page of products in a number of queries that does not depend on the page.
 */
final class ProductVariablesTest extends TestCase
{
    use CountsQueries;

    private const NAMES = [
        // §4.2
        'sku', 'external_id', 'slug', 'barcode', 'mpn', 'barcodes', 'name', 'summary', 'category', 'categories',
        'price', 'old_price', 'unit', 'priority', 'is_published', 'brand', 'labels', 'stock', 'stock_name', 'is_purchasable',
        // §4.3
        'id', 'url', 'image_link', 'images', 'additional_images', 'availability', 'price_with_currency', 'list_price',
        'list_price_with_currency', 'sale_price', 'sale_price_with_currency', 'sale_price_effective_date', 'description_html',
        'description_cdata', 'description_text', 'title', 'long_text', 'gtin', 'gtins', 'identifier_exists', 'group_id',
        'product_type', 'category_id', 'platform_category', 'google_product_category', 'custom_label_0', 'custom_label_1',
        'custom_label_2', 'custom_label_3', 'custom_label_4',
    ];

    private Shop $shop;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            BrandsServiceProvider::class,
            LabelsServiceProvider::class,
            StockServiceProvider::class,
            CatalogPropertiesServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-catalog.price.currency', 'usd');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::seedOnce($this->app, self::class);
        // What a queue worker has: no request, so addresses start at `app.url` (§4.3 of the spec).
        $this->app->make('url')->forceRootUrl('https://example.test');
        $this->app->make('url')->forceScheme('https');
    }

    #[Test]
    public function every_variable_of_the_spec_is_in_the_registry(): void
    {
        $names = array_keys($this->variables()->all());

        $this->assertSame([], array_values(array_diff(self::NAMES, $names)));
        $this->assertContains('property.color', $names);
        $this->assertContains('property.wi_fi', $names);
        $this->assertNotContains('color', $names, 'a property is property.<code>, not its column');
        $this->assertInstanceOf(HtmlVariable::class, $this->variables()->variables()->variable('description_html'));
        $this->assertTrue($this->variables()->variables()->knows('value', ['gtins']));
        $this->assertFalse($this->variables()->variables()->knows('nobody'));
    }

    #[Test]
    public function a_page_costs_the_same_whatever_its_size_and_nothing_repeats(): void
    {
        $this->assertSame(Shop::PRODUCTS, Product::query()->count());
        // A picture of the library in every description: the one place that would ask per product.
        Product::query()->orderBy('id')->each(static function (Product $product): void {
            $product->forceFill(['description' => '<p>Text</p><img data-wx-path="media/'.$product->id.'.jpg" src="/old.jpg">'])->saveQuietly();
        });

        $page = fn (int $size): Collection => Product::query()->orderBy('id')->limit($size)->get()->toBase();
        // The first call fills what a process keeps: locales, properties, sets.
        $this->variables()->values($page(1), 'en');

        $small = $this->recordQueries(fn () => $this->variables()->values($page(5), 'en'));
        $large = $this->recordQueries(fn () => $this->variables()->values($page(60), 'en'));

        $this->assertSame($small->count(), $large->count(), $large->describe());
        $rows = $this->assertNoRepeatedQueries(fn () => $this->variables()->values($page(60), 'en'));

        $this->assertCount(60, $rows);

        foreach ([...self::NAMES, 'property.color'] as $name) {
            $this->assertArrayHasKey($name, reset($rows));
        }
    }

    #[Test]
    public function prices_read_as_the_marketplace_wants_them(): void
    {
        $sale = $this->product('Sale', $this->shop->category('Boots'), ['price' => 1200, 'old_price' => 1500]);
        $plain = $this->product('Plain', $this->shop->category('Boots'), ['price' => 80.5, 'old_price' => 50]);
        $none = $this->product('None', $this->shop->category('Boots'));
        $rows = $this->rows([$sale, $plain, $none]);

        $this->assertSame('1200.00', $rows[$sale->id]['price']);
        $this->assertSame('1200.00 USD', $rows[$sale->id]['price_with_currency']);
        $this->assertSame('1500.00', $rows[$sale->id]['list_price']);
        $this->assertSame('1500.00 USD', $rows[$sale->id]['list_price_with_currency']);
        $this->assertSame('1200.00', $rows[$sale->id]['sale_price']);
        $this->assertSame('1200.00 USD', $rows[$sale->id]['sale_price_with_currency']);

        $this->assertSame('80.50', $rows[$plain->id]['list_price']);
        $this->assertSame('', $rows[$plain->id]['sale_price']);
        $this->assertSame('', $rows[$plain->id]['sale_price_with_currency']);

        $this->assertSame('', $rows[$none->id]['price']);
        $this->assertSame('', $rows[$none->id]['price_with_currency']);
        $this->assertSame('', $rows[$sale->id]['sale_price_effective_date']);
    }

    #[Test]
    public function addresses_are_absolute_and_a_slug_goes_through_as_it_is(): void
    {
        $product = $this->product('Brake pads', $this->shop->category('Boots'), ['slug' => 'brake-pads']);
        ProductImage::query()->create(['product_id' => $product->id, 'path' => 'catalog/video.jpg', 'position' => 0, 'video_provider' => 'youtube', 'video' => 'abc']);

        foreach (range(1, 12) as $position) {
            ProductImage::query()->create(['product_id' => $product->id, 'path' => "catalog/{$position}.jpg", 'alt' => "Picture {$position}", 'position' => $position]);
        }

        $row = $this->rows([$product])[$product->id];

        $this->assertSame('https://example.test/brake-pads-'.$product->id, $row['url']);
        $this->assertStringStartsWith('https://example.test/', $row['image_link']);
        $this->assertStringEndsWith('catalog/1.jpg', $row['image_link']);
        $this->assertCount(12, $row['images']);
        $this->assertSame('Picture 1', $row['images'][0]['alt']);
        $this->assertCount(10, $row['additional_images']);
        $this->assertStringEndsWith('catalog/2.jpg', $row['additional_images'][0]['url']);
    }

    #[Test]
    public function texts_are_cut_by_the_word_and_a_description_gives_all_three_forms(): void
    {
        $long = str_repeat('word ', 40);
        $product = $this->product(trim($long), $this->shop->category('Boots'), [
            'summary' => 'Short.',
            'description' => '<p>Ceramic &amp; quiet</p>',
        ]);
        $bare = $this->product('Bare', $this->shop->category('Boots'), ['summary' => 'Only a summary.']);
        $rows = $this->rows([$product, $bare]);

        $this->assertSame(149, mb_strlen($rows[$product->id]['title']));
        $this->assertStringEndsWith('word', $rows[$product->id]['title']);
        $this->assertSame('<p>Ceramic &amp; quiet</p>', $rows[$product->id]['description_html']);
        $this->assertInstanceOf(Cdata::class, $rows[$product->id]['description_cdata']);
        $this->assertSame('<p>Ceramic &amp; quiet</p>', $rows[$product->id]['description_cdata']->toText());
        $this->assertSame('Ceramic & quiet', $rows[$product->id]['description_text']);
        $this->assertSame('Ceramic & quiet', $rows[$product->id]['long_text']);

        $this->assertSame('Only a summary.', $rows[$bare->id]['long_text']);
        $this->assertSame('', $rows[$bare->id]['description_cdata']);
    }

    /* A marketplace gets the product's words, not the section of cards the site prints among them. */
    #[Test]
    public function a_block_shortcode_stays_out_of_every_form_of_the_description(): void
    {
        app(Shortcodes::class)->register('section', '<section><h2>Picks</h2>Trail camera 10.00 Buy</section>', block: true);
        $product = $this->product('Pads', $this->shop->category('Boots'), ['description' => '<p>Ceramic</p><p>[section]</p>']);
        $row = $this->rows([$product])[$product->id];

        $this->assertSame('<p>Ceramic</p>', $row['description_html']);
        $this->assertSame('Ceramic', $row['description_text']);
    }

    #[Test]
    public function identifiers_pass_only_when_they_are_real(): void
    {
        $real = $this->product('Real', $this->shop->category('Boots'), ['barcode' => '4006381 333931']);
        ProductBarcode::query()->create(['product_id' => $real->id, 'barcode' => '5901234123457', 'position' => 0]);
        ProductBarcode::query()->create(['product_id' => $real->id, 'barcode' => 'INTERNAL-1', 'position' => 1]);
        $internal = $this->product('Internal', $this->shop->category('Boots'), ['barcode' => '2000000000008']);
        $part = $this->product('Part', $this->shop->category('Boots'), ['mpn' => 'WX-9001']);
        $rows = $this->rows([$real, $internal, $part]);

        $this->assertSame('4006381333931', $rows[$real->id]['gtin']);
        $this->assertSame([['value' => '4006381333931'], ['value' => '5901234123457']], $rows[$real->id]['gtins']);
        $this->assertSame([['value' => '5901234123457'], ['value' => 'INTERNAL-1']], $rows[$real->id]['barcodes']);
        $this->assertSame('yes', $rows[$real->id]['identifier_exists']);

        $this->assertSame('2000000000008', $rows[$internal->id]['barcode'], 'the barcode itself stays as it is');
        $this->assertSame('', $rows[$internal->id]['gtin']);
        $this->assertSame('no', $rows[$internal->id]['identifier_exists']);

        $this->assertSame('WX-9001', $rows[$part->id]['mpn']);
        $this->assertSame('yes', $rows[$part->id]['identifier_exists']);
    }

    #[Test]
    public function a_category_reads_as_a_path(): void
    {
        $product = $this->product('Knife', $this->shop->category('Knives'));
        $product->categories()->attach($this->shop->category('Boots')->getKey());
        $row = $this->rows([$product])[$product->id];

        $this->assertSame('Home > Kitchen > Knives', $row['product_type']);
        $this->assertSame('Home/Kitchen/Knives', $row['category']);
        $this->assertSame('Shoes/Boots', $row['categories']);
        $this->assertSame((string) $this->shop->category('Knives')->getKey(), $row['category_id']);
    }

    #[Test]
    public function the_satellites_give_what_a_buyer_reads(): void
    {
        $products = Product::query()->orderBy('id')->limit(10)->get();
        $rows = $this->variables()->values($products->toBase(), 'en');
        $statuses = StockStatus::query()->pluck('is_purchasable', 'code');
        $branded = array_filter($rows, static fn (array $row): bool => $row['brand'] !== '');
        $labelled = array_filter($rows, static fn (array $row): bool => $row['labels'] !== []);

        $this->assertNotSame([], $branded);
        $this->assertNotSame([], $labelled);
        $this->assertSame(['code', 'name'], array_keys(reset($labelled)['labels'][0]));

        foreach ($rows as $row) {
            $this->assertNotSame('', $row['stock'], 'the default status where nobody chose one');
            $this->assertNotSame('', $row['stock_name']);
            $sold = (bool) $statuses[$row['stock']];
            $this->assertSame($sold ? '1' : '', $row['is_purchasable']);
            $this->assertSame($sold ? 'in_stock' : 'out_of_stock', $row['availability']);
        }

        $this->assertContains('out_of_stock', array_column($rows, 'availability'));
    }

    #[Test]
    public function a_property_is_named_in_the_language_of_the_page(): void
    {
        $property = $this->shop->properties['color'];
        $values = array_values($this->shop->values['color']);
        $product = $this->shop->productOf('Shirts');
        $values[0]->setTranslation('title', 'en', 'Red')->save();
        $values[1]->setTranslation('title', 'en', 'Blue')->save();
        $this->app->make(ProductValues::class)->put($product, $property, [$values[0]->id, $values[1]->id]);

        $row = $this->rows([$product])[$product->id];

        $this->assertSame(['Red', 'Blue'], explode(';', $row['property.color']));
        $this->assertSame('Red;Blue', Template::render('{{property.color}}', ['property.color' => $row['property.color']], static fn (string $value): string => $value)['text']);
    }

    #[Test]
    public function a_site_adds_a_variable_of_its_own(): void
    {
        $this->variables()->register(new ComputedVariable(
            new Variable('warranty', 'Warranty', '24'),
            static fn (Collection $products): array => $products->mapWithKeys(static fn (Product $product): array => [$product->id => '24'])->all(),
        ));

        $product = $this->shop->productOf('Boots');

        $this->assertSame('24', $this->rows([$product])[$product->id]['warranty']);
        $this->assertTrue($this->variables()->variables()->knows('warranty'));

        $this->expectException(InvalidArgumentException::class);
        $this->variables()->register(new ComputedVariable(new Variable('warranty', 'Again'), static fn (): array => []));
    }

    #[Test]
    public function a_registered_variable_beats_a_column_and_a_private_column_is_no_variable(): void
    {
        $columns = $this->app->make(ExchangeColumns::class);
        $columns->register($this->column('cost'));
        $columns->register($this->column('supplier_note', private: true));

        $names = array_keys($this->variables()->all());

        $this->assertContains('cost', $names);
        $this->assertNotContains('supplier_note', $names);

        $product = $this->shop->productOf('Boots');
        $this->assertSame('cell', $this->rows([$product])[$product->id]['cost']);

        // `brand` is a column too: a slug in a file, a name in a template.
        $this->assertNotInstanceOf(ExchangeColumn::class, $this->variables()->all()['brand']);
        $this->assertInstanceOf(ComputedVariable::class, $this->variables()->all()['brand']);
    }

    #[Test]
    public function a_call_narrows_the_names_and_overrides_what_a_feed_decides(): void
    {
        $product = $this->shop->productOf('Boots');
        $override = new ComputedVariable(new Variable('availability', 'Mapped'), static fn (): array => [(int) $product->id => 'backorder']);

        $row = $this->variables()->values(collect([$product]), 'en', ['sku', 'availability'], [$override])[$product->id];

        $this->assertSame(['sku', 'availability'], array_keys($row));
        $this->assertSame('backorder', $row['availability']);
    }

    private function variables(): ProductVariables
    {
        return $this->app->make(ProductVariables::class);
    }

    /**
     * @param  list<Product>  $products
     * @return array<int, array<string, mixed>>
     */
    private function rows(array $products): array
    {
        return $this->variables()->values(collect($products), 'en');
    }

    private function column(string $key, bool $private = false): ExchangeColumn
    {
        $column = new class($key) implements ExchangeColumn
        {
            public function __construct(private readonly string $key) {}

            public function key(): string
            {
                return $this->key;
            }

            public function label(): string
            {
                return $this->key;
            }

            public function field(): string
            {
                return '';
            }

            public function localized(): bool
            {
                return false;
            }

            public function export(Collection $products, ?string $locale): array
            {
                return $products->mapWithKeys(static fn (Product $product): array => [$product->id => 'cell'])->all();
            }

            public function parse(string $cell, ImportContext $context): mixed
            {
                return $cell;
            }
        };

        if (! $private) {
            return $column;
        }

        return new class($key) implements ExchangeColumn, PrivateColumn
        {
            public function __construct(private readonly string $key) {}

            public function key(): string
            {
                return $this->key;
            }

            public function label(): string
            {
                return $this->key;
            }

            public function field(): string
            {
                return '';
            }

            public function localized(): bool
            {
                return false;
            }

            public function export(Collection $products, ?string $locale): array
            {
                return [];
            }

            public function parse(string $cell, ImportContext $context): mixed
            {
                return $cell;
            }
        };
    }
}
