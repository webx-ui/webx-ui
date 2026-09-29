<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Engine\CatalogEngines;
use WebxUi\Catalog\Models\Product;
use WebxUi\Catalog\Popularity\PopularitySignal;
use WebxUi\Catalog\Popularity\PopularitySignals;
use WebxUi\Catalog\Tests\Fixtures\IndexingEngine;

/**
 * §9 and §16: views go from the cache to the table in bulk, fade at night, and are weighed with
 * every other signal into a score; only the products whose score moved go to the engine.
 */
final class PopularityTest extends TestCase
{
    #[Test]
    public function counted_views_are_written_down_and_added_to_what_was_there(): void
    {
        $laptops = $this->category('laptops');
        $a = $this->product('One', $laptops);
        $b = $this->product('Two', $laptops);

        $this->browse($a, 3);
        $this->browse($b, 1);
        $this->artisan('webx:catalog:flush-views')->assertSuccessful();

        $this->browse($a, 2);
        $this->artisan('webx:catalog:flush-views')->assertSuccessful();

        $this->assertEquals(5, $this->views($a->id));
        $this->assertEquals(1, $this->views($b->id));
    }

    #[Test]
    #[DefineEnvironment('withAnIndex')]
    public function the_recount_fades_the_views_weighs_the_signals_and_touches_only_what_moved(): void
    {
        $this->app['config']->set('webx-catalog.popularity.weights', ['views' => 1, 'sales' => 10]);

        $laptops = $this->category('laptops');
        $steady = $this->product('Steady', $laptops);
        $rising = $this->product('Rising', $laptops);
        $quiet = $this->product('Quiet', $laptops);

        // A satellite's signal: sales, a batch at a time.
        $this->app->make(PopularitySignals::class)->register(new class($rising->id) implements PopularitySignal
        {
            public function __construct(private readonly int $rising) {}

            public function key(): string
            {
                return 'sales';
            }

            public function values(array $productIds): array
            {
                return in_array($this->rising, $productIds, true) ? [$this->rising => 3.0] : [];
            }
        });

        DB::table('catalog_product_popularity')->insert([
            ['product_id' => $steady->id, 'views' => 100, 'score' => 90],
            ['product_id' => $rising->id, 'views' => 10, 'score' => 9],
        ]);
        DB::table('catalog_index_queue')->delete();

        $this->artisan('webx:catalog:popularity')->assertSuccessful();

        // 100 × 0.9 = 90: the same score as before, nothing to reindex.
        $this->assertEquals(90, $this->views($steady->id));
        $this->assertEquals(90, $this->score($steady->id));
        // 10 × 0.9 + 10 × 3 = 39: moved.
        $this->assertEquals(39, $this->score($rising->id));
        $this->assertEquals(0, $this->score($quiet->id));

        $this->assertSame([$rising->id], DB::table('catalog_index_queue')->pluck('product_id')->map(static fn (mixed $id): int => (int) $id)->all());
    }

    private function browse(Product $product, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->withHeader('User-Agent', 'Mozilla/5.0 Firefox/140.0')->get($product->url())->assertOk();
        }
    }

    private function views(int $id): float
    {
        return (float) DB::table('catalog_product_popularity')->where('product_id', $id)->value('views');
    }

    private function score(int $id): float
    {
        return (float) DB::table('catalog_product_popularity')->where('product_id', $id)->value('score');
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
