<?php

declare(strict_types=1);

namespace WebxUi\CatalogFeeds\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Demo\DemoPlan;
use WebxUi\Admin\ModuleRegistry;
use WebxUi\Admin\Setup\Catalogue;
use WebxUi\Catalog\Demo\CatalogDemo;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Variables\Gtin;
use WebxUi\CatalogBrands\BrandsServiceProvider;
use WebxUi\CatalogBrands\Demo\BrandsDemo;
use WebxUi\CatalogFeeds\Delivery\FeedWatch;
use WebxUi\CatalogFeeds\Demo\FeedsDemo;
use WebxUi\CatalogFeeds\Models\Feed;
use WebxUi\CatalogFeeds\Models\FeedBuild;
use WebxUi\CatalogStock\Demo\StockDemo;
use WebxUi\CatalogStock\StockServiceProvider;

/**
 * `webx:demo` of the feeds: a feed on the demo shop, built by the watcher once the demos after it
 * are through, and a file with every case the rules of §14.2 of the feeds spec have to meet.
 */
final class DemoTest extends TestCase
{
    private const G = 'http://base.google.com/ns/1.0';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            BrandsServiceProvider::class,
            StockServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The demo's pictures go in as they are: the pipeline is the media module's test.
        $app['config']->set('webx-media.optimize.enabled', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        $this->app->make('url')->forceRootUrl('https://example.test');
        $this->app->make('url')->forceScheme('https');
    }

    #[Test]
    public function the_module_is_offered_to_the_demo_after_the_catalogue(): void
    {
        $ids = array_map(
            static fn ($module): string => $module->id(),
            DemoPlan::of($this->app->make(ModuleRegistry::class))->seed,
        );

        $this->assertContains('catalog-feeds', $ids);
        $this->assertLessThan(array_search('catalog-feeds', $ids, true), array_search('catalog', $ids, true));
        $this->assertSame(['en'], $this->app->make(Catalogue::class)->demoOf('catalog-feeds'));
    }

    #[Test]
    public function the_demo_feed_is_built_by_the_watcher_and_holds_every_case_of_the_rules(): void
    {
        $ledger = $this->seeded();

        $feed = Feed::query()->sole();
        $this->assertSame(FeedsDemo::NAME, $feed->name);
        $this->assertSame('google', $feed->preset);
        $this->assertSame('EUR', $feed->currency);
        $this->assertSame(0, FeedBuild::query()->count(), 'A file made during the demo would miss the stock.');

        $this->assertSame(1, $this->app->make(FeedWatch::class)->tick());

        $build = FeedBuild::query()->sole();
        $this->assertSame(FeedBuild::DONE, $build->status, (string) $build->error);
        $this->assertSame(FeedBuild::SCHEDULE, $build->trigger);

        $items = $this->items($feed->refresh());
        // The first product's only picture plays a video, and a video does not count (§5.3).
        $this->assertSame(['no_image' => 1], $build->refresh()->rejected);
        $this->assertSame($this->onSite() - 1, count($items), 'Every other product on the site goes out.');

        $value = static fn (SimpleXMLElement $item, string $name): string => trim((string) $item->children(self::G)->{$name});

        foreach ($items as $item) {
            $this->assertNotSame('', $value($item, 'brand'), 'A product without a brand is a fault of the demo: '.$value($item, 'id'));

            if ($value($item, 'gtin') !== '') {
                $this->assertSame($value($item, 'gtin'), Gtin::of($value($item, 'gtin')));
            }

            $this->assertSame(
                $value($item, 'gtin') === '' && $value($item, 'mpn') === '' ? 'no' : 'yes',
                $value($item, 'identifier_exists'),
            );
            $this->assertMatchesRegularExpression('/^[\x21-\x7e]+$/', $value($item, 'link'));
        }

        $some = static fn (callable $test): int => count(array_filter($items, $test));

        $this->assertGreaterThan(0, $some(static fn ($item): bool => $value($item, 'sale_price') !== ''), 'A sale.');
        $this->assertGreaterThan(0, $some(static fn ($item): bool => $value($item, 'identifier_exists') === 'no'), 'No identifiers.');
        $this->assertGreaterThan(0, $some(static fn ($item): bool => $value($item, 'gtin') !== ''), 'A GTIN.');
        $this->assertGreaterThan(0, $some(static fn ($item): bool => $value($item, 'availability') === 'out_of_stock'), 'Out of stock.');
        $this->assertGreaterThan(0, $some(static fn ($item): bool => $value($item, 'availability') === 'in_stock'), 'In stock.');
        $this->assertSame(1, $some(static fn ($item): bool => preg_match('/\p{Cyrillic}/u', $value($item, 'title')) === 1), 'A name in Cyrillic, its link checked above.');
        $this->assertSame(1, $some(static fn ($item): bool => $value($item, 'title') === 'Knife & board set "Pro" <20 cm>'), 'A name that has to be escaped.');

        $this->assertGreaterThan(0, $build->refresh()->warnings['invalid_gtin'] ?? 0, 'A shop\'s own code is no GTIN.');

        $this->removed($ledger);
    }

