<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Remote\HostResolver;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Admin\Testing\FakeHostResolver;
use WebxUi\Admin\Uploads\Uploads;
use WebxUi\Catalog\Models\Category;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Tests\Fixtures\Shop;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogFeeds\Build\FeedBuilder;
use WebxUi\CatalogFeeds\Build\FeedCategories;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\CatalogFeeds\Models\Taxonomy;
use WebxUi\CatalogFeeds\Models\TaxonomyItem;
use WebxUi\CatalogFeeds\Taxonomy\ImportTaxonomy;
use WebxUi\CatalogFeeds\Taxonomy\Taxonomies;
use WebxUi\CatalogFeeds\Taxonomy\TaxonomyImport;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogProperties\Catalog\ProductValues;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\CatalogProperties\Models\Property;
use WebxUi\CatalogStock\StockServiceProvider;

/**
 * The mappings of a feed (§3.3, §3.4, §6 of the feeds spec): what a marketplace's list holds, what a
 * category takes after, and where the five custom labels come from.
 */
final class MappingsTest extends TestCase
{
    use CountsQueries;

    private const LIST = "# Google_Product_Taxonomy_Version: 2021-09-21\n1 - Animals & Pet Supplies\n3237 - Animals & Pet Supplies > Live Animals\n";

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

