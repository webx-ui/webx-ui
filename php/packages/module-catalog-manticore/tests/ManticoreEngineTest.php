<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Engine\CatalogResult;
use WebxUi\Catalog\Engine\Indexer;
use WebxUi\Catalog\Manticore\Manticore;
use WebxUi\Catalog\Manticore\ManticoreEngine;
use WebxUi\Catalog\Tests\EngineScenarios;

/**
 * The questions every engine answers alike, asked of a live Manticore (decision 26 of the
 * Manticore spec) — and what is Manticore's own: a table per language that finds a word of any
 * language of the site (decisions 5–6), and a rebuild that swaps a whole table in (decisions 8–9).
 */
final class ManticoreEngineTest extends EngineScenarios
{
    use UsesManticore;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->useManticore($app);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->skipWithoutManticore();
    }

    protected function tearDown(): void
    {
        $this->dropOwnTables();
        parent::tearDown();
    }

    protected function settle(): void
    {
        $this->app->make(Indexer::class)->run();
    }

    #[Test]
    public function every_language_has_a_table_that_finds_a_word_in_any_language_of_the_site(): void
    {
        $this->useLocales('en', 'ru');
        $phones = $this->category('phones');

        $case = $this->product('Protective case for phone', $phones);
        $case->setTranslation('name', 'ru', 'Защитный чехол для телефона')->save();
        // No Russian name: the Russian table takes the English one, as the storefront does.
        $cable = $this->product('USB cable', $phones);

        $this->settle();

        $this->assertSame([$case->id], $this->ask('чехлы', 'en')->ids);
        $this->assertSame([$case->id], $this->ask('cases', 'ru')->ids);
        $this->assertSame([$cable->id], $this->ask('cable', 'ru')->ids);
        // The beginning of a word is enough.
        $this->assertSame([$case->id], $this->ask('protec', 'ru')->ids);

        $tables = array_column($this->engine()->status(), 'table');
        $this->assertSame([$this->prefix.'_catalog_products_en', $this->prefix.'_catalog_products_ru'], $tables);
    }

    #[Test]
    public function a_changed_schema_is_reported_and_the_rebuild_swaps_a_whole_table_in(): void
    {
        $laptops = $this->category('laptops');
        $product = $this->product('ThinkPad X1', $laptops);
        $this->settle();

        $this->assertSame('ready', $this->engine()->status()['en']['state']);

        // Another morphology is another table: reported, not rebuilt behind anybody's back.
        $this->app['config']->set('webx-catalog-manticore.morphology.en', 'libstemmer_en');
        $this->app->forgetInstance(Catalog::class);
        $this->settle();

        $status = $this->engine()->status()['en'];
        $this->assertSame('stale', $status['state']);
        $this->assertStringContainsString('morphology', (string) $status['reason']);
        $this->assertSame([$product->id], $this->ask('thinkpad', 'en')->ids);

        $this->app->make(Indexer::class)->rebuild();

        $status = $this->engine()->status()['en'];
        $this->assertSame('ready', $status['state']);
        $this->assertSame(1, $status['documents']);
        $this->assertSame([$product->id], $this->ask('thinkpad', 'en')->ids);

        $server = $this->app->make(Manticore::class);
        $names = array_column($server->sql('SHOW TABLES')[0]['data'] ?? [], 'Table');
        $this->assertNotContains($server->table('en', next: true), $names);
    }

    #[Test]
    public function a_product_gone_from_the_table_is_gone_from_the_index(): void
    {
        $laptops = $this->category('laptops');
        $kept = $this->product('Kept laptop', $laptops);
        $gone = $this->product('Gone laptop', $laptops);
        $this->settle();

        $gone->forceDelete();
        $this->settle();

        $this->assertSame([$kept->id], $this->ask('laptop', 'en')->ids);
    }

    private function ask(string $search, string $locale): CatalogResult
    {
        return $this->engine()->search(new CatalogQuery(locale: $locale, search: $search));
    }

    private function engine(): ManticoreEngine
    {
        $engine = $this->app->make(Catalog::class)->engine();
        $this->assertInstanceOf(ManticoreEngine::class, $engine);

        return $engine;
    }
}
