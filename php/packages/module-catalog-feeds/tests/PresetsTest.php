<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductBarcode;
use WebxUi\Catalog\Tests\Fixtures\Shop;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogFeeds\Build\FeedBuilder;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\CatalogFeeds\Presets\FeedPreset;
use WebxUi\CatalogFeeds\Presets\FeedPresets;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\CatalogStock\StockServiceProvider;

/**
 * §9 of the feeds spec: a preset makes a feed the check of §8 keeps, and that feed makes a file on
 * the demo's shop. What Google's rules ask of every value is `GoogleFeedRulesTest` of the last
 * stage; here is what the template itself answers for.
 */
final class PresetsTest extends TestCase
{
    private const G = 'http://base.google.com/ns/1.0';

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::seedOnce($this->app, self::class);
        Storage::fake('local');
        $this->app->make('url')->forceRootUrl('https://example.test');
        $this->app->make('url')->forceScheme('https');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function presets(): array
    {
        return ['google' => ['google', 'xml'], 'json' => ['json', 'json'], 'xml' => ['xml', 'xml'], 'csv' => ['csv', 'csv']];
    }

    #[Test]
    #[DataProvider('presets')]
    public function a_preset_makes_a_feed_that_is_kept_and_a_file(string $key, string $format): void
    {
        $feed = $this->fromPreset($key);
        $build = $this->build($feed);

        $this->assertSame($key, $feed->preset);
        $this->assertSame($format, $feed->format);
        $this->assertSame(FeedBuild::DONE, $build->status, (string) $build->error);
        $this->assertGreaterThan(0, $build->written);
        $this->assertSame($build->written, $this->written($format, $this->file($build)));
    }

    #[Test]
    public function the_file_of_google_is_rss_with_its_namespace_and_no_empty_attribute(): void
    {
        $file = $this->file($this->build($this->fromPreset('google')));
        $rss = new SimpleXMLElement($file);

        $this->assertSame('rss', $rss->getName());
        $this->assertSame('2.0', (string) $rss['version']);
        $this->assertSame(self::G, $rss->getDocNamespaces()['g']);
        $this->assertNotSame('', (string) $rss->channel->title);
        $this->assertSame('https://example.test', (string) $rss->channel->link);
        // RSS wants one, and a feed that was given no description of its own is still about the shop.
        $this->assertSame((string) $rss->channel->title, (string) $rss->channel->description);

        foreach ($rss->channel->item as $item) {
            $values = $item->children(self::G);

            foreach (['id', 'title', 'link', 'image_link', 'availability', 'price', 'condition', 'identifier_exists'] as $attribute) {
                $this->assertNotSame('', (string) $values->{$attribute}, $attribute);
            }

            foreach ($values as $name => $value) {
                $this->assertNotSame('', trim((string) $value), "An empty {$name}");
            }

            $this->assertMatchesRegularExpression('/^\d+\.\d{2} EUR$/', (string) $values->price);
            $this->assertContains((string) $values->availability, ['in_stock', 'out_of_stock']);
            $this->assertSame('new', (string) $values->condition);
        }
    }

    #[Test]
    public function a_discount_is_the_list_price_and_the_sale_price(): void
    {
        $product = $this->shop->productOf('Knives');
        $product->forceFill(['price' => 39, 'old_price' => 45])->save();

        $item = $this->itemOf($product, $this->file($this->build($this->fromPreset('google'))));

        $this->assertSame('45.00 EUR', (string) $item->price);
        $this->assertSame('39.00 EUR', (string) $item->sale_price);
    }

    #[Test]
    public function a_product_without_a_text_has_no_description_tag_and_stays_in_the_file(): void
    {
        $product = $this->shop->productOf('Knives', 1);
        $product->forceFill(['description' => null, 'summary' => null])->save();

        $item = $this->itemOf($product, $this->file($this->build($this->fromPreset('google'))));

        $this->assertCount(0, $item->description);
    }

