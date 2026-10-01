<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\DoctorChecks;
use WebxUi\Catalog\Catalog;
use WebxUi\Catalog\Engine\CatalogQuery;
use WebxUi\Catalog\Manticore\CatalogUnavailable;
use WebxUi\Catalog\Manticore\Doctor\ManticoreCheck;
use WebxUi\Catalog\Manticore\Manticore;
use WebxUi\Catalog\Manticore\ManticoreEngine;
use WebxUi\Catalog\Manticore\TablePrefix;
use WebxUi\Catalog\Tests\TestCase;

/**
 * Decisions 3 and 10–14 of the Manticore spec, with no server at all: the panel and a small
 * catalogue fall back on the database, a large one answers 503 with `Retry-After`, the failure is
 * remembered so nobody waits for it twice, and without a prefix the engine does not start.
 */
final class OutageTest extends TestCase
{
    use UsesManticore;

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->useManticore($app);
        // A port nobody listens on: refused at once.
        $app['config']->set('webx-catalog-manticore.host', '127.0.0.1');
        $app['config']->set('webx-catalog-manticore.port', 9);
    }

    #[Test]
    public function the_panel_and_a_small_catalogue_fall_back_on_the_database(): void
    {
        $laptops = $this->category('laptops');
        $product = $this->product('ThinkPad', $laptops);

        $engine = $this->engine();

        $this->assertSame([$product->id], $engine->search(new CatalogQuery(locale: 'en'))->ids);
        $this->assertTrue($engine->fellBack());
        $this->assertSame([$product->id], $engine->search(new CatalogQuery(locale: 'en', context: 'panel', withUnpublished: true))->ids);
        $this->assertTrue($engine->fellBack());
    }

    #[Test]
    public function a_large_catalogue_answers_503_and_the_failure_is_remembered(): void
    {
        $this->app['config']->set('webx-catalog.sql_engine_limit', 0);
        $this->product('ThinkPad', $this->category('laptops'));
        $engine = $this->engine();

        try {
            $engine->search(new CatalogQuery(locale: 'en'));
            $this->fail('A catalogue past the limit must not fall back on the database.');
        } catch (CatalogUnavailable $unavailable) {
            $this->assertSame(503, $unavailable->getStatusCode());
            $this->assertLessThanOrEqual(30, (int) $unavailable->getHeaders()['Retry-After']);
        }

        $this->assertTrue($this->app->make(Manticore::class)->down());

        // Within the half-minute nobody asks the server again.
        Http::fake();
        $this->assertSame([], $engine->search(new CatalogQuery(locale: 'en', context: 'panel', withUnpublished: true, search: 'nothing'))->ids);
        Http::assertNothingSent();
    }

    #[Test]
    public function a_question_that_falls_into_the_swap_is_asked_once_more(): void
    {
        $this->app['config']->set('webx-catalog-manticore.swap_wait', 0);
        $asked = 0;
        $empty = [['columns' => [], 'data' => [], 'total' => 0, 'error' => '', 'warning' => '']];

        // Between `DROP` and `RENAME` the table is not there; a moment later it is.
        Http::fake(static function (Request $request) use (&$asked, $empty) {
            parse_str($request->body(), $form);

            if (str_starts_with((string) ($form['query'] ?? ''), 'SELECT id') && $asked++ === 0) {
                return Http::response([['total' => 0, 'error' => "unknown local table(s) 'shop_catalog_products_en' in search request", 'warning' => '']]);
            }

            return Http::response($empty);
        });

        $this->assertSame([], $this->engine()->search(new CatalogQuery(locale: 'en'))->ids);
        $this->assertSame(2, $asked);
        $this->assertFalse($this->engine()->fellBack());
    }

    #[Test]
    public function the_storefront_answers_503_on_the_sites_own_page(): void
    {
        $this->app['config']->set('webx-catalog.sql_engine_limit', 0);
        $this->product('ThinkPad', $this->category('laptops'));

        $this->get('/laptops')
            ->assertStatus(503)
            ->assertHeader('Retry-After')
            ->assertSee('<!doctype html>', false)
            ->assertSee('The catalogue is temporarily unavailable')
            ->assertSee('noindex', false)
            ->assertDontSee('ThinkPad');

        $this->getJson('/laptops')->assertStatus(503)->assertJsonPath('message', 'The catalogue is temporarily unavailable.');
    }

    #[Test]
    public function without_a_prefix_the_engine_does_not_start_and_the_doctor_says_why(): void
    {
        $this->app['config']->set('webx-catalog-manticore.table_prefix', null);

        try {
            $this->app->make(Manticore::class)->table('en');
            $this->fail('No prefix, no table.');
        } catch (LogicException $missing) {
            $this->assertStringContainsString('MANTICORE_TABLE_PREFIX', $missing->getMessage());
        }

        $diagnoses = $this->app->make(ManticoreCheck::class)->run();
        $this->assertCount(1, $diagnoses);
        $this->assertTrue($diagnoses[0]->failed());
        $this->assertContains(ManticoreCheck::class, $this->app->make(DoctorChecks::class)->all());
    }

    #[Test]
    public function a_prefix_is_lower_case_letters_digits_and_underscores(): void
    {
        $this->assertSame('webx_cms_local', TablePrefix::of('webx_cms_local'));
        $this->assertSame('wxtest', TablePrefix::of('wxtest_'));

        $this->expectException(LogicException::class);
        TablePrefix::of('Shop-1');
    }

    #[Test]
    public function an_unreachable_server_is_reported_by_the_doctor(): void
    {
        $diagnoses = $this->app->make(ManticoreCheck::class)->run();

        $this->assertTrue($diagnoses[0]->failed());
    }

    private function engine(): ManticoreEngine
    {
        $engine = $this->app->make(Catalog::class)->engine();
        $this->assertInstanceOf(ManticoreEngine::class, $engine);

        return $engine;
    }
}