        Shop::seedOnce($this->app, self::class);
        Storage::fake('local');
        $this->app->instance(HostResolver::class, new FakeHostResolver);
    }

    #[Test]
    public function a_list_comes_in_by_address_through_the_queue(): void
    {
        Http::fake(['www.google.com/*' => static fn () => Http::response(self::LIST)]);

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('taxonomies/google/import'), ['locale' => 'de'])
            ->assertStatus(202)
            ->assertJsonPath('taxonomy.locale', 'de-DE');

        Http::assertSent(static fn (ClientRequest $request): bool => $request->url() === 'https://www.google.com/basepages/producttype/taxonomy-with-ids.de-DE.txt');

        $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api('taxonomies/google/search?locale=de&q=live'))
            ->assertOk()
            ->assertJsonPath('data.0.external_id', '3237')
            ->assertJsonPath('data.0.path', 'Animals & Pet Supplies > Live Animals')
            ->assertJsonPath('taxonomy.version', '2021-09-21')
            ->assertJsonPath('taxonomy.items', 2)
            ->assertJsonPath('taxonomy.status', 'done')
            ->assertJsonPath('taxonomy.fallback', false);
    }

    #[Test]
    public function an_import_by_address_waits_in_the_queue_and_is_asked_for_once(): void
    {
        Bus::fake();

        $import = $this->app->make(TaxonomyImport::class);
        $import->queue('google', 'de');
        $import->queue('google', 'de');

        Bus::assertDispatchedTimes(ImportTaxonomy::class, 1);
    }

    #[Test]
    public function a_language_without_a_file_reads_the_english_list_and_says_so(): void
    {
        Http::fake([
            '*uk-UA.txt' => static fn () => Http::response('', 404),
            '*en-US.txt' => static fn () => Http::response(self::LIST),
        ]);

        $this->app->make(TaxonomyImport::class)->queue('google', 'uk');

        $described = $this->app->make(Taxonomies::class)->describe('google', 'uk');

        $this->assertTrue($described['fallback']);
        $this->assertSame('en-US', $described['list_locale']);
        $this->assertSame(2, $described['items']);
        $this->assertFalse(Taxonomy::query()->where('locale', 'uk-UA')->exists());
    }

    #[Test]
    public function an_address_a_person_gave_does_not_fall_back_and_says_what_went_wrong(): void
    {
        Http::fake(['lists.example.com/*' => static fn () => Http::response('', 404)]);

        $this->app->make(TaxonomyImport::class)->queue('google', 'de', 'https://lists.example.com/list.txt');

        $described = $this->app->make(Taxonomies::class)->describe('google', 'de');

        $this->assertSame('failed', $described['status']);
        $this->assertStringContainsString('404', (string) $described['error']);
        $this->assertFalse(Taxonomy::query()->where('locale', 'en-US')->exists());
    }

    #[Test]
    public function an_address_on_the_inside_is_not_fetched(): void
    {
        $this->app->instance(HostResolver::class, (new FakeHostResolver)->answer('intranet.example.com', ['10.0.0.7']));
        Http::fake();

        $this->app->make(TaxonomyImport::class)->queue('google', 'de', 'https://intranet.example.com/list.txt');

        Http::assertNothingSent();
        $this->assertSame('failed', Taxonomy::query()->where('locale', 'de-DE')->value('status'));
    }

    #[Test]
    public function an_address_has_to_be_https(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('taxonomies/google/import'), ['url' => 'http://lists.example.com/list.txt'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    #[Test]
    public function a_redirect_away_from_https_brings_nothing_in(): void
    {
        Http::fake([
            'https://lists.example.com/*' => static fn () => Http::response('', 302, ['Location' => 'http://lists.example.com/plain.txt']),
            'http://lists.example.com/*' => static fn () => Http::response(self::LIST),
        ]);

        $this->app->make(TaxonomyImport::class)->queue('google', 'de', 'https://lists.example.com/list.txt');

        $this->assertSame('failed', Taxonomy::query()->where('locale', 'de-DE')->value('status'));
        $this->assertSame(0, TaxonomyItem::query()->count());
    }

    #[Test]
    public function a_list_comes_in_as_a_file_and_the_upload_is_gone(): void
    {
        $user = $this->editor();

        $this->actingAs($user, 'cms')
            ->postJson($this->api('taxonomies/google/import'), ['locale' => 'en', 'upload' => $this->upload($user, self::LIST)])
            ->assertOk()
            ->assertJsonPath('taxonomy.items', 2)
            ->assertJsonPath('taxonomy.version', '2021-09-21')
            ->assertJsonPath('taxonomy.list_locale', 'en-US');

        $this->assertSame('taxonomy-with-ids.txt', Taxonomy::query()->value('source'));
    }

    #[Test]
    public function a_wrong_file_changes_nothing_and_names_its_line(): void
    {
        $user = $this->editor();

        $this->actingAs($user, 'cms')
            ->postJson($this->api('taxonomies/google/import'), ['locale' => 'en', 'upload' => $this->upload($user, self::LIST)])
            ->assertOk();

        $this->actingAs($user, 'cms')
            ->postJson($this->api('taxonomies/google/import'), ['locale' => 'en', 'upload' => $this->upload($user, "1 - Pets\n<html>\n")])
            ->assertStatus(422)
            ->assertJsonValidationErrors('upload')
            ->assertJsonPath('errors.upload.0', 'Line 2 of the file is not a category of the list.');

        $this->assertSame('Animals & Pet Supplies', TaxonomyItem::query()->where('external_id', '1')->value('path'));
    }

    #[Test]
    public function a_new_list_keeps_what_was_mapped_and_a_dropped_category_stays_as_a_value(): void
    {
        $user = $this->editor();
        $this->import($user, self::LIST);
        [$top, $child] = $this->branch();
        $feed = $this->feed();

        $this->app->make(FeedCategories::class)->set($feed, [
            ['category_id' => $top, 'taxonomy_item_id' => $this->item('1')],
            ['category_id' => $child, 'taxonomy_item_id' => $this->item('3237')],
        ]);

        $kept = $this->item('1');
        $this->import($user, "1 - Pets\n");

        $this->assertSame($kept, $this->item('1'));
        $this->assertSame('Pets', TaxonomyItem::query()->whereKey($kept)->value('path'));

        $map = $this->app->make(FeedCategories::class)->map($feed);

        $this->assertSame('1', $map->value($top));
        $this->assertSame('3237', $map->value($child));
    }

    #[Test]
    public function a_category_takes_after_its_nearest_ancestor_and_a_row_of_its_own_wins(): void
    {
        $this->import($this->editor(), self::LIST);
        [$top, $child, $leaf] = $this->branch();
        $feed = $this->feed();
        $categories = $this->app->make(FeedCategories::class);

        $categories->set($feed, [['category_id' => $top, 'taxonomy_item_id' => $this->item('1')]]);
        $map = $categories->map($feed);

        $this->assertSame('1', $map->value($leaf));
        $this->assertSame($top, $map->find($leaf)['category'] ?? null);

        $categories->set($feed, [['category_id' => $child, 'value' => 'Hand > Typed']]);
        $map = $categories->map($feed);

        $this->assertSame('Hand > Typed', $map->value($leaf));
        $this->assertSame('1', $map->value($top));

        $categories->set($feed, [['category_id' => $child]]);

        $this->assertSame('1', $categories->map($feed)->value($leaf));
    }

    #[Test]
    public function the_form_reads_the_tree_with_what_is_inherited_and_writes_it(): void
    {
        $user = $this->editor();
        $this->import($user, self::LIST);
        [$top, $child] = $this->branch();
        $feed = $this->feed(['taxonomy' => 'google']);

        $answer = $this->actingAs($user, 'cms')
            ->putJson($this->api($feed->id.'/categories'), ['categories' => [['category_id' => $top, 'taxonomy_item_id' => $this->item('3237')]]])
            ->assertOk()
            ->assertJsonPath('taxonomy.key', 'google')
            ->json();

        $rows = array_column($answer['data'], null, 'id');

        $this->assertSame('3237', $rows[$top]['value']);
        $this->assertSame('Animals & Pet Supplies > Live Animals', $rows[$top]['label']);
        $this->assertNull($rows[$child]['value']);
        $this->assertSame(['category_id' => $top, 'value' => '3237', 'label' => 'Animals & Pet Supplies > Live Animals'], $rows[$child]['inherited']);
        $this->assertSame(count(array_filter($answer['data'], static fn (array $row): bool => $row['value'] === null && $row['inherited'] === null)), $answer['unmapped']);
    }

    #[Test]
    public function a_row_is_a_category_of_the_list_or_a_value_and_not_both(): void
    {
        $user = $this->editor();
        $this->import($user, self::LIST);
        [$top] = $this->branch();

        $this->actingAs($user, 'cms')
            ->putJson($this->api($this->feed()->id.'/categories'), ['categories' => [['category_id' => $top, 'taxonomy_item_id' => $this->item('1'), 'value' => '5']]])
            ->assertStatus(422);
    }

    #[Test]
    public function the_file_gets_the_id_and_an_unmapped_category_is_counted_and_stays_in(): void
    {
        $this->import($this->editor(), self::LIST);

        $mapped = $this->productWithCategory();
        $feed = $this->feed(['item' => '{{id}}:{{google_product_category}}:{{platform_category}}']);

        $this->app->make(FeedCategories::class)->set($feed, [['category_id' => (int) $mapped->category_id, 'taxonomy_item_id' => $this->item('3237')]]);

        $build = $this->build($feed);
        $lines = explode("\n", $this->file($build));

        $this->assertContains($mapped->id.':3237:3237', $lines);
        $this->assertNotEmpty(array_filter($lines, static fn (string $line): bool => str_ends_with($line, '::')));
        $this->assertSame(count(array_filter($lines, static fn (string $line): bool => str_ends_with($line, '::'))), $build->warnings['unmapped_category'] ?? 0);
    }

    #[Test]
    public function a_page_with_mappings_and_labels_costs_the_same_whatever_its_size(): void
    {
        [$top] = $this->branch();

        $settings = [
            'item' => '{{id}}{{platform_category}}{{google_product_category}}{{custom_label_0}}{{custom_label_1}}{{custom_label_2}}',
            'custom_labels' => [
                0 => ['type' => 'label', 'labels' => ['sale', 'new']],
                1 => ['type' => 'price_range', 'ranges' => [['value' => 'any']]],
                2 => ['type' => 'property', 'property' => 'color'],
            ],
        ];

        $started = function (int $chunk) use ($settings, $top): array {
            $this->app['config']->set('webx-catalog-feeds.chunk', $chunk);
            Queue::fake();

            $feed = $this->feed($settings);
            $this->app->make(FeedCategories::class)->set($feed, [['category_id' => $top, 'value' => '1']]);

            $builder = $this->app->make(FeedBuilder::class);
            $build = $builder->start($feed);
            $this->assertInstanceOf(FeedBuild::class, $build);

            // The first page warms what is read once a job; the second is the one that repeats.
            $builder->work($build->id, 0);

            return [$builder, (int) $build->id];
        };

        $count = function (int $chunk) use ($started): int {
            [$builder, $id] = $started($chunk);

            return $this->recordQueries(static fn () => $builder->work($id, 0))->count();
        };

        $this->assertSame($count(5), $count(40));

        [$builder, $id] = $started(40);

        $this->assertNoRepeatedQueries(static fn () => $builder->work($id, 0), 'a page');
    }

    #[Test]
    public function a_template_that_does_not_print_the_category_warns_about_nothing(): void
    {
        $this->assertArrayNotHasKey('unmapped_category', $this->build($this->feed())->warnings ?? []);
    }

    #[Test]
    public function five_sources_give_their_values_in_the_file(): void
    {
        $product = $this->productWithCategory();
        $labelled = (int) DB::table('catalog_label_product')->value('product_id');
        $code = (string) DB::table('catalog_labels')->where('id', DB::table('catalog_label_product')->where('product_id', $labelled)->value('label_id'))->value('code');
        $price = (float) Product::query()->whereKey($product->id)->value('price');

        $feed = $this->feed([
            'item' => '{{id}}|{{custom_label_0}}|{{custom_label_1}}|{{custom_label_2}}|{{custom_label_3}}|{{custom_label_4}}',
            'custom_labels' => [
                0 => ['type' => 'label', 'labels' => ['nobody-has-this', $code]],
                1 => ['type' => 'price_range', 'ranges' => [['to' => $price - 1, 'value' => 'low'], ['to' => $price, 'value' => 'exact'], ['value' => 'high']]],
                2 => ['type' => 'property', 'property' => 'contents'],
                3 => ['type' => 'constant', 'value' => 'autumn-campaign'],
                4 => ['type' => 'none'],
            ],
        ]);

        $rows = [];

        foreach (explode("\n", $this->file($this->build($feed))) as $line) {
            $cells = explode('|', $line);
            $rows[(int) $cells[0]] = $cells;
        }

        $this->assertSame($code, $rows[$labelled][1]);
        $this->assertSame('exact', $rows[$product->id][2]);
        $this->assertSame('autumn-campaign', $rows[$product->id][4]);
        $this->assertSame('', $rows[$product->id][5]);
        $this->assertNotEmpty(array_filter($rows, static fn (array $row): bool => $row[3] !== ''));
        $this->assertNotEmpty(array_filter($rows, static fn (array $row): bool => $row[1] === ''));
    }

    #[Test]
    public function a_property_of_several_values_gives_the_first_whole(): void
    {
        $property = (int) Property::query()->get()->first(static fn (Property $one): bool => $one->codeIn('en') === 'color')?->id;
        $product = (int) DB::table('catalog_product_property_values')
            ->where('property_id', $property)
            ->groupBy('product_id')
            ->havingRaw('count(*) > 1')
            ->value('product_id');

        $first = $this->app->make(ProductValues::class)->read([$product])[$product][$property][0];
        DB::table('catalog_property_values')->where('id', $first)->update(['title' => json_encode(['en' => 'Black; matte'])]);

        $feed = $this->feed([
            'item' => '{{id}}|{{custom_label_0}}|{{property.color}}',
            'custom_labels' => [0 => ['type' => 'property', 'property' => 'color']],
        ]);

        $line = collect(explode("\n", $this->file($this->build($feed))))->first(static fn (string $text): bool => str_starts_with($text, $product.'|'));

        $this->assertStringStartsWith($product.'|Black; matte|Black; matte;', (string) $line);
    }

    #[Test]
    public function a_value_is_cut_to_what_google_takes(): void
    {
        $feed = $this->feed([
            'item' => '{{custom_label_3}}',
            'custom_labels' => [3 => ['type' => 'constant', 'value' => str_repeat('a', 120)]],
        ]);

        $this->assertSame(str_repeat('a', 100), explode("\n", $this->file($this->build($feed)))[0]);
    }

    #[Test]
    public function too_many_values_of_a_label_warn_once_a_label_across_the_pages(): void
    {
        $this->app['config']->set('webx-catalog-feeds.max_label_values', 1);
        $this->app['config']->set('webx-catalog-feeds.chunk', 2);

        // The first products cost the same as each other or not: two values appear either way.
        $cheapest = (float) Product::query()->visible()->where('price', '>', 0)->min('price');

        $build = $this->build($this->feed([
            'item' => '{{custom_label_0}}{{custom_label_1}}',
            'custom_labels' => [
                0 => ['type' => 'price_range', 'ranges' => [['to' => $cheapest, 'value' => 'low'], ['value' => 'high']]],
                1 => ['type' => 'constant', 'value' => 'same'],
            ],
        ]));

        $this->assertSame(1, $build->warnings['label_values'] ?? 0);
    }

    #[Test]
    public function a_label_within_the_limit_warns_about_nothing(): void
    {
        $build = $this->build($this->feed([
            'item' => '{{custom_label_0}}',
            'custom_labels' => [0 => ['type' => 'constant', 'value' => 'same']],
        ]));

        $this->assertArrayNotHasKey('label_values', $build->warnings ?? []);
    }

    #[Test]
    public function a_label_the_template_does_not_print_cannot_fail_a_build(): void
    {
        $build = $this->build($this->feed(['custom_labels' => [0 => ['type' => 'property', 'property' => 'gone']]]));

        $this->assertSame(FeedBuild::DONE, $build->status);
    }

    #[Test]
    public function a_property_the_site_lost_is_said_under_the_labels(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), [
                'name' => 'Spare parts',
                'format' => 'text',
                'item' => '{{custom_label_0}}',
                'custom_labels' => ['0' => ['type' => 'property', 'property' => 'gone']],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('custom_labels');
    }

    /**
     * @param  array<array-key, mixed>  $labels
     */
    #[Test]
    #[DataProvider('wrongLabels')]
    public function a_source_that_cannot_work_is_refused(array $labels): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), [...$this->fields(), 'custom_labels' => $labels])
            ->assertStatus(422)
            ->assertJsonValidationErrors('custom_labels');
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function wrongLabels(): iterable
    {
        yield 'a sixth label' => [['5' => ['type' => 'none']]];
        yield 'a name for a key' => [['first' => ['type' => 'none']]];
        yield 'an unknown type' => [['0' => ['type' => 'weather']]];
        yield 'no labels' => [['0' => ['type' => 'label', 'labels' => []]]];
        yield 'no ranges' => [['0' => ['type' => 'price_range', 'ranges' => []]]];
        yield 'an open range in the middle' => [['0' => ['type' => 'price_range', 'ranges' => [['value' => 'a'], ['to' => 5, 'value' => 'b']]]]];
        yield 'ranges going down' => [['0' => ['type' => 'price_range', 'ranges' => [['to' => 5, 'value' => 'a'], ['to' => 1, 'value' => 'b']]]]];
        yield 'a range with no value' => [['0' => ['type' => 'price_range', 'ranges' => [['to' => 5]]]]];
        yield 'no property' => [['0' => ['type' => 'property']]];
        yield 'no constant' => [['0' => ['type' => 'constant', 'value' => '']]];
    }

    #[Test]
    public function the_source_of_an_mpn_is_a_field_or_a_property(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), [...$this->fields(), 'sources' => ['mpn' => ['type' => 'weather']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sources');

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), [...$this->fields(), 'sources' => ['mpn' => ['type' => 'property']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sources');
    }

    #[Test]
    public function a_property_gives_a_feed_its_mpn(): void
    {
        $feed = $this->feed([
            'item' => '{{mpn}}',
            'sources' => ['mpn' => ['type' => 'property', 'property' => 'contents']],
        ]);

        $this->assertStringContainsString('The item, a manual, a box', $this->file($this->build($feed)));
    }

    #[Test]
    public function a_status_is_what_the_map_says_and_without_a_row_what_it_always_was(): void
    {
        $rows = DB::table('catalog_product_stock')
            ->join('catalog_stock_statuses', 'catalog_stock_statuses.id', '=', 'status_id')
            ->pluck('catalog_stock_statuses.code', 'product_id')
            ->all();

        $feed = $this->feed(['item' => '{{id}}:{{availability}}', 'stock_map' => ['on-order' => 'backorder']]);
        $lines = explode("\n", $this->file($this->build($feed)));

        foreach ($lines as $line) {
            [$id, $availability] = explode(':', $line);

            if (($rows[(int) $id] ?? null) === 'on-order') {
                $this->assertSame('backorder', $availability);
            }
        }

        $this->assertNotEmpty(array_filter($lines, static fn (string $line): bool => str_ends_with($line, ':in_stock')));
    }

    #[Test]
    public function a_value_of_the_stock_map_is_a_word(): void
    {
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), [...$this->fields(), 'stock_map' => ['in-stock' => ['nested']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('stock_map.in-stock');
    }

    #[Test]
    public function preorder_saves_with_a_warning(): void
    {
        $answer = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['name' => 'Spare parts', 'format' => 'text', 'item' => '{{id}}', 'stock_map' => ['on-order' => 'preorder']])
            ->assertCreated()
            ->json('warnings');

        $preorder = array_column($answer, null, 'code')['preorder'];

        $this->assertSame(1, $preorder['count']);
        $this->assertStringContainsString('availability_date', (string) $preorder['message']);
    }

    #[Test]
    public function backorder_asks_for_the_same_date_and_counts_with_preorder(): void
    {
        $answer = $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api(), ['name' => 'Spare parts', 'format' => 'text', 'item' => '{{id}}', 'stock_map' => ['on-order' => 'backorder', 'soon' => 'preorder', 'in-stock' => 'in_stock']])
            ->assertCreated()
            ->json('warnings');

        $this->assertSame(2, array_column($answer, null, 'code')['preorder']['count']);
    }

    #[Test]
    public function a_reader_sees_the_mappings_and_does_not_write_them(): void
    {
        [$top] = $this->branch();
        $feed = $this->feed();
        $reader = $this->editor(['catalog.view']);

        $this->actingAs($reader, 'cms')->getJson($this->api($feed->id.'/categories'))->assertOk();
        $this->actingAs($reader, 'cms')->getJson($this->api('taxonomies/google/search'))->assertOk();
        $this->actingAs($reader, 'cms')->putJson($this->api($feed->id.'/categories'), ['categories' => [['category_id' => $top, 'value' => '1']]])->assertForbidden();
        $this->actingAs($reader, 'cms')->postJson($this->api('taxonomies/google/import'))->assertForbidden();
    }

    #[Test]
    public function a_list_nobody_knows_has_no_address(): void
    {
        $this->actingAs($this->editor(), 'cms')->getJson($this->api('taxonomies/weather/search'))->assertNotFound();
    }

    private function productWithCategory(): Product
    {
        return Product::query()->visible()->whereNotNull('category_id')->orderBy('id')->firstOrFail();
    }

    /**
     * Three categories, each under the one before.
     *
     * @return array{int, int, int}
     */
    private function branch(): array
    {
        $top = new Category(['name' => 'Top', 'slug' => 'mapped-top', 'is_published' => true]);
        $top->saveAsRoot();

        $child = new Category(['name' => 'Child', 'slug' => 'mapped-child', 'is_published' => true]);
        $child->appendTo($top->refresh());

        $leaf = new Category(['name' => 'Leaf', 'slug' => 'mapped-leaf', 'is_published' => true]);
        $leaf->appendTo($child->refresh());

        return [(int) $top->id, (int) $child->id, (int) $leaf->id];
    }

    private function item(string $external): int
    {
        return (int) TaxonomyItem::query()->where('external_id', $external)->value('id');
    }

    private function import(mixed $user, string $text): void
    {
        $this->actingAs($user, 'cms')
            ->postJson($this->api('taxonomies/google/import'), ['locale' => 'en', 'upload' => $this->upload($user, $text)])
            ->assertOk();
    }

    /** A whole file through the sessions a browser would use: the id of what it left. */
    private function upload(mixed $user, string $text): string
    {
        $uploads = $this->app->make(Uploads::class);
        [$upload] = $uploads->start($user, TaxonomyImport::UPLOAD_PURPOSE, 'taxonomy-with-ids.txt', strlen($text), 'text/plain', md5($text.microtime()));

        $stream = fopen('php://memory', 'r+b');
        fwrite($stream, $text);
        rewind($stream);
        $uploads->append($upload, 0, $stream);

        return (string) $upload->id;
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

    /**
     * @param  array<string, mixed>  $settings
     */
    private function feed(array $settings = []): Feed
    {
        $feed = new Feed;
        $feed->forceFill([
            'name' => 'Spare parts',
            'format' => 'text',
            'locale' => 'en',
            'currency' => 'EUR',
            'item' => '{{id}}',
            ...$settings,
        ])->save();

        return $feed->refresh();
    }
}
