<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Tests\Fixtures\Shop;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogFeeds\Build\FeedBuilder;
use WebxUi\CatalogFeeds\Build\FeedProof;
use WebxUi\CatalogFeeds\Build\FeedTrial;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\CatalogStock\StockServiceProvider;

/**
 * §8 of the feeds spec on a real shop: a feed that would not make a valid file is not kept, the
 * mistake says where it is, and a preview is the same check that keeps nothing.
 */
final class TrialTest extends TestCase
{
    /** No product of the shop answers to this. */
    private const NOTHING = ['price' => ['min' => 999999999]];

    private const XML = ['format' => 'xml', 'head' => "<feed>\n", 'item' => '<item><id>{{id}}</id></item>', 'foot' => "\n</feed>"];

    private const JSON = ['format' => 'json', 'head' => '{"items":[', 'item' => '{"id":"{{id}}"}', 'foot' => ']}'];

    /** Longer in bytes than a column counted in characters reaches: a place must not slide into it. */
    private const WORDS = 'Интернет-магазин запчастей для всех видов техники и даже больше';

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
        $this->actingAs($this->editor(), 'cms');
    }

    #[Test]
    public function a_good_feed_is_kept_and_has_nothing_to_say(): void
    {
        $this->save(self::XML)->assertCreated()->assertJsonPath('warnings', []);
        $this->save(self::JSON)->assertCreated();
        $this->save(['format' => 'csv', 'head' => "id,title\n", 'item' => '{{id}},{{title}}', 'foot' => ''])->assertCreated();
        $this->save(['format' => 'text', 'item' => '{{id}} <not xml'])->assertCreated();
    }

    #[Test]
    public function a_template_mustache_cannot_read_is_not_kept(): void
    {
        $this->refused(['item' => '{{#images}}{{url}}'], 'item', 'The section {{#images}} is not closed');
        $this->refused(['item' => '{{id}}', 'head' => '{{/categories}}'], 'head', 'closes a section that was never opened');
        $this->refused(['item' => '{{id}}', 'foot' => '{{products_count'], 'foot', 'is not closed');
        $this->refused(['item' => '{{>partial}}'], 'item', 'Partials');

        $id = $this->save(['item' => '{{id}}'])->assertCreated()->json('data.id');
        $this->putJson($this->api((string) $id), ['item' => '   '])
            ->assertUnprocessable()
            ->assertJsonPath('errors.item.0', 'The feed has no template of a product.');
    }

    #[Test]
    public function a_name_is_known_where_it_stands_and_nowhere_else(): void
    {
        $this->refused(['item' => '{{nope}}'], 'item', '{{nope}} is not known in the part “Product”.');
        // A head has no product.
        $this->refused(['item' => '{{id}}', 'head' => '{{title}}'], 'head', '{{title}} is not known in the part “Header”.');
        $this->refused(['item' => '{{products_count}}'], 'item', '{{products_count}}');
        $this->refused(['item' => '{{id}}', 'head' => '{{#categories}}{{nope}}{{/categories}}'], 'head', '{{nope}}');

        // The fields of a list, its positions, and what an enclosing scope has.
        $this->save([
            'format' => 'text',
            'head' => "{{#categories}}{{path}}{{^last}};{{/last}}{{/categories}}{{#currencies}}{{code}}{{/currencies}}{{shop.name}}\n",
            'item' => '{{id}}{{#images}}{{^first}},{{/first}}{{url}}{{title}}{{/images}}',
        ])->assertCreated();
    }

    #[Test]
    public function a_variable_in_a_literal_cdata_is_refused_in_xml_only(): void
    {
        $item = '<item><![CDATA[{{title}}]]></item>';

        $this->refused([...self::XML, 'item' => $item], 'item', 'cannot stand inside a literal CDATA');
        $this->save(['format' => 'text', 'item' => $item])->assertCreated();
    }

    #[Test]
    public function broken_xml_comes_with_its_line_and_fragment_and_is_not_kept(): void
    {
        $values = [...self::XML, 'item' => "<item>\n  <id>{{id}}</wrong>\n</item>"];
        $first = (int) Product::query()->visible()->orderBy('id')->value('id');

        $message = $this->refused($values, 'item', 'The file is not valid XML, line 3:');
        $this->assertStringContainsString('Part “Product”, line 2:', $message);
        $this->assertStringContainsString('</wrong>', $message);

        $problem = $this->preview($values)->assertOk()->assertJsonPath('data.ok', false)->json('data.errors.0');

        $this->assertSame('item', $problem['field']);
        $this->assertSame('item', $problem['part']);
        $this->assertSame(3, $problem['line']);
        $this->assertSame(2, $problem['part_line']);
        $this->assertStringContainsString('</wrong>', $problem['fragment']);
        $this->assertIsInt($problem['product']);
        $this->assertGreaterThanOrEqual($first, $problem['product']);
    }

    #[Test]
    public function a_mistake_is_found_in_the_part_it_is_in_even_on_one_line(): void
    {
        $head = $this->preview([...self::XML, 'head' => '<feed><a></b>', 'foot' => '</feed>'])->json('data.errors.0');
        $this->assertSame('head', $head['part']);
        $this->assertSame(1, $head['line']);

        $foot = $this->preview([...self::XML, 'head' => '<feed>', 'foot' => '</fed>'])->json('data.errors.0');
        $this->assertSame('foot', $foot['part']);
        $this->assertStringContainsString('</fed>', $foot['fragment']);
    }

    #[Test]
    public function products_without_a_root_cannot_pass_as_xml(): void
    {
        $this->refused(['format' => 'xml', 'item' => '<item>{{id}}</item>'], 'item', 'Extra content');
    }

    #[Test]
    public function broken_json_comes_with_its_line_and_fragment_and_is_not_kept(): void
    {
        $values = [...self::JSON, 'head' => "{\n\"items\": [\n", 'item' => "{\n  \"id\": {{id}}x\n}", 'foot' => "\n]}"];

        $message = $this->refused($values, 'item', 'The sample is not valid JSON, line 4:');
        $this->assertStringContainsString('Part “Product”, line 2:', $message);

        $problem = $this->preview($values)->json('data.errors.0');

        $this->assertSame(4, $problem['line']);
        $this->assertSame('item', $problem['part']);
        $this->assertSame(2, $problem['part_line']);
        $this->assertStringContainsString('"id":', $problem['fragment']);
    }

    #[Test]
    public function a_frame_that_breaks_json_is_shown_in_the_frame(): void
    {
        $this->refused([...self::JSON, 'foot' => "\n]"], 'foot', 'The sample is not valid JSON, line 2:');
    }

    #[Test]
    public function a_product_that_only_reads_with_the_frame_is_refused_as_a_build_would_fail_it(): void
    {
        // Whole, this is valid; a build reads each product by itself and would fail on the first.
        $this->refused(['format' => 'json', 'head' => '{"id":"', 'item' => '{{id}}"', 'foot' => '}', 'separator' => ','], 'item', 'not valid JSON');
    }

    #[Test]
    public function a_feed_of_json_lines_has_no_whole_to_read(): void
    {
        $this->save(['format' => 'json', 'item' => '{"id":"{{id}}"}', 'separator' => "\n"])
            ->assertCreated()
            ->assertJsonPath('data.separator', "\n");
    }

    #[Test]
    public function a_row_with_the_wrong_number_of_fields_is_shown_by_its_line(): void
    {
        $message = $this->refused(['format' => 'csv', 'head' => "a,b,c\n", 'item' => '{{id}},{{id}}'], 'item', 'Line 2 of the file has 2 fields instead of 3.');

        $this->assertStringContainsString('Part “Product”, line 1:', $message);
    }

    #[Test]
    public function a_line_break_of_a_template_survives_the_request(): void
    {
        $id = $this->save(['format' => 'csv', 'head' => "id,title\n", 'item' => '{{id}},x', 'separator' => "\n"])
            ->assertCreated()
            ->assertJsonPath('data.head', "id,title\n")
            ->json('data.id');

        $this->putJson($this->api((string) $id), ['head' => ''])->assertOk()->assertJsonPath('data.head', '');
    }

    /**
     * @return iterable<string, array{0: string, 1: mixed}>
     */
    public static function emptyNotAllowed(): iterable
    {
        yield 'name' => ['name', ''];
        yield 'name of spaces' => ['name', '   '];
        yield 'format' => ['format', ''];
        yield 'locale' => ['locale', ''];
        yield 'schedule' => ['schedule', ''];
        yield 'is_active' => ['is_active', ''];
        yield 'rebuild_on_change' => ['rebuild_on_change', ''];
        yield 'filter' => ['filter', ''];
        yield 'stock_map' => ['stock_map', ''];
        yield 'custom_labels' => ['custom_labels', ''];
        yield 'sources' => ['sources', ''];
        yield 'options' => ['options', ''];
    }

    #[Test]
    #[DataProvider('emptyNotAllowed')]
    public function an_empty_value_is_refused_under_its_field(string $field, mixed $value): void
    {
        $feed = $this->saved(self::XML);

        $this->putJson($this->api((string) $feed->id), [$field => $value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertSame('Spare parts', $feed->refresh()->name);
        $this->assertSame('xml', $feed->format);
    }

    #[Test]
    public function an_empty_value_of_a_field_that_may_be_empty_is_null(): void
    {
        $feed = $this->saved([...self::XML, 'taxonomy' => 'google', 'schedule' => 'daily', 'schedule_at' => '04:30', 'preset' => 'google']);

        $this->putJson($this->api((string) $feed->id), ['taxonomy' => '', 'schedule_at' => '  ', 'preset' => ''])
            ->assertOk()
            ->assertJsonPath('data.taxonomy', null)
            ->assertJsonPath('data.schedule_at', null)
            ->assertJsonPath('data.preset', null);
    }

    #[Test]
    public function the_fields_around_the_templates_are_trimmed_and_the_templates_are_not(): void
    {
        $this->save([...self::XML, 'name' => '  Spare parts  ', 'head' => "  <feed>\n", 'options' => ['description' => '  Parts  ']])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Spare parts')
            ->assertJsonPath('data.head', "  <feed>\n")
            ->assertJsonPath('data.options.description', 'Parts');
    }

    #[Test]
    public function a_tab_stays_the_delimiter_of_a_csv(): void
    {
        $this->save(['format' => 'csv', 'item' => "{{id}}\tx", 'options' => ['delimiter' => "\t"]])
            ->assertCreated()
            ->assertJsonPath('data.options.delimiter', "\t");
    }

    #[Test]
    public function an_empty_value_is_refused_in_a_preview_too_and_an_empty_feed_is_no_feed(): void
    {
        $this->preview([...self::XML, 'locale' => ''])->assertUnprocessable()->assertJsonValidationErrors('locale');
        $this->preview([...self::XML, 'feed' => ''])->assertOk()->assertJsonPath('data.ok', true);
    }

    #[Test]
    public function a_part_is_found_past_text_that_is_not_ascii_in_xml(): void
    {
        $this->nameProducts();

        $values = [
            'format' => 'xml',
            'head' => '<feed><shop>'.self::WORDS.'</shop>',
            'item' => '<item><t>{{title}}</t><id>{{id}}</wrong></item>',
            'foot' => '</feed>',
        ];

        $this->refused($values, 'item', 'The file is not valid XML, line 1:');

        $problem = $this->preview($values)->json('data.errors.0');

        $this->assertSame('item', $problem['field']);
        $this->assertSame('item', $problem['part']);
        $this->assertSame($this->firstProduct(), $problem['product']);
        $this->assertStringContainsString('</wrong>', $problem['fragment']);
    }

    #[Test]
    public function a_part_is_found_past_text_that_is_not_ascii_on_a_later_line_of_xml(): void
    {
        $this->nameProducts();

        $problem = $this->preview([
            'format' => 'xml',
            'head' => "<feed>\n<shop>".self::WORDS.'</shop>',
            'item' => '<item><t>{{title}}</t><id>{{id}}</id></item>',
            'foot' => '</wrong>',
            'separator' => ' ',
        ])->json('data.errors.0');

        $this->assertSame('foot', $problem['part']);
        $this->assertSame(2, $problem['line']);
        $this->assertStringContainsString('</wrong>', $problem['fragment']);
    }

    #[Test]
    public function a_part_is_found_past_text_that_is_not_ascii_in_json(): void
    {
        $this->nameProducts();

        $values = [
            'format' => 'json',
            'head' => '{"shop":"'.self::WORDS.'","items":[',
            'item' => '{"title":"{{title}}","id":{{id}}x}',
            'foot' => ']}',
        ];

        $this->refused($values, 'item', 'The sample is not valid JSON, line 1:');

        $problem = $this->preview($values)->json('data.errors.0');

        $this->assertSame('item', $problem['part']);
        $this->assertSame($this->firstProduct(), $problem['product']);
        $this->assertStringContainsString('x}', $problem['fragment']);
    }

    #[Test]
    public function a_mistake_of_a_frame_of_json_on_one_line_is_shown_in_the_frame(): void
    {
        $this->nameProducts();

        $item = '{"title":"{{title}}"}';

        $separator = $this->preview(['format' => 'json', 'head' => '[', 'item' => $item, 'foot' => ']', 'separator' => ';'])->json('data.errors.0');
        $this->assertSame('separator', $separator['part']);
        $this->assertNull($separator['product']);

        $foot = $this->preview(['format' => 'json', 'head' => '[', 'item' => $item, 'foot' => ']}', 'separator' => ','])->json('data.errors.0');
        $this->assertSame('foot', $foot['part']);
        $this->assertNull($foot['product']);

        $head = $this->preview(['format' => 'json', 'head' => '[}', 'item' => $item, 'foot' => ']', 'separator' => ','])->json('data.errors.0');
        $this->assertSame('head', $head['part']);
    }

    #[Test]
    public function a_product_cut_short_is_the_products_mistake_and_not_what_follows_it(): void
    {
        $problem = $this->preview(['format' => 'json', 'head' => '[', 'item' => '{"id":"{{id}}"', 'foot' => ']', 'separator' => ','])->json('data.errors.0');

        $this->assertSame('item', $problem['part']);
        $this->assertSame($this->firstProduct(), $problem['product']);
    }

    #[Test]
    public function a_mistake_is_told_in_the_language_of_the_panel_and_not_of_the_feed(): void
    {
        App::setLocale('en');

        $feed = new Feed;
        $feed->forceFill([...self::XML, 'head' => '<feed><a></b>', 'locale' => 'ru', 'separator' => "\n", 'currency' => 'EUR']);

        $problem = $this->app->make(FeedTrial::class)->check($feed)->problem;

        $this->assertStringContainsString('The file is not valid XML', (string) $problem?->message);
        $this->assertSame('en', App::getLocale());
    }

    #[Test]
    public function with_no_product_to_try_the_frame_is_read_and_the_answer_warns(): void
    {
        $response = $this->save([...self::XML, 'filter' => self::NOTHING])->assertCreated();

        $this->assertSame(FeedProof::NO_PRODUCTS, $response->json('warnings.0.code'));
        $this->assertStringContainsString('only the header and the footer', (string) $response->json('warnings.0.message'));

        $this->refused([...self::XML, 'foot' => '</other>', 'filter' => self::NOTHING], 'foot', 'not valid XML');

        // Nothing at all to read is no mistake either.
        $this->save(['format' => 'xml', 'item' => '<item/>', 'filter' => self::NOTHING])->assertCreated();
    }

    #[Test]
    public function what_a_filter_or_a_source_cannot_find_is_refused_under_its_field(): void
    {
        $this->refused(['item' => '{{id}}', 'filter' => ['properties' => ['p.99999' => ['1']]]], 'filter', '[p.99999]');
        $this->refused(['item' => '{{mpn}}', 'sources' => ['mpn' => ['type' => 'property', 'property' => 'no-such']]], 'sources', '[no-such]');
    }

    #[Test]
    public function a_sample_is_what_the_build_writes(): void
    {
        config(['webx-catalog-feeds.sample' => 3]);

        $values = ['format' => 'text', 'item' => '{{id}}:{{title}}:{{price_with_currency}}'];
        $text = (string) $this->preview($values)->assertJsonPath('data.written', 3)->json('data.text');

        $feed = Feed::query()->findOrFail($this->save($values)->assertCreated()->json('data.id'));
        $build = $this->app->make(FeedBuilder::class)->start($feed);
        $this->assertInstanceOf(FeedBuild::class, $build);

        $this->assertSame(3, substr_count($text, "\n") + 1);
        $this->assertStringStartsWith($text."\n", (string) Storage::disk('local')->get((string) $build->refresh()->path));
    }

    #[Test]
    public function a_preview_answers_with_the_counters_and_keeps_nothing(): void
    {
        $feeds = Feed::query()->count();
        $visible = Product::query()->visible()->count();

        $response = $this->preview(self::XML)->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.errors', [])
            ->assertJsonPath('data.selected', $visible)
            ->assertJsonPath('data.excluded', 0)
            ->assertJsonPath('data.written', 5);

        $this->assertStringStartsWith("<feed>\n<item><id>", (string) $response->json('data.text'));
        $this->assertGreaterThanOrEqual(5, $response->json('data.looked_at'));
        $this->assertSame($feeds, Feed::query()->count());
    }

    #[Test]
    public function a_preview_says_why_a_named_product_stays_out(): void
    {
        $good = $this->shop->productOf('Knives');
        $hidden = Product::query()->visible()->orderByDesc('id')->firstOrFail();
        Product::query()->whereKey($hidden->id)->update(['is_published' => false]);
        $taken = $this->shop->productOf('Knives', 1);
        $bare = $this->shop->productOf('Knives', 2);
        $bare->images()->delete();

        $feed = Feed::query()->findOrFail($this->save(self::XML)->assertCreated()->json('data.id'));
        DB::table('catalog_feed_exclusions')->insert(['feed_id' => $feed->id, 'product_id' => $taken->id]);

        $response = $this->preview([...self::XML, 'feed' => $feed->id, 'product_ids' => [$good->id, $hidden->id, $taken->id, $bare->id, 99999999]])
            ->assertOk()
            ->assertJsonPath('data.written', 1)
            ->assertJsonPath('data.excluded', 1);

        $fates = array_column((array) $response->json('data.products'), 'reason', 'id');

        $this->assertSame([
            $good->id => null,
            $hidden->id => 'not_selected',
            $taken->id => 'excluded',
            $bare->id => 'no_image',
            99999999 => 'not_found',
        ], $fates);
        $this->assertStringContainsString('<id>'.$good->id.'</id>', (string) $response->json('data.text'));
        $this->assertStringNotContainsString('<id>'.$bare->id.'</id>', (string) $response->json('data.text'));
    }

    #[Test]
    public function a_preview_stays_within_its_limits_and_its_permission(): void
    {
        $this->preview([...self::XML, 'product_ids' => range(1, 21)])->assertUnprocessable()->assertJsonValidationErrors('product_ids');
        $this->postJson($this->api('preview'), ['name' => 'x'])->assertUnprocessable()->assertJsonValidationErrors(['format', 'item']);

        $this->actingAs($this->editor(['catalog.view']), 'cms');
        $this->preview(self::XML)->assertForbidden();
    }

    #[Test]
    public function a_feed_gone_bad_can_still_be_renamed_and_switched_off(): void
    {
        $feed = new Feed;
        $feed->forceFill(['name' => 'Old', 'format' => 'xml', 'locale' => 'en', 'currency' => 'EUR', 'item' => '<broken'])->save();

        $this->putJson($this->api((string) $feed->id), ['name' => 'New', 'is_active' => false, 'item' => '<broken'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.is_active', false);

        $this->putJson($this->api((string) $feed->id), ['item' => '<broken again'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('item');
    }

    #[Test]
    public function a_price_with_a_currency_needs_a_site_with_one(): void
    {
        config(['webx-catalog.price.currency' => null]);

        $words = 'The site has no currency: set WEBX_CATALOG_CURRENCY.';

        $this->refused(['format' => 'text', 'item' => '{{id}} {{price_with_currency}}'], 'currency', $words);
        $this->refused(['format' => 'text', 'item' => '{{id}}', 'head' => '{{feed.currency}}'], 'currency', $words);
        $this->save(['format' => 'text', 'item' => '{{id}} {{price}}'])->assertCreated()->assertJsonPath('data.currency', '');
    }

    #[Test]
    public function a_build_fails_after_the_site_changed_its_currency_until_the_feed_is_saved(): void
    {
        $values = ['format' => 'text', 'item' => '{{id}}'];
        $feed = Feed::query()->findOrFail($this->save($values)->assertCreated()->json('data.id'));
        $builder = $this->app->make(FeedBuilder::class);

        $first = $builder->start($feed->refresh());
        $this->assertInstanceOf(FeedBuild::class, $first);
        $this->assertSame(FeedBuild::DONE, $first->refresh()->status, (string) $first->error);

        config(['webx-catalog.price.currency' => 'USD']);

        $failed = $builder->start($feed->refresh());
        $this->assertInstanceOf(FeedBuild::class, $failed);
        $this->assertSame(FeedBuild::FAILED, $failed->refresh()->status);
        $this->assertSame('A feed is in the site’s currency: USD.', $failed->error);
        // The file served stays.
        $this->assertSame($first->id, $feed->refresh()->current_build_id);

        $this->putJson($this->api((string) $feed->id), ['name' => 'Saved again'])->assertOk()->assertJsonPath('data.currency', 'USD');

        $again = $builder->start($feed->refresh());
        $this->assertInstanceOf(FeedBuild::class, $again);
        $this->assertSame(FeedBuild::DONE, $again->refresh()->status, (string) $again->error);
    }

    #[Test]
    public function a_build_fails_on_a_site_that_lost_its_currency(): void
    {
        $feed = Feed::query()->findOrFail($this->save(['format' => 'text', 'item' => '{{id}}'])->assertCreated()->json('data.id'));

        config(['webx-catalog.price.currency' => null]);

        $build = $this->app->make(FeedBuilder::class)->start($feed->refresh());
        $this->assertInstanceOf(FeedBuild::class, $build);
        $this->assertSame(FeedBuild::FAILED, $build->refresh()->status);
        $this->assertSame('The site has no currency: set WEBX_CATALOG_CURRENCY.', $build->error);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return TestResponse<Response>
     */
    private function save(array $values): TestResponse
    {
        return $this->postJson($this->api(), ['name' => 'Spare parts', 'format' => 'text', ...$values]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return TestResponse<Response>
     */
    private function preview(array $values): TestResponse
    {
        return $this->postJson($this->api('preview'), ['name' => 'Spare parts', 'format' => 'text', 'item' => '{{id}}', ...$values]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function saved(array $values): Feed
    {
        return Feed::query()->findOrFail((int) $this->save($values)->assertCreated()->json('data.id'));
    }

    /** Cyrillic in what the products render to as well, as on a site that writes in it. */
    private function nameProducts(): void
    {
        foreach (Product::query()->visible()->orderBy('id')->limit(10)->get() as $product) {
            $product->setTranslation('name', 'en', 'Тормозные колодки')->save();
        }
    }

    private function firstProduct(): int
    {
        return (int) $this->preview(['format' => 'text', 'item' => '{{id}}'])->json('data.text');
    }

    /**
     * Refused under `$field` in those words, with nothing kept; the message, for what else it says.
     *
     * @param  array<string, mixed>  $values
     */
    private function refused(array $values, string $field, string $words): string
    {
        $feeds = Feed::query()->count();
        $response = $this->save($values)->assertUnprocessable()->assertJsonValidationErrors($field);
        $message = (string) $response->json('errors.'.$field.'.0');

        $this->assertStringContainsString($words, $message);
        $this->assertSame($feeds, Feed::query()->count());

        return $message;
    }
}