    #[Test]
    public function only_the_main_barcode_goes_to_google_and_a_copy_of_the_template_may_send_them_all(): void
    {
        $product = $this->shop->productOf('Knives', 2);
        $product->forceFill(['barcode' => '4006381333931'])->save();
        ProductBarcode::query()->create(['product_id' => $product->id, 'barcode' => '5901234123457', 'position' => 0]);

        $feed = $this->fromPreset('google');
        $item = $this->itemOf($product, $this->file($this->build($feed)));

        $this->assertSame(['4006381333931'], array_map(strval(...), iterator_to_array($item->gtin, false)));

        $this->actingAs($this->editor(), 'cms')->putJson($this->api((string) $feed->id), [
            'item' => str_replace('{{#gtin}}<g:gtin>{{gtin}}</g:gtin>{{/gtin}}', '{{#gtins}}<g:gtin>{{value}}</g:gtin>{{/gtins}}', (string) $feed->item),
        ])->assertOk();

        $item = $this->itemOf($product, $this->file($this->build($feed)));

        $this->assertSame(['4006381333931', '5901234123457'], array_map(strval(...), iterator_to_array($item->gtin, false)));
    }

    #[Test]
    public function what_a_name_holds_does_not_break_a_file(): void
    {
        $product = $this->shop->productOf('Knives', 3);
        $product->setTranslation('name', 'en', 'Pots & "Pans", <XL>')->save();

        $xml = $this->file($this->build($this->fromPreset('xml')));
        $csv = $this->file($this->build($this->fromPreset('csv')));
        $json = $this->file($this->build($this->fromPreset('json')));
        $google = $this->itemOf($product, $this->file($this->build($this->fromPreset('google'))));

        $this->assertSame('Pots & "Pans", <XL>', (string) $google->title);
        $this->assertContains('Pots & "Pans", <XL>', array_map(strval(...), (new SimpleXMLElement($xml))->xpath('//name') ?: []));
        $this->assertStringContainsString('"Pots & ""Pans"", <XL>"', $csv);
        $this->assertContains('Pots & "Pans", <XL>', array_column((array) json_decode($json, true)['products'], 'name'));
    }

    #[Test]
    public function the_file_of_a_preset_is_in_the_language_of_the_feed(): void
    {
        $product = $this->shop->productOf('Knives', 4);
        $product->setTranslation('name', 'de', 'Küchenmesser')->save();

        $feed = $this->fromPreset('csv', ['locale' => 'de']);

        $this->assertStringContainsString('Küchenmesser', $this->file($this->build($feed)));
    }

    #[Test]
    public function the_form_wins_over_the_preset(): void
    {
        $feed = $this->fromPreset('google', ['name' => 'Autumn campaign', 'custom_labels' => ['3' => ['type' => 'constant', 'value' => 'autumn']]]);

        $this->assertSame('Autumn campaign', $feed->name);
        $this->assertSame('google', $feed->taxonomy);
        $this->assertSame(['3' => ['type' => 'constant', 'value' => 'autumn']], $feed->custom_labels);
    }

    #[Test]
    public function a_new_feed_is_called_as_its_preset_until_a_person_names_it(): void
    {
        $this->assertSame('Google Merchant', $this->fromPreset('google')->name);
    }

    #[Test]
    public function a_feed_holds_a_copy_and_the_preset_is_free_to_change(): void
    {
        $feed = $this->fromPreset('json');
        $copy = $feed->item;

        $this->app->forgetInstance(FeedPresets::class);
        $this->app->instance(FeedPresets::class, new FeedPresets([$this->preset('json', ['format' => 'text', 'item' => 'changed'])]));

        $this->assertSame($copy, $feed->refresh()->item);
        $this->assertSame(FeedBuild::DONE, $this->build($feed)->status);
    }

