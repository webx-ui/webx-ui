<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\History\HistoryEntry;
use WebxUi\Admin\Screens\ScreenRegistry;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Tests\Fixtures\Shop;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogFeeds\Build\BuildFeed;
use WebxUi\CatalogFeeds\Delivery\FeedKey;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\CatalogStock\StockServiceProvider;

/**
 * §11 of the feeds spec: what the form asks of the server besides the feed itself, and that what it
 * renders by itself comes to what the server would write (§8.3).
 */
final class PanelApiTest extends TestCase
{
    private const FIXTURE = __DIR__.'/fixtures/preview.json';

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
        $this->actingAs($this->editor(), 'cms');
    }

    #[Test]
    public function the_form_is_a_screen_with_its_tabs(): void
    {
        $this->getJson('/api/cms/screens/'.Feed::SCREEN)
            ->assertOk()
            ->assertJsonPath('data.root.0.id', 'tabs')
            ->assertJsonCount(7, 'data.root.0.children');

        $names = array_column($this->app->make(ScreenRegistry::class)->fields(Feed::SCREEN), 'name');

        foreach (['name', 'format', 'locale', 'is_active', 'schedule', 'filter', 'stock_map', 'taxonomy'] as $field) {
            $this->assertContains($field, $names);
        }
    }

    #[Test]
    public function the_meta_lists_the_stock_statuses_of_the_satellite(): void
    {
        $statuses = $this->getJson($this->api('meta'))->assertOk()->assertJsonPath('data.stock', true)->json('data.stock_statuses');

        $this->assertNotEmpty($statuses);
        $this->assertSame(['code', 'title', 'is_purchasable'], array_keys($statuses[0]));
    }

    #[Test]
    public function the_variables_come_by_part_with_words_of_their_own(): void
    {
        $answer = $this->getJson($this->api('variables'))->assertOk();

        $item = array_column($answer->json('data.item'), 'name');
        $frame = $answer->json('data.frame');

        $this->assertContains('price_with_currency', $item);
        $this->assertNotContains('shop.name', $item);
        $this->assertContains('shop.name', array_column($frame, 'name'));
        $this->assertNotContains('price', array_column($frame, 'name'));

        foreach ($frame as $variable) {
            $this->assertStringNotContainsString('webx-catalog-feeds::', $variable['label']);
            $this->assertNotSame($variable['name'], $variable['label']);
        }
    }

    #[Test]
    public function the_facets_offer_what_the_selection_may_name(): void
    {
        $facets = array_column((array) $this->getJson($this->api('facets'))->assertOk()->json('data'), null, 'key');

        $this->assertArrayNotHasKey('category', $facets);
        $this->assertSame('range', $facets['price']['kind']);
        $this->assertNotEmpty($facets['brand']['values']);

        // A filter holds the code of a label, and the value offered has to be what it holds.
        foreach ($facets['label']['values'] as $value) {
            $this->assertNotSame('', $value['label']);
            $this->assertDoesNotMatchRegularExpression('/^\d+$/', $value['value']);
        }
    }

    #[Test]
    public function the_count_follows_the_filter_and_the_exclusions_of_the_feed(): void
    {
        $feed = $this->feed();
        $all = $this->postJson($this->api('count'), ['filter' => []])->assertOk()->json('data.selected');
        $first = (int) Product::query()->visible()->orderBy('id')->value('id');

        $this->assertGreaterThan(1, $all);
        $this->assertSame(0, $this->postJson($this->api('count'), ['filter' => ['price' => ['min' => 999999999]]])->json('data.selected'));

        DB::table('catalog_feed_exclusions')->insert(['feed_id' => $feed->id, 'product_id' => $first, 'created_at' => Carbon::now()]);

        $this->postJson($this->api('count'), ['filter' => [], 'feed' => $feed->id])
            ->assertOk()
            ->assertJsonPath('data.selected', $all - 1)
            ->assertJsonPath('data.excluded', 1);

        $this->postJson($this->api('count'), ['filter' => ['properties' => ['nobody' => ['1']]]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('filter');
    }

    #[Test]
    public function a_person_takes_a_product_out_and_brings_it_back(): void
    {
        $feed = $this->feed();
        $product = Product::query()->visible()->orderBy('id')->firstOrFail();
        $path = $this->api($feed->id.'/exclusions');

        $this->postJson($path, ['product_ids' => [$product->id]])->assertNoContent();
        $this->postJson($path, ['product_ids' => [$product->id]])->assertNoContent();

        $this->getJson($path)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', (int) $product->id);
        $this->getJson($path.'?q=nothing-is-called-this')->assertOk()->assertJsonPath('total', 0);

        $this->postJson($path, ['product_ids' => [999999]])->assertUnprocessable();

        $this->deleteJson($path.'/'.$product->id)->assertNoContent();
        $this->getJson($path)->assertJsonPath('total', 0);
    }

    #[Test]
    public function a_rebuild_is_queued_once_and_a_feed_that_is_off_says_so(): void
    {
        Queue::fake();

        $feed = $this->feed();

        $id = $this->postJson($this->api($feed->id.'/rebuild'))->assertAccepted()->assertJsonPath('data.status', 'queued')->assertJsonPath('data.trigger', FeedBuild::MANUAL)->json('data.id');
        $this->postJson($this->api($feed->id.'/rebuild'))->assertAccepted()->assertJsonPath('data.id', $id);

        Queue::assertPushed(BuildFeed::class, 1);

        $feed->forceFill(['is_active' => false])->save();

        $this->postJson($this->api($feed->id.'/rebuild'))->assertUnprocessable()->assertJsonValidationErrors('is_active');
    }

    #[Test]
    public function the_journal_tells_a_build_what_it_left_out_and_the_reissues_of_the_key(): void
    {
        $feed = $this->feed();
        $product = Product::query()->orderBy('id')->firstOrFail();

        $build = FeedBuild::query()->create([
            'feed_id' => $feed->id,
            'status' => FeedBuild::DONE,
            'trigger' => FeedBuild::MANUAL,
            'queued_at' => Carbon::now(),
            'finished_at' => Carbon::now(),
            'selected' => 3,
            'written' => 2,
            'rejected' => ['no_image' => 1],
            'warnings' => ['no_brand' => 2],
        ]);

        DB::table('catalog_feed_build_rejects')->insert(['build_id' => $build->id, 'product_id' => $product->id, 'reason' => 'no_image']);
        $feed->forceFill(['current_build_id' => $build->id])->save();
        $this->app->make(FeedKey::class)->reissue($feed);

        $this->getJson($this->api($feed->id.'/builds'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.written', 2)
            ->assertJsonPath('data.0.current', true)
            ->assertJsonPath('data.0.rejected.no_image', 1)
            ->assertJsonCount(1, 'reissues');

        $this->getJson($this->api($feed->id.'/builds/'.$build->id))
            ->assertOk()
            ->assertJsonPath('data.rejects.0.product_id', (int) $product->id)
            ->assertJsonPath('data.rejects.0.reason', 'no_image')
            ->assertJsonPath('data.rejects.0.name', $product->displayName('en'));

        $this->assertSame(1, HistoryEntry::query()->where('subject_id', $feed->id)->where('event', HistoryEntry::KEY_REISSUED)->count());

        $other = $this->feed();

        $this->getJson($this->api($other->id.'/builds/'.$build->id))->assertNotFound();
    }

    #[Test]
    public function the_sample_does_not_depend_on_the_template_and_keeps_nothing(): void
    {
        $count = Feed::query()->count();

        $answer = $this->postJson($this->api('sample'), [...$this->fields(), 'item' => '<broken'])->assertOk();

        $this->assertNotEmpty($answer->json('data.products'));
        $this->assertArrayHasKey('price_with_currency', $answer->json('data.products.0.values'));
        $this->assertSame('EUR', $answer->json('data.frame')['feed.currency']);
        $this->assertSame((string) count($answer->json('data.products')), $answer->json('data.frame.products_count'));
        $this->assertSame($count, Feed::query()->count());
    }

    #[Test]
    public function a_sample_keeps_the_line_breaks_of_a_form_and_names_a_product(): void
    {
        $product = Product::query()->visible()->whereHas('images')->orderByDesc('id')->firstOrFail();

        $this->postJson($this->api('sample'), [...$this->fields(), 'product_ids' => [$product->id]])
            ->assertOk()
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.id', (int) $product->id);
    }

    #[Test]
    public function a_setting_that_cannot_be_read_is_the_answer_of_a_sample(): void
    {
        $this->postJson($this->api('sample'), [...$this->fields(), 'filter' => ['properties' => ['nobody' => ['1']]]])
            ->assertOk()
            ->assertJsonPath('data.products', [])
            ->assertJsonPath('data.errors.0.field', 'filter');
    }

    /**
     * What the browser renders with and what the server writes for the same feed, kept for
     * `preview.test.ts` to render: a difference fails there. The clock stands still, or the two
     * answers would differ by the second between them. `WEBX_WRITE_FIXTURES=1` writes the file, and
     * Prettier formats it after that: what is compared is the data.
     */
    #[Test]
    public function the_cases_of_the_browser_are_what_the_server_answers_now(): void
    {
        Carbon::setTestNow('2026-11-05 12:00:00');

        $cases = [];

        foreach (['google', 'json', 'xml', 'csv'] as $preset) {
            $presets = array_column((array) $this->getJson($this->api('meta'))->json('data.presets'), null, 'key');
            $values = $presets[$preset]['values'];
            $body = ['name' => 'Spare parts', 'preset' => $preset, ...$values];

            $sample = $this->postJson($this->api('sample'), $body)->assertOk()->json('data');
            $preview = $this->postJson($this->api('preview'), $body)->assertOk()->json('data');

            $this->assertSame([], $preview['errors']);
            $this->assertNotEmpty($sample['products']);

            $cases[] = [
                'name' => $preset,
                'feed' => array_intersect_key($values, array_flip(['format', 'head', 'item', 'foot', 'separator', 'options'])),
                'sample' => ['products' => $sample['products'], 'frame' => $sample['frame']],
                'text' => $preview['text'],
            ];
        }

        Carbon::setTestNow();

        $json = json_encode($cases, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

        if (getenv('WEBX_WRITE_FIXTURES') === '1') {
            file_put_contents(self::FIXTURE, $json);
        }

        $this->assertSame(json_decode($json, true), json_decode((string) file_get_contents(self::FIXTURE), true));
    }

    private function feed(): Feed
    {
        return Feed::query()->create([...$this->fields(), 'locale' => 'en', 'currency' => 'EUR']);
    }
}