    #[Test]
    public function a_second_run_makes_no_second_feed(): void
    {
        $ledger = $this->seeded();
        $ledger->takeNotes();

        $this->app->make(FeedsDemo::class)->seed($ledger);

        $this->assertSame(1, Feed::query()->count());
        $this->assertCount(1, $ledger->takeNotes());
    }

    #[Test]
    public function a_site_with_a_feed_of_its_own_is_left_alone(): void
    {
        $ledger = $this->app->make(DemoLedger::class);
        $ledger->forModule('catalog');
        $this->app->make(CatalogDemo::class)->seed($ledger);

        $feed = new Feed;
        $feed->forceFill(['name' => 'Mine', 'format' => 'text', 'locale' => 'en', 'currency' => 'EUR', 'item' => '{{id}}'])->save();

        $ledger->forModule('catalog-feeds');
        $this->app->make(FeedsDemo::class)->seed($ledger);

        $this->assertSame(['Mine'], Feed::query()->pluck('name')->all());
        $this->assertSame([], $ledger->idsOf('catalog-feeds', Feed::class));
    }

    #[Test]
    public function without_the_demo_shop_or_a_currency_it_says_so_and_makes_nothing(): void
    {
        $ledger = $this->app->make(DemoLedger::class);
        $ledger->forModule('catalog-feeds');
        $this->app->make(FeedsDemo::class)->seed($ledger);

        $this->assertCount(1, $ledger->takeNotes());
        $this->assertSame(0, Feed::query()->count());

        $ledger->forModule('catalog');
        $this->app->make(CatalogDemo::class)->seed($ledger);
        $this->app['config']->set('webx-catalog.price.currency', null);

        $ledger->forModule('catalog-feeds');
        $this->app->make(FeedsDemo::class)->seed($ledger);

        $this->assertCount(1, $ledger->takeNotes());
        $this->assertSame(0, Feed::query()->count());
    }

    /** In the order of the real run: the feed comes before the stock. */
    private function seeded(): DemoLedger
    {
        $ledger = $this->app->make(DemoLedger::class);

        foreach (['catalog' => CatalogDemo::class, 'catalog-brands' => BrandsDemo::class, 'catalog-feeds' => FeedsDemo::class, 'catalog-stock' => StockDemo::class] as $id => $demo) {
            $ledger->forModule($id);
            $this->app->make($demo)->seed($ledger);
        }

        return $ledger;
    }

    private function onSite(): int
    {
        return Product::query()->where('is_published', true)->whereNotNull('category_id')->count();
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private function items(Feed $feed): array
    {
        $file = $this->get('/feeds/'.$feed->access_key.'.xml')->assertOk()->streamedContent();
        $xml = simplexml_load_string($file);
        $this->assertInstanceOf(SimpleXMLElement::class, $xml, 'The file is XML.');

        return iterator_to_array($xml->channel->item, false);
    }

    private function removed(DemoLedger $ledger): void
    {
        $path = (string) FeedBuild::query()->value('path');
        Storage::disk('local')->assertExists($path);

        foreach (array_reverse($ledger->entries()) as $entry) {
            $ledger->undo($entry);
        }

        $this->assertSame(0, Feed::query()->count());
        $this->assertSame(0, FeedBuild::query()->count());
        Storage::disk('local')->assertMissing($path);
    }
}
