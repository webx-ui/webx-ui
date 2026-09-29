<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogEngines;
use WebxUi\Catalog\Engine\Indexer;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Tests\Fixtures\IndexingEngine;

/**
 * §8.3 and §16: the queue of the engine — nothing at all under the database engine, one
 * statement for a query's worth of products, a batch done twice is harmless, and a batch the
 * engine refused waits for the next pass.
 */
final class QueueTest extends TestCase
{
    #[Test]
    public function under_the_database_engine_nothing_is_ever_queued(): void
    {
        $laptops = $this->category('laptops');
        $product = $this->product('ThinkPad', $laptops);
        $product->update(['price' => 10]);

        $catalog = $this->app->make(Catalog::class);
        $catalog->touch([$product->id]);
        $catalog->touchQuery(Product::query());
        $catalog->touchCategory($laptops);

        $this->assertSame(0, DB::table('catalog_index_queue')->count());
        $this->artisan('webx:catalog:index')->expectsOutputToContain('no index')->assertSuccessful();
    }

    #[Test]
    #[DefineEnvironment('withAnIndex')]
    public function a_query_of_products_is_queued_in_one_statement(): void
    {
        $laptops = $this->category('laptops');
        $this->product('One', $laptops);
        $this->product('Two', $laptops);
        $this->product('Three');
        DB::table('catalog_index_queue')->delete();

        DB::enableQueryLog();
        $this->app->make(Catalog::class)->touchQuery(Product::query()->whereNotNull('category_id'));
        $statements = array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], 'catalog_index_queue'));
        DB::disableQueryLog();

        $this->assertCount(1, $statements);
        $this->assertSame(2, DB::table('catalog_index_queue')->count());
    }

    #[Test]
    #[DefineEnvironment('withAnIndex')]
    public function the_worker_writes_every_contributors_document_and_empties_the_queue(): void
    {
        $laptops = $this->category('laptops');
        $product = $this->product('ThinkPad', $laptops, ['sku' => 'TP-1', 'price' => 999]);
        $gone = $this->product('Gone', $laptops);
        $this->app->make(Catalog::class)->touch([$product->id, $gone->id, 404]);
        $gone->forceDelete();

        $written = $this->app->make(Indexer::class)->run();
        $engine = $this->engine();

        $this->assertSame(1, $written);
        $this->assertSame(0, DB::table('catalog_index_queue')->count());
        $this->assertSame('ThinkPad', $engine->documents[$product->id]['name_en']);
        $this->assertSame([$laptops->id], $engine->documents[$product->id]['categories']);
        $this->assertSame(999.0, $engine->documents[$product->id]['price']);
        $this->assertTrue($engine->documents[$product->id]['is_visible']);
        $this->assertEqualsCanonicalizing([$gone->id, 404], $engine->removed);
    }

    #[Test]
    #[DefineEnvironment('withAnIndex')]
    public function doing_a_batch_twice_is_harmless(): void
    {
        $product = $this->product('ThinkPad', $this->category('laptops'));
        $indexer = $this->app->make(Indexer::class);
        $catalog = $this->app->make(Catalog::class);

        $catalog->touch([$product->id]);
        $indexer->run();
        $first = $this->engine()->documents;

        $catalog->touch([$product->id]);
        $catalog->touch([$product->id]);
        $indexer->run();

        $this->assertSame($first, $this->engine()->documents);
        $this->assertCount(1, $this->engine()->documents);
    }

    #[Test]
    #[DefineEnvironment('withAnIndex')]
    public function a_batch_the_engine_refused_goes_back_into_the_queue_and_the_save_never_noticed(): void
    {
        $this->engine()->failing = true;

        // The save itself does not ask the engine anything (§5.2 of the architecture).
        $product = $this->product('ThinkPad', $this->category('laptops'));
        $this->assertSame(1, DB::table('catalog_index_queue')->where('product_id', $product->id)->count());

        try {
            $this->app->make(Indexer::class)->run();
            $this->fail('The engine was down.');
        } catch (RuntimeException) {
            // Expected.
        }

        $this->assertTrue(DB::table('catalog_index_queue')->where('product_id', $product->id)->exists());

        $this->engine()->failing = false;
        $this->app->make(Indexer::class)->run();

        $this->assertArrayHasKey($product->id, $this->engine()->documents);
    }

    #[Test]
    #[DefineEnvironment('withAnIndex')]
    public function a_rebuild_writes_everything_with_the_schema_made_anew(): void
    {
        $laptops = $this->category('laptops');
        $this->product('One', $laptops);
        $deleted = $this->product('Two', $laptops);
        $deleted->delete();

        $this->artisan('webx:catalog:index', ['--rebuild' => true])->assertSuccessful();

        $this->assertCount(2, $this->engine()->documents);
        $this->assertTrue($this->engine()->documents[$deleted->id]['is_deleted']);
        $this->assertTrue($this->engine()->prepared[0]['rebuild']);
        $this->assertSame(0, DB::table('catalog_index_queue')->count());
    }

    private function engine(): IndexingEngine
    {
        return $this->app->make(IndexingEngine::class);
    }

    /**
     * @param  Application  $app
     */
    protected function withAnIndex($app): void
    {
        $app['config']->set('webx-catalog.engine', 'indexing');
        $app->singleton(IndexingEngine::class);
        $app->afterResolving(CatalogEngines::class, static function (CatalogEngines $engines): void {
            $engines->register('indexing', IndexingEngine::class);
        });
    }
}