    #[Test]
    public function an_unknown_preset_is_refused_under_its_field(): void
    {
        $this->actingAs($this->editor(), 'cms')->postJson($this->api(), ['preset' => 'marketplace'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('preset');

        $this->assertSame(0, Feed::query()->count());
    }

    #[Test]
    public function a_feed_keeps_the_word_of_a_preset_that_is_gone(): void
    {
        $feed = $this->fromPreset('json');

        $this->app->instance(FeedPresets::class, new FeedPresets);

        $this->actingAs($this->editor(), 'cms')->putJson($this->api((string) $feed->id), ['name' => 'Renamed'])->assertOk();

        $this->assertSame('json', $feed->refresh()->preset);
    }

    #[Test]
    public function a_preview_tries_a_preset_and_keeps_nothing(): void
    {
        $answer = $this->actingAs($this->editor(), 'cms')->postJson($this->api('preview'), ['preset' => 'google'])->assertOk();

        $this->assertTrue($answer->json('data.ok'));
        $this->assertStringContainsString('<g:id>', (string) $answer->json('data.text'));
        $this->assertSame(0, Feed::query()->count());
    }

    #[Test]
    public function the_presets_are_offered_with_what_they_copy(): void
    {
        $meta = $this->actingAs($this->editor(['catalog.view']), 'cms')->getJson($this->api('meta'))->assertOk();

        $this->assertSame(['google', 'json', 'xml', 'csv'], array_column($meta->json('data.presets'), 'key'));
        $this->assertSame('xml', $meta->json('data.presets.0.values.format'));
        $this->assertSame('google', $meta->json('data.presets.0.values.taxonomy'));
        $this->assertStringContainsString('<g:id>', $meta->json('data.presets.0.values.item'));
        $this->assertNotSame('', $meta->json('data.presets.0.description'));
        $this->assertSame(['xml', 'json', 'csv', 'text'], $meta->json('data.formats'));
        $this->assertContains('price_range', $meta->json('data.label_sources'));
    }

    #[Test]
    public function a_site_adds_a_preset_and_a_taken_key_is_a_mistake(): void
    {
        $presets = $this->app->make(FeedPresets::class);
        $presets->register($this->preset('parts', ['format' => 'text', 'item' => '{{sku}}']));

        $this->assertSame('text', $this->fromPreset('parts')->format);

        $this->expectException(InvalidArgumentException::class);
        $presets->register($this->preset('google', []));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function preset(string $key, array $values): FeedPreset
    {
        return new class($key, $values) implements FeedPreset
        {
            /**
             * @param  array<string, mixed>  $values
             */
            public function __construct(private readonly string $key, private readonly array $values) {}

            public function key(): string
            {
                return $this->key;
            }

            public function name(): string
            {
                return 'Spare parts';
            }

            public function description(): string
            {
                return 'A file of a site';
            }

            public function values(): array
            {
                return $this->values;
            }
        };
    }

    /**
     * What the dialog does: a preset and whatever the form changed, through the API.
     *
     * @param  array<string, mixed>  $values
     */
    private function fromPreset(string $key, array $values = []): Feed
    {
        $id = $this->actingAs($this->editor(), 'cms')->postJson($this->api(), ['preset' => $key, ...$values])
            ->assertCreated()
            ->json('data.id');

        return Feed::query()->findOrFail($id);
    }

    private function build(Feed $feed): FeedBuild
    {
        $build = $this->app->make(FeedBuilder::class)->start($feed->refresh());
        $this->assertInstanceOf(FeedBuild::class, $build);

        return $build->refresh();
    }

    private function file(FeedBuild $build): string
    {
        return (string) Storage::disk('local')->get((string) $build->path);
    }

    private function written(string $format, string $file): int
    {
        return match ($format) {
            'json' => count((array) json_decode($file, true, flags: JSON_THROW_ON_ERROR)['products']),
            'csv' => count(array_filter(explode("\n", trim($file)))) - 1,
            default => count((new SimpleXMLElement($file))->xpath('//item | //product') ?: []),
        };
    }

    /** The `g:` attributes of a product, out of a file of the Google preset. */
    private function itemOf(Product $product, string $file): SimpleXMLElement
    {
        foreach ((new SimpleXMLElement($file))->channel->item as $item) {
            $values = $item->children(self::G);

            if ((string) $values->id === (string) $product->id) {
                return $values;
            }
        }

        $this->fail("Product {$product->id} is not in the file.");
    }
}
