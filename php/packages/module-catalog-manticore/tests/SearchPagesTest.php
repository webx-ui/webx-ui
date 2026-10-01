<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Manticore\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Engine\Indexer;
use WebxUi\Catalog\Tests\TestCase;

/**
 * Decisions 16–17 and 21 of the Manticore spec where a person sees them: the storefront's search
 * says what it searched for instead and offers the words as typed, a code typed without its
 * dashes still goes to the card, and the panel shows the same correction and the one row.
 */
final class SearchPagesTest extends TestCase
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

    #[Test]
    public function the_storefront_says_what_it_searched_for_instead(): void
    {
        $this->product('Protective case for phone', $this->category('phones'));
        $this->settle();

        $this->get('/catalog/search?q=protectve')
            ->assertOk()
            ->assertSee('Protective case for phone')
            ->assertSee('Showing results for')
            ->assertSee('<strong>protective</strong>', false)
            ->assertSee('href="http://localhost/catalog/search?q=protectve&amp;typed=1"', false);

        // The words as typed: nothing found, and nothing corrected.
        $this->get('/catalog/search?q=protectve&typed=1')
            ->assertOk()
            ->assertDontSee('Protective case for phone')
            ->assertDontSee('Showing results for');
    }

    #[Test]
    public function a_code_typed_any_way_goes_to_the_card(): void
    {
        $plug = $this->product('Spark plug', $this->category('plugs'), ['sku' => 'AT-1234/56', 'slug' => 'spark-plug']);
        $this->settle();

        $this->get('/catalog/search?q=at123456')->assertStatus(302)->assertRedirect($plug->url());
        $this->get('/catalog/search?q='.urlencode('at 1234/56'))->assertStatus(302);
    }

    #[Test]
    public function the_panel_shows_the_correction_and_the_one_row_of_a_code(): void
    {
        $plugs = $this->category('plugs');
        $plug = $this->product('Spark plug', $plugs, ['sku' => 'AT-1234/56']);
        $this->product('Adapter for AT-1234/56', $plugs);
        $this->settle();
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')->getJson($this->api('products?q=AT-1234/56'))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $plug->id)
            ->assertJsonPath('corrected', null);

        $this->actingAs($editor, 'cms')->getJson($this->api('products?q=sprak'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $plug->id)
            ->assertJsonPath('corrected', 'spark');

        $this->actingAs($editor, 'cms')->getJson($this->api('products?q=sprak&typed=1'))
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('corrected', null);
    }

    private function settle(): void
    {
        $this->app->make(Indexer::class)->run();
    }
}
