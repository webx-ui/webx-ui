<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Documents\Documents;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Engine\CatalogResult;
use WebxUi\Catalog\Engine\Indexer;
use WebxUi\Catalog\Facets\FacetValue;
use WebxUi\Catalog\Facets\IndexField;
use WebxUi\Catalog\Facets\PriceFacet;
use WebxUi\Catalog\Manticore\IndexStatus;
use WebxUi\Catalog\Manticore\Manticore;
use WebxUi\Catalog\Manticore\ManticoreEngine;
use WebxUi\Catalog\Manticore\Rebuild\RebuildIndex;
use WebxUi\Catalog\Manticore\Rebuild\RebuildProgress;
use WebxUi\Catalog\Manticore\TableSchema;
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

    #[Test]
    public function a_code_is_found_by_any_part_of_its_letters_and_digits(): void
    {
        $plugs = $this->category('plugs');
        $plug = $this->product('Spark plug', $plugs, ['sku' => 'NGK.BKR6E-11']);
        $case = $this->product('Phone case', $plugs, ['sku' => 'AT-1234/56']);
        $this->settle();

        $this->assertSame([$plug->id], $this->ask('bkr6e11', 'en')->ids);
        $this->assertSame([$plug->id], $this->ask('BKR6E', 'en')->ids);
        $this->assertSame([$case->id], $this->ask('at1234', 'en')->ids);
        $this->assertSame([$case->id], $this->ask('34/5', 'en')->ids);
        // Typed without its dashes, the whole code is still the product's own.
        $this->assertSame([$case->id], $this->ask('at123456', 'en')->exact);
        // A name is not searched by a part (decision 22): «lug» is no word of «plug» — only the
        // correction finds it, and says so.
        $this->assertSame([], $this->engine()->search(new CatalogQuery(locale: 'en', search: 'lug', asTyped: true))->ids);
        $this->assertSame('plug', $this->ask('lug', 'en')->corrected);
    }

    #[Test]
    public function a_search_that_finds_nothing_is_corrected_and_says_so(): void
    {
        $phones = $this->category('phones');
        $case = $this->product('Protective case for phone', $phones);
        $this->product('USB cable', $phones);
        $this->settle();

        $typo = $this->ask('protectve case', 'en');
        $this->assertSame([$case->id], $typo->ids);
        $this->assertSame('protective case', $typo->corrected);

        // Found as typed: nothing to correct, nothing said.
        $this->assertNull($this->ask('protective', 'en')->corrected);

        // As typed, it is asked for: the empty answer is the answer.
        $typed = $this->engine()->search(new CatalogQuery(locale: 'en', search: 'protectve case', asTyped: true));
        $this->assertSame([], $typed->ids);
        $this->assertNull($typed->corrected);

        // Nothing near enough: empty, and no correction to show.
        $none = $this->ask('zzzzqqq', 'en');
        $this->assertSame(0, $none->total);
        $this->assertNull($none->corrected);
    }

    #[Test]
    public function a_search_typed_with_the_other_layout_is_found_as_meant(): void
    {
        $this->useLocales('en', 'ru');
        $case = $this->product('Protective case', $this->category('phones'));
        $case->setTranslation('name', 'ru', 'Чехол защитный')->save();
        $this->settle();

        $result = $this->ask('xt[jk', 'ru');
        $this->assertSame([$case->id], $result->ids);
        $this->assertSame('чехол', $result->corrected);
    }

    #[Test]
    public function a_table_of_an_older_schema_answers_what_it_has_until_the_rebuild(): void
    {
        $laptops = $this->category('laptops');
        $product = $this->product('ThinkPad X1', $laptops, ['sku' => 'AT-1234/56', 'price' => 900]);
        $this->settle();

        // The table as an older version made it: no codes, no price — what a deploy leaves behind.
        $server = $this->app->make(Manticore::class);
        $table = $server->table('en');
        $older = array_values(array_filter(
            $this->app->make(Documents::class)->schema(['en']),
            static fn (IndexField $field): bool => ! $field->code && $field->name !== 'price',
        ));
        $server->sql('DROP TABLE '.$table);
        $server->sql((new TableSchema($older, 'en', ['en'], 'en', ['en' => 'stem_en'], 3, 0))->create($table));
        $this->app['cache']->flush();
        $this->app->make(Catalog::class)->touch([$product->id]);
        $this->settle();

        $this->assertSame('stale', $this->engine()->status()['en']['state']);
        $this->assertSame([$product->id], $this->ask('thinkpad', 'en')->ids);
        // Asked of columns it does not have, it answers nothing rather than an error.
        $this->assertSame([], $this->ask('at123456', 'en')->ids);
        $priced = $this->engine()->search(new CatalogQuery(
            locale: 'en',
            facets: [PriceFacet::KEY => FacetValue::range(100, 1000)],
            count: [PriceFacet::KEY],
        ));
        $this->assertSame([], $priced->ids);

        // The panel's rebuild, as the queue runs it.
        $progress = $this->app->make(RebuildProgress::class);
        $progress->queue();
        $this->app->call([new RebuildIndex, 'handle']);

        $this->assertSame(RebuildProgress::DONE, $progress->get()['state']);
        $this->assertSame(1, $progress->get()['done']);
        $this->assertSame(1, $progress->get()['total']);
        $this->assertSame('ready', $this->engine()->status()['en']['state']);
        $this->assertSame([$product->id], $this->ask('at123456', 'en')->ids);
    }

    #[Test]
    public function the_report_counts_each_table_against_the_database_and_finds_one_product(): void
    {
        $laptops = $this->category('laptops');
        $kept = $this->product('ThinkPad X1', $laptops);
        $hidden = $this->product('ThinkPad X2', $laptops, ['is_published' => false]);
        $this->settle();
        $kept->delete();

        $report = $this->app->make(IndexStatus::class)->report();

        $this->assertTrue($report['connection']['available']);
        $this->assertNotNull($report['connection']['version']);
        $this->assertSame(2, $report['products']);
        $this->assertSame(2, $report['tables'][0]['documents']);
        $this->assertSame(1, $report['queue']['waiting']);
        $this->assertFalse($report['outdated']);

        $why = $this->app->make(IndexStatus::class)->product($hidden->id);
        $this->assertFalse($why['published']);
        $this->assertTrue($why['tables']['en']['indexed']);
        $this->assertFalse($why['tables']['en']['published']);
        $this->assertNull($why['queued_since']);
        $this->assertTrue($this->app->make(IndexStatus::class)->product($kept->id)['deleted']);
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
