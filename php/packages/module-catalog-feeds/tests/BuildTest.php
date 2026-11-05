<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Catalog\Facets\Facets;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Models\ProductImage;
use WebxUi\Catalog\Tests\Fixtures\Shop;
use WebxUi\Catalog\Variables\ComputedVariable;
use WebxUi\Catalog\Variables\ProductVariables;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogFeeds\Build\BuildFeed;
use WebxUi\CatalogFeeds\Build\FeedBuilder;
use WebxUi\CatalogFeeds\Build\FeedSelection;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogProperties\Catalog\ProductValues;
use WebxUi\CatalogProperties\Catalog\Properties;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\CatalogStock\StockServiceProvider;
use WebxUi\Templates\Variable;

/**
 * A build over a shop with every satellite (§5, §7.1, §7.2, §7.6 of the feeds spec): what goes in,
 * what stays out and why, a page repeated, the file checked before anybody is given it.
 */
final class BuildTest extends TestCase
{
    use CountsQueries;

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
        // What a queue worker has: no request, so addresses start at `app.url`.
        $this->app->make('url')->forceRootUrl('https://example.test');
        $this->app->make('url')->forceScheme('https');
    }

    #[Test]
    public function the_head_is_written_last_and_knows_what_the_pages_found(): void
    {
        $feed = $this->feed([
            'format' => 'xml',
            'head' => '<feed count="{{products_count}}" currency="{{feed.currency}}">{{#categories}}<c id="{{id}}" parent="{{parent_id}}">{{path}}</c>{{/categories}}',
            'item' => '<item><id>{{id}}</id><link>{{url}}</link><title>{{title}}</title></item>',
            'foot' => '</feed>',
            'filter' => ['categories' => ['include' => [$this->shop->category('Knives')->id]]],
        ]);

        $build = $this->build($feed);
        $file = $this->file($build);
        $products = Product::query()->visible()->inCategories($this->branch('Knives'))->count();

        $this->assertSame(FeedBuild::DONE, $build->status);
        $this->assertSame($products, $build->written);
        $this->assertSame($products, $build->selected);
        $this->assertStringStartsWith('<feed count="'.$products.'" currency="EUR">', $file);
        $this->assertStringContainsString('>Home &gt; Kitchen &gt; Knives</c>', $file);
        // An ancestor is there, or `parent_id` would lead nowhere.
        $this->assertStringContainsString('<c id="'.$this->shop->category('Home')->id.'" parent="">Home</c>', $file);
        $this->assertStringContainsString('<link>https://example.test/', $file);
        $this->assertStringEndsWith('</feed>', $file);
        $this->assertSame($build->id, $feed->refresh()->current_build_id);
        $this->assertSame(hash('sha256', $file), $build->etag);
        $this->assertSame(strlen($file), $build->bytes);
        $this->assertSame('catalog-feeds/'.$feed->id.'/'.$build->id.'.xml', $build->path);
    }

    #[Test]
    public function a_branch_is_taken_out_with_what_is_under_it(): void
    {
        $feed = $this->feed(['filter' => ['categories' => [
            'include' => [$this->shop->category('Home')->id],
            'exclude' => [$this->shop->category('Kitchen')->id],
        ]]]);

        $ids = $this->ids($feed);
        $textiles = Product::query()->visible()->where('category_id', $this->shop->category('Textiles')->id)->pluck('id')->all();

        $this->assertNotSame([], $textiles);
        $this->assertEmpty(array_diff($textiles, $ids));
        $this->assertNotContains($this->shop->productOf('Knives')->id, $ids);
        $this->assertNotContains($this->shop->productOf('Cookware')->id, $ids);
    }

    #[Test]
    public function the_filter_speaks_through_the_facets_of_the_catalogue(): void
    {
        $brand = $this->shop->brands['northwind'];
        $cheap = $this->feed(['filter' => ['price' => ['min' => 10, 'max' => 40]]]);
        $branded = $this->feed(['filter' => ['brands' => [$brand->id]]]);
        $labelled = $this->feed(['filter' => ['labels' => ['sale']]]);

        $this->assertEqualsCanonicalizing(
            Product::query()->visible()->whereBetween('price', [10, 40])->pluck('id')->all(),
            $this->ids($cheap),
        );
        $this->assertEqualsCanonicalizing(
            DB::table('catalog_product_brand')->where('brand_id', $brand->id)->pluck('product_id')->all(),
            $this->ids($branded),
        );
        $this->assertEqualsCanonicalizing(
            DB::table('catalog_label_product')->join('catalog_labels', 'catalog_labels.id', '=', 'label_id')->where('code', 'sale')->pluck('product_id')->all(),
            $this->ids($labelled),
        );
    }

    #[Test]
    public function a_property_outside_the_filter_selects_all_the_same(): void
    {
        $color = $this->shop->properties['color'];
        $color->update(['is_filterable' => false]);
        $this->app->make(Properties::class)->flush();
        $facets = $this->app->make(Facets::class);
        $facets->flush();
        $black = $this->shop->values['color']['Black'];

        // The storefront does not know it; the feed does.
        $this->assertNull($facets->find($color->facetKey()));

        $feed = $this->feed(['filter' => ['properties' => [$color->facetKey() => [(string) $black->id]]]]);
        $expected = DB::table(ProductValues::TABLE)->where('value_id', $black->id)->pluck('product_id')->unique()->all();

        $this->assertNotSame([], $expected);
        $this->assertEqualsCanonicalizing($expected, $this->ids($feed));
    }

    #[Test]
    public function a_text_property_matches_the_whole_value_in_the_language_of_the_feed(): void
    {
        $contents = $this->shop->properties['contents'];
        $key = $contents->facetKey();
        $having = DB::table(ProductValues::TABLE)->where('property_id', $contents->id)->pluck('product_id')->all();

        $this->assertNotSame([], $having);
        $this->assertEqualsCanonicalizing($having, $this->ids($this->feed(['filter' => ['properties' => [$key => ['The item, a manual, a box']]]])));
        $this->assertSame([], $this->ids($this->feed(['filter' => ['properties' => [$key => ['The item']]]])));
        $this->assertSame([], $this->ids($this->feed(['locale' => 'de', 'filter' => ['properties' => [$key => ['The item, a manual, a box']]]])));
    }

    #[Test]
    public function a_filter_nobody_can_answer_keeps_the_file_of_yesterday(): void
    {
        $feed = $this->feed();
        $good = $this->build($feed);

        $feed->forceFill(['filter' => ['properties' => ['p.99999' => ['1']]]])->save();
        $bad = $this->build($feed);

        $this->assertSame(FeedBuild::FAILED, $bad->status);
        $this->assertStringContainsString('p.99999', (string) $bad->error);
        $this->assertSame($good->id, $feed->refresh()->current_build_id);
    }

    #[Test]
    public function a_label_nobody_has_selects_nothing_rather_than_everything(): void
    {
        $this->assertSame([], $this->ids($this->feed(['filter' => ['labels' => ['no-such-label']]])));
    }

    #[Test]
    public function an_exclusion_is_of_one_feed_and_is_counted_without_a_reason(): void
    {
        $product = $this->shop->products[0];
        $feed = $this->feed();
        $other = $this->feed();
        DB::table(FeedSelection::EXCLUSIONS)->insert(['feed_id' => $feed->id, 'product_id' => $product->id]);

        $build = $this->build($feed);

        $this->assertNotContains($product->id, $this->idsOf($build));
        $this->assertContains($product->id, $this->ids($other));
        $this->assertSame(1, $build->excluded);
        $this->assertSame([], $build->rejected ?? []);
    }

    #[Test]
    public function a_product_with_nothing_to_show_stays_out_and_the_journal_says_why(): void
    {
        [$free, $priceless, $faceless, $videoOnly, $foreign] = array_slice($this->shop->products, 0, 5);

        Product::query()->whereKey($free->id)->update(['price' => 0]);
        Product::query()->whereKey($priceless->id)->update(['price' => null]);
        ProductImage::query()->where('product_id', $faceless->id)->delete();
        ProductImage::query()->where('product_id', $videoOnly->id)->update(['video_provider' => 'youtube', 'video' => 'abc']);
        Product::query()->whereKey($foreign->id)->update(['name' => json_encode(['de' => 'Nur Deutsch'])]);

        $build = $this->build($this->feed());

        $this->assertSame(['no_price' => 2, 'no_image' => 2, 'no_name' => 1], $build->rejected);
        $this->assertSame(Shop::PRODUCTS, $build->selected);
        $this->assertSame(Shop::PRODUCTS - 5, $build->written);
        $this->assertEqualsCanonicalizing(
            [[$free->id, 'no_price'], [$priceless->id, 'no_price'], [$faceless->id, 'no_image'], [$videoOnly->id, 'no_image'], [$foreign->id, 'no_name']],
            DB::table('catalog_feed_build_rejects')->where('build_id', $build->id)->get()->map(static fn (object $row): array => [$row->product_id, $row->reason])->all(),
        );
    }

    #[Test]
    public function the_journal_names_only_so_many_products_and_counts_them_all(): void
    {
        $this->app['config']->set('webx-catalog-feeds.max_rejects', 3);
        $this->app['config']->set('webx-catalog-feeds.chunk', 2);
        Product::query()->whereKey(array_map(static fn (Product $product): int => $product->id, array_slice($this->shop->products, 0, 7)))->update(['price' => null]);

        $build = $this->build($this->feed());

        $this->assertSame(['no_price' => 7], $build->rejected);
        $this->assertSame(3, DB::table('catalog_feed_build_rejects')->where('build_id', $build->id)->count());
    }

    #[Test]
    public function only_in_stock_filters_and_is_not_a_reason(): void
    {
        $build = $this->build($this->feed(['filter' => ['only_in_stock' => true]]));
        $unsold = DB::table('catalog_product_stock')
            ->join('catalog_stock_statuses', 'catalog_stock_statuses.id', '=', 'status_id')
            ->where('is_purchasable', false)
            ->count();

        $this->assertGreaterThan(0, $unsold);
        $this->assertSame($unsold, $build->filtered);
        $this->assertSame(Shop::PRODUCTS - $unsold, $build->written);
        $this->assertSame([], $build->rejected ?? []);
    }

    #[Test]
    public function a_page_that_died_is_done_again_and_nothing_is_written_twice(): void
    {
        $this->app['config']->set('webx-catalog-feeds.chunk', 10);
        Queue::fake();
        Product::query()->whereKey($this->shop->products[12]->id)->update(['price' => null]);
        $fuse = (object) ['pages' => 0];

        $this->app->make(ProductVariables::class)->register(new ComputedVariable(
            new Variable('fuse', 'fuse'),
            static function (Collection $products) use ($fuse): array {
                if (++$fuse->pages === 3) {
                    throw new RuntimeException('The database went away.');
                }

                return [];
            },
        ));

        $feed = $this->feed(['item' => '{{id}}{{fuse}}', 'format' => 'text']);
        $builder = $this->app->make(FeedBuilder::class);
        $build = $builder->start($feed);
        $this->assertInstanceOf(FeedBuild::class, $build);

        $died = false;

        try {
            $builder->work($build->id, 3600);
        } catch (RuntimeException) {
            $died = true;
        }

        $this->assertTrue($died);
        $build->refresh();
        $this->assertSame(20 - 1, $build->written);
        $this->assertSame(['no_price' => 1], $build->rejected);

        // As the page died: the bytes of a half-written page are in the body, past what is recorded.
        file_put_contents(storage_path('app/catalog-feeds/'.$build->id.'.body'), "\n9999999", FILE_APPEND);

        while ($builder->work($build->id, 3600)) {
            // The pages left, then the end of the build.
        }

        $build->refresh();

        $this->assertSame(FeedBuild::DONE, $build->status);
        $this->assertSame(Shop::PRODUCTS - 1, $build->written);
        $this->assertSame(Shop::PRODUCTS, $build->selected);
        $this->assertSame(['no_price' => 1], $build->rejected);
        $this->assertSame(1, DB::table('catalog_feed_build_rejects')->where('build_id', $build->id)->count());

        $lines = explode("\n", $this->file($build));
        $this->assertCount(Shop::PRODUCTS - 1, $lines);
        $this->assertSame($lines, array_values(array_unique($lines)));
        $this->assertNotContains('9999999', $lines);
    }

    #[Test]
    public function a_job_leaves_what_it_has_no_time_for_to_the_next_one(): void
    {
        $this->app['config']->set('webx-catalog-feeds.chunk', 50);
        Queue::fake();

        $builder = $this->app->make(FeedBuilder::class);
        $build = $builder->start($this->feed());
        $this->assertInstanceOf(FeedBuild::class, $build);
        Queue::assertPushed(BuildFeed::class, 1);

        $jobs = 0;

        while ($builder->work($build->id, 0)) {
            $jobs++;
        }

        // Three pages, and the join and the check each in a job of its own; publishing ends it.
        $this->assertSame(5, $jobs);
        $this->assertSame(Shop::PRODUCTS, $build->refresh()->written);
        $this->assertSame(FeedBuild::DONE, $build->status);
    }

    #[Test]
    public function a_job_outlives_its_budget_and_not_the_retry_of_the_queue(): void
    {
        $job = new BuildFeed(1);
        $site = dirname(__DIR__, 3).'/site';
        $queue = require $site.'/config/queue.php';

        $this->assertSame(60, $job->budget(), 'The budget of the spec.');
        $this->assertGreaterThanOrEqual($job->budget() + 15, $job->timeout, 'A page ends and the next job is queued.');

        foreach (['database', 'redis', 'beanstalkd'] as $connection) {
            $this->assertLessThan($queue['connections'][$connection]['retry_after'], $job->timeout, $connection);
        }

        // The job's own timeout is what counts: a worker of the skeleton sets none, and may not lower it.
        $this->assertStringNotContainsString('--timeout', (string) file_get_contents($site.'/docker/supervisord.conf'));

        $this->app['config']->set('webx-catalog-feeds.job_seconds', 600);
        $this->assertSame(60, $job->budget(), 'A site cannot lift the budget over the timeout.');
    }

    #[Test]
    public function the_end_of_a_build_is_repeated_step_by_step_without_a_trace(): void
    {
        $feed = $this->feed(['format' => 'csv', 'item' => '{{sku}},{{title}}', 'separator' => "\n"]);
        $whole = $this->file($this->build($feed));

        Queue::fake();
        $builder = $this->app->make(FeedBuilder::class);
        $build = $builder->start($feed->refresh());
        $this->assertInstanceOf(FeedBuild::class, $build);

        // No time at all, so the check takes a row a job; each step dies once after its work is done.
        $jobs = 0;
        $repeated = [];

        do {
            $before = $build->refresh()->state;
            $step = $before['step'] ?? 'pages';
            $more = $builder->work($build->id, 0);
            $jobs++;

            if ($more && $step !== 'pages' && ! isset($repeated[$step])) {
                $repeated[$step] = true;
                $build->refresh()->forceFill(['state' => $before])->save();
            }
        } while ($more);

        $this->assertSame(['join', 'check'], array_keys($repeated));
        $this->assertGreaterThan(Shop::PRODUCTS, $jobs);
        $this->assertSame(FeedBuild::DONE, $build->refresh()->status);
        $this->assertSame($whole, $this->file($build));
        $this->assertSame([], glob(storage_path('app/catalog-feeds/'.$build->id.'.*')) ?: []);
    }

    #[Test]
    public function a_broken_row_is_found_after_a_check_was_put_down(): void
    {
        $feed = $this->feed(['format' => 'csv', 'head' => "a,b\n", 'item' => '{{sku}},{{title}}', 'separator' => "\n", 'foot' => "\none,two,three"]);

        Queue::fake();
        $builder = $this->app->make(FeedBuilder::class);
        $build = $builder->start($feed->refresh());
        $this->assertInstanceOf(FeedBuild::class, $build);

        while ($builder->work($build->id, 0)) {
            // A row a job, up to the last.
        }

        $this->assertSame(FeedBuild::FAILED, $build->refresh()->status);
        $this->assertStringContainsString((string) ($build->written + 2), (string) $build->error);
        $this->assertSame([], glob(storage_path('app/catalog-feeds/'.$build->id.'.*')) ?: []);
    }

    #[Test]
    public function a_page_costs_the_same_whatever_its_size_and_nothing_repeats(): void
    {
        $item = '<item>{{id}}{{url}}{{title}}{{description_text}}{{brand}}{{availability}}{{price_with_currency}}{{image_link}}'
            .'{{gtin}}{{mpn}}{{product_type}}{{property.color}}{{#labels}}{{code}}{{/labels}}{{#additional_images}}{{url}}{{/additional_images}}</item>';

        $count = function (int $chunk) use ($item): int {
            $this->app['config']->set('webx-catalog-feeds.chunk', $chunk);
            Queue::fake();
            $builder = $this->app->make(FeedBuilder::class);
            $build = $builder->start($this->feed(['head' => '<feed>', 'item' => $item, 'foot' => '</feed>']));
            $this->assertInstanceOf(FeedBuild::class, $build);

            // The first page warms what is read once a job; the second is the one that repeats.
            $builder->work($build->id, 0);

            return $this->recordQueries(static fn () => $builder->work($build->id, 0))->count();
        };

        $this->assertSame($count(5), $count(40));

        $this->app['config']->set('webx-catalog-feeds.chunk', 40);
        $builder = $this->app->make(FeedBuilder::class);
        $build = $builder->start($this->feed(['head' => '<feed>', 'item' => $item, 'foot' => '</feed>']));
        $this->assertInstanceOf(FeedBuild::class, $build);
        $builder->work($build->id, 0);

        $this->assertNoRepeatedQueries(static fn () => $builder->work($build->id, 0), 'a page');
    }

    #[Test]
    public function a_template_pays_only_for_what_it_uses(): void
    {
        $this->app['config']->set('webx-catalog-feeds.chunk', 500);
        Queue::fake();
        $builder = $this->app->make(FeedBuilder::class);
        $build = $builder->start($this->feed(['item' => '{{id}}', 'format' => 'text']));
        $this->assertInstanceOf(FeedBuild::class, $build);

        $tally = $this->recordQueries(static fn () => $builder->work($build->id, 3600));

        $this->assertStringNotContainsString(ProductValues::TABLE, $tally->describe());
        $this->assertStringNotContainsString('catalog_brands', $tally->describe());
    }

    #[Test]
    public function the_journal_warns_about_what_the_feed_prints_and_nothing_else(): void
    {
        $first = $this->shop->products[0];
        Product::query()->whereKey($first->id)->update(['barcode' => '123', 'description' => null, 'summary' => null, 'name' => str_repeat('word ', 40)]);
        ProductImage::query()->where('product_id', $this->shop->products[1]->id)->update(['width' => 200, 'height' => 200]);

        $build = $this->build($this->feed(['item' => '{{title}}{{gtin}}{{long_text}}{{image_link}}', 'format' => 'text']));

        $warnings = $build->warnings ?? [];
        $this->assertSame(1, $warnings['invalid_gtin']);
        $this->assertSame(1, $warnings['no_description']);
        $this->assertSame(1, $warnings['small_image']);
        $this->assertSame(1, $warnings['text_cut']);
        $this->assertArrayNotHasKey('no_brand', $warnings);

        $quiet = $this->build($this->feed(['item' => '{{id}}', 'format' => 'text']));
        $this->assertSame([], $quiet->warnings ?? []);
    }

    #[Test]
    public function a_broken_file_is_never_served(): void
    {
        $good = $this->feed();
        $served = $this->build($good);
        $path = (string) $served->path;

        $cases = [
            'xml' => ['format' => 'xml', 'head' => '<feed>', 'item' => '<item>{{id}}', 'foot' => '</feed>'],
            'json item' => ['format' => 'json', 'head' => '[', 'item' => '{"id":{{id}}', 'foot' => ']'],
            'json frame' => ['format' => 'json', 'head' => '[', 'item' => '{"id":"{{id}}"}', 'foot' => '}'],
            'json separator' => ['format' => 'json', 'head' => '[', 'item' => '{"id":"{{id}}"}', 'foot' => ']', 'separator' => ';'],
        ];

        foreach ($cases as $name => $settings) {
            $good->forceFill($settings)->save();
            $build = $this->build($good);

            $this->assertSame(FeedBuild::FAILED, $build->status, $name);
            $this->assertNotNull($build->error, $name);
            $this->assertNull($build->path, $name);
            $this->assertSame($served->id, $good->refresh()->current_build_id, $name);
        }

        Storage::disk('local')->assertExists($path);
    }

    #[Test]
    public function a_row_of_a_csv_with_another_number_of_fields_fails_with_its_line(): void
    {
        $feed = $this->feed(['format' => 'csv', 'head' => "id,name\n", 'item' => '{{id}},{{title}}{{#old_price}},{{old_price}}{{/old_price}}', 'foot' => '']);

        $build = $this->build($feed);

        $this->assertSame(FeedBuild::FAILED, $build->status);
        $this->assertStringContainsString('2', (string) $build->error);
    }

    #[Test]
    public function a_value_with_a_delimiter_in_it_is_one_field_of_a_csv(): void
    {
        Product::query()->whereKey($this->shop->products[0]->id)->update(['name' => 'Bolt; nut, "washer"']);

        $feed = $this->feed(['format' => 'csv', 'head' => "id;name\n", 'item' => '{{id}};{{title}}', 'options' => ['delimiter' => ';']]);
        $build = $this->build($feed);

        $this->assertSame(FeedBuild::DONE, $build->status, (string) $build->error);
        $this->assertStringContainsString('"Bolt; nut, ""washer"""', $this->file($build));
    }

    #[Test]
    public function a_json_file_of_products_reads_back(): void
    {
        $feed = $this->feed(['format' => 'json', 'separator' => ',', 'head' => '{"items":[', 'item' => '{"id":"{{id}}","title":"{{title}}"}', 'foot' => '],"total":"{{products_count}}"}']);
        Product::query()->whereKey($this->shop->products[0]->id)->update(['name' => 'A "quoted" name']);

        $build = $this->build($feed);
        $decoded = json_decode($this->file($build), true);

        $this->assertSame(FeedBuild::DONE, $build->status, (string) $build->error);
        $this->assertIsArray($decoded);
        $this->assertCount(Shop::PRODUCTS, $decoded['items']);
        $this->assertSame((string) Shop::PRODUCTS, $decoded['total']);
        $this->assertSame('A "quoted" name', $decoded['items'][0]['title']);
    }

    #[Test]
    public function a_feed_has_one_build_at_a_time_and_a_silent_one_is_closed(): void
    {
        Queue::fake();
        $builder = $this->app->make(FeedBuilder::class);
        $feed = $this->feed();

        $first = $builder->start($feed);
        $this->assertInstanceOf(FeedBuild::class, $first);
        $this->assertSame($first->id, $builder->start($feed)?->id);
        Queue::assertPushed(BuildFeed::class, 1);

        Carbon::setTestNow(Carbon::now()->addMinutes(31));
        $second = $builder->start($feed, FeedBuild::SCHEDULE);
        Carbon::setTestNow();

        $this->assertInstanceOf(FeedBuild::class, $second);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(FeedBuild::SCHEDULE, $second->trigger);
        $this->assertSame(FeedBuild::FAILED, $first->refresh()->status);
        Queue::assertPushed(BuildFeed::class, 2);
    }

    #[Test]
    public function a_feed_switched_off_is_not_built(): void
    {
        $this->assertNull($this->app->make(FeedBuilder::class)->start($this->feed(['is_active' => false])));
    }

    #[Test]
    public function the_same_content_keeps_its_date(): void
    {
        $feed = $this->feed();
        $first = $this->build($feed);

        Carbon::setTestNow(Carbon::now()->addHour());
        $second = $this->build($feed);

        $feed->forceFill(['item' => '<other>{{id}}</other>'])->save();
        $third = $this->build($feed);
        Carbon::setTestNow();

        $this->assertSame($first->etag, $second->etag);
        $this->assertEquals($first->content_at, $second->content_at);
        $this->assertNotSame($first->etag, $third->etag);
        $this->assertTrue($third->content_at?->gt($second->content_at));
    }

    #[Test]
    public function the_settings_of_a_feed_win_over_the_registry(): void
    {
        $unsold = (int) DB::table('catalog_product_stock')
            ->join('catalog_stock_statuses', 'catalog_stock_statuses.id', '=', 'status_id')
            ->where('catalog_stock_statuses.code', 'on-order')
            ->value('product_id');

        $feed = $this->feed([
            'format' => 'text',
            'item' => '{{id}}:{{availability}}:{{mpn}}:{{identifier_exists}}',
            'stock_map' => ['on-order' => 'backorder'],
            'sources' => ['mpn' => ['type' => 'property', 'property' => 'contents']],
        ]);

        $lines = explode("\n", $this->file($this->build($feed)));

        $this->assertContains($unsold.':backorder::no', $lines);
        $this->assertNotEmpty(array_filter($lines, static fn (string $line): bool => str_contains($line, ':The item, a manual, a box:yes')));
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
     * @return list<int>
     */
    private function ids(Feed $feed): array
    {
        return $this->idsOf($this->build($feed));
    }

    /**
     * @return list<int>
     */
    private function idsOf(FeedBuild $build): array
    {
        $this->assertSame(FeedBuild::DONE, $build->status, (string) $build->error);
        $file = $this->file($build);

        return $file === '' ? [] : array_map('intval', explode("\n", $file));
    }

    /**
     * @return list<int>
     */
    private function branch(string $name): array
    {
        $category = $this->shop->category($name);

        return $category->subtree()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
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
