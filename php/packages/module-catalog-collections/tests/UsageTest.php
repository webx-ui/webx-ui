<?php

declare(strict_types=1);

namespace WebxUi\CatalogCollections\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blocks\BlocksServiceProvider;
use WebxUi\Blocks\Models\Region;
use WebxUi\Blocks\Tests\Fixtures\Page;
use WebxUi\CatalogCollections\Models\ProductCollection;
use WebxUi\CatalogCollections\Panel\CollectionUsage;
use WebxUi\Mcp\Registry\ToolRegistry;

/**
 * Where a collection stands (§6.4): a block in a draft, in what the site shows and in a region, the
 * number on the list from one walk, and a save that moves it.
 */
final class UsageTest extends TestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [BlocksServiceProvider::class, ...parent::getPackageProviders($app)];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-cache.enabled', true);
        $app['config']->set('webx-blocks.regions', ['footer' => ['title' => 'Footer']]);
        $app['config']->set('webx-blocks.entities', [Page::class]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->migrateOnce();

        Schema::create('pages', static function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->blocks();
            $table->draft();
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('webx:blocks:offered', ['--install' => true, '--module' => ['catalog-collections']])->assertSuccessful();

        ProductCollection::query()->create(['title' => 'Picks', 'key' => 'picks']);
        ProductCollection::query()->create(['title' => 'Other', 'key' => 'other']);
    }

    #[Test]
    public function a_block_in_a_draft_is_a_place_the_site_does_not_show_yet(): void
    {
        $page = Page::query()->create(['title' => 'Home']);
        $page->saveDraft(['blocks' => $page->storeBlocks([$this->node('picks')])]);

        [$place] = $this->places('picks');

        $this->assertSame('Home', $place['title']);
        $this->assertSame((int) $page->id, $place['id']);
        $this->assertFalse($place['live']);
        $this->assertTrue($place['draft']);
        $this->assertFalse($place['published']);
        $this->assertNull($place['region']);
    }

    #[Test]
    public function an_agent_reads_the_same_places_and_is_told_what_they_leave_out(): void
    {
        $page = Page::query()->create(['title' => 'Home']);
        $page->saveDraft(['blocks' => $page->storeBlocks([$this->node('picks')])]);

        $registry = $this->app->make(ToolRegistry::class);
        $usage = ($registry->tool('catalog_collections_usage')->tool->handler)(['collection' => 'picks']);

        $this->assertSame('Home', $usage['places'][0]['title']);
        $this->assertStringContainsString('shortcode', $usage['not_counted']);

        $list = ($registry->tool('catalog_collections_list')->tool->handler)([]);

        $this->assertSame(['picks' => 1, 'other' => 0], array_column($list['collections'], 'places', 'key'));
    }

    #[Test]
    public function a_published_block_is_in_what_the_site_shows(): void
    {
        $page = Page::query()->create(['title' => 'Home']);
        $page->saveDraft(['blocks' => $page->storeBlocks([$this->node('picks')])]);
        $page->publish();

        [$place] = $this->places('picks');

        $this->assertTrue($place['live']);
        $this->assertTrue($place['published']);
    }

    #[Test]
    public function a_block_taken_out_in_the_draft_is_still_a_place_until_that_is_published(): void
    {
        $page = Page::query()->create(['title' => 'Home']);
        $page->saveDraft(['blocks' => $page->storeBlocks([$this->node('picks')])]);
        $page->publish();
        $page->saveDraft(['blocks' => $page->storeBlocks([$this->node('other')])]);

        [$picks] = $this->places('picks');
        [$other] = $this->places('other');

        $this->assertTrue($picks['live']);
        $this->assertFalse($picks['draft']);
        $this->assertFalse($other['live']);
        $this->assertTrue($other['draft']);
    }

    #[Test]
    public function a_region_is_a_place_by_its_name(): void
    {
        $region = Region::query()->create(['name' => 'footer']);
        $region->saveDraft(['blocks' => $region->storeBlocks([$this->node('picks')])]);
        $region->publish();

        [$place] = $this->places('picks');

        $this->assertSame('footer', $place['region']);
        $this->assertStringContainsString('Footer', (string) $place['title']);
        $this->assertTrue($place['live']);
        $this->assertNull($place['url']);
    }

    #[Test]
    public function the_list_counts_each_collection_and_a_page_with_it_twice_once(): void
    {
        $page = Page::query()->create(['title' => 'Home']);
        $page->saveDraft(['blocks' => $page->storeBlocks([$this->node('picks'), [...$this->node('picks'), 'key' => 'k2']])]);

        $region = Region::query()->create(['name' => 'footer']);
        $region->saveDraft(['blocks' => $region->storeBlocks([$this->node('picks')])]);

        $this->assertSame(['picks' => 2, 'other' => 0], $this->counts());
    }

    #[Test]
    public function saving_content_moves_the_number_that_was_kept(): void
    {
        $this->assertSame(['picks' => 0, 'other' => 0], $this->counts());

        $page = Page::query()->create(['title' => 'Home']);
        $page->saveDraft(['blocks' => $page->storeBlocks([$this->node('picks')])]);

        $this->assertSame(['picks' => 1, 'other' => 0], $this->counts());

        $page->saveDraft(['blocks' => []]);

        $this->assertSame(['picks' => 0, 'other' => 0], $this->counts());
    }

    #[Test]
    public function the_list_walks_the_content_once_and_then_not_at_all(): void
    {
        foreach (range(1, 5) as $index) {
            ProductCollection::query()->create(['title' => 'More '.$index, 'key' => 'more-'.$index]);
        }

        Page::query()->create(['title' => 'Home']);

        $walks = $this->walks();
        $this->assertSame(1, $walks, 'one walk for all the rows');

        $this->assertSame(0, $this->walks(), 'a warm list reads no content');
    }

    #[Test]
    public function a_collection_in_the_bin_still_says_where_it_stands(): void
    {
        $page = Page::query()->create(['title' => 'Home']);
        $page->saveDraft(['blocks' => $page->storeBlocks([$this->node('picks')])]);

        $collection = ProductCollection::query()->where('key', 'picks')->firstOrFail();
        $collection->delete();

        $this->assertCount(1, $this->places('picks'));

        $rows = $this->actingAs($this->manager(), 'cms')->getJson($this->api().'?trashed=1')->assertOk()->json('data');
        $this->assertSame([1], array_column($rows, 'places'));
    }

    #[Test]
    public function a_reader_of_the_catalogue_asks_and_nobody_else_does(): void
    {
        $id = (int) ProductCollection::query()->where('key', 'picks')->value('id');

        $this->actingAs($this->manager(['catalog.view']), 'cms')->getJson($this->api($id.'/usage'))->assertOk();
        $this->actingAs($this->manager([]), 'cms')->getJson($this->api($id.'/usage'))->assertForbidden();
        $this->actingAs($this->manager(), 'cms')->getJson($this->api('999999/usage'))->assertNotFound();
    }

    #[Test]
    public function it_knows_what_it_counts(): void
    {
        $usage = $this->app->make(CollectionUsage::class);

        $this->assertTrue($usage->available());
        $this->assertSame([], $usage->of('nobody-has-this'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function places(string $key): array
    {
        $id = (int) ProductCollection::withTrashed()->where('key', $key)->value('id');

        return $this->actingAs($this->manager(), 'cms')->getJson($this->api($id.'/usage'))->assertOk()->json('data');
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        $rows = $this->actingAs($this->manager(), 'cms')->getJson($this->api())->assertOk()->json('data');

        return array_column($rows, 'places', 'key');
    }

    /** How many times one request of the list read the pages. */
    private function walks(): int
    {
        $user = $this->manager();
        $this->app->forgetScopedInstances();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user, 'cms')->getJson($this->api())->assertOk();

        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return count(array_filter($queries, static fn (string $sql): bool => str_contains($sql, 'from "pages"')));
    }

    /**
     * @return array{key: string, type: string, values: array<string, mixed>}
     */
    private function node(string $key): array
    {
        return ['key' => 'k1', 'type' => 'product-collection', 'values' => ['collection' => $key]];
    }
}
