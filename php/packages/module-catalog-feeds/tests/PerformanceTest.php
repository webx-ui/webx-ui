<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Catalog\Tests\Fixtures\Shop;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogFeeds\Build\FeedBuilder;
use WebxUi\CatalogFeeds\Delivery\FeedWatch;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\CatalogFeeds\Presets\GooglePreset;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogProperties\CatalogPropertiesServiceProvider;
use WebxUi\CatalogStock\StockServiceProvider;

/**
 * The query budget of what a feed costs (WEBX_UI_PERFORMANCE.md): the address a marketplace polls,
 * the list and the form of the panel, a page of a build, and the watcher's minute.
 *
 * Every budget is a ceiling these fit under today with the shop of the core's fixture, not a target.
 * No freshness test: a feed caches nothing, its file is the cache and `FeedWatch` keeps it fresh
 * (`DeliveryTest`). A change that needs more says why here before raising the number.
 */
final class PerformanceTest extends TestCase
{
    use CountsQueries;

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
    }

    #[Test]
    public function the_address_costs_the_same_with_the_file_and_without(): void
    {
        $feed = $this->feed();
        $this->build($feed);
        $path = '/feeds/'.$feed->access_key.'.xml';

        $etag = (string) $this->get($path)->assertOk()->headers->get('ETag');

        $this->assertQueriesWithin(3, fn () => $this->get($path)->assertOk(), 'the file');
        $this->assertQueriesWithin(3, fn () => $this->get($path, ['If-None-Match' => $etag])->assertStatus(304), 'not modified');
        $this->assertNoRepeatedQueries(fn () => $this->get($path)->assertOk(), 'the file');
    }

    #[Test]
    public function the_list_costs_the_same_for_three_feeds_and_for_thirty(): void
    {
        $this->actingAs($this->editor(), 'cms');
        $this->build($this->feed());
        // The editor's roles are read once and kept on the user: no price of the list.
        $this->getJson($this->api())->assertOk();

        $three = $this->listQueries(2);
        $thirty = $this->listQueries(27);

        $this->assertSame($three, $thirty);
        $this->assertLessThanOrEqual(6, $thirty);
        $this->assertNoRepeatedQueries(fn () => $this->getJson($this->api())->assertOk(), 'the list');
    }

    #[Test]
    public function the_form_fits_its_budget(): void
    {
        $this->actingAs($this->editor(), 'cms');
        $feed = $this->feed();
        $this->build($feed);

        $this->assertQueriesWithin(4, fn () => $this->getJson($this->api((string) $feed->id))->assertOk(), 'the form');
        $this->assertQueriesWithin(5, fn () => $this->getJson($this->api((string) $feed->id).'/builds')->assertOk(), 'the journal');
    }

    #[Test]
    public function a_page_of_the_google_preset_fits_its_budget(): void
    {
        // Held back: the sync queue would build the whole file inside `start()`.
        Queue::fake();
        $this->app['config']->set('webx-catalog-feeds.chunk', 5);

        $builder = $this->app->make(FeedBuilder::class);
        $build = $builder->start($this->feed());
        $this->assertInstanceOf(FeedBuild::class, $build);

        // The first page warms what is read once a job; the second is the one every page repeats.
        $builder->work($build->id, 0);

        $this->assertQueriesWithin(15, static fn () => $builder->work($build->id, 0), 'a page');
    }

    #[Test]
    public function the_watcher_looks_at_every_feed_in_one_go(): void
    {
        foreach (range(1, 5) as $n) {
            $feed = $this->feed(['name' => 'Watched '.$n, 'rebuild_on_change' => true]);
            $this->build($feed);
        }

        $watch = $this->app->make(FeedWatch::class);
        $watch->tick();

        $this->assertQueriesWithin(3, static fn () => $watch->tick(), 'a quiet minute');
    }

    private function listQueries(int $more): int
    {
        foreach (range(1, $more) as $n) {
            $this->build($this->feed(['name' => "Feed {$more}-{$n}"]));
        }

        return $this->recordQueries(fn () => $this->getJson($this->api().'?per_page=200')->assertOk())->count();
    }

    private function build(Feed $feed): void
    {
        $build = $this->app->make(FeedBuilder::class)->start($feed->refresh());
        $this->assertInstanceOf(FeedBuild::class, $build);
        $this->assertSame(FeedBuild::DONE, $build->refresh()->status, (string) $build->error);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function feed(array $settings = []): Feed
    {
        $feed = new Feed;
        $feed->forceFill([
            ...(new GooglePreset)->values(),
            'name' => 'Spare parts',
            'preset' => 'google',
            'locale' => 'en',
            'currency' => 'EUR',
            ...$settings,
        ])->save();

        return $feed->refresh();
    }
}
