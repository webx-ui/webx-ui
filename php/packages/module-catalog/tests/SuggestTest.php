<?php

declare(strict_types=1);

namespace WebxUi\Catalog\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Catalog\Models\Category;

/**
 * WIDGETS §18, W6.5: the catalogue's search suggests while typing — `/catalog/suggest?q=` answers
 * the HTML of `webx-catalog::suggest` for `<x-webx-suggest>`, and the search page's box is that
 * widget, a plain GET search without JavaScript.
 */
final class SuggestTest extends TestCase
{
    private Category $laptops;

    protected function setUp(): void
    {
        parent::setUp();

        $this->laptops = $this->category('laptops');
    }

    #[Test]
    public function the_address_answers_the_products_found_with_their_count_and_a_link_to_all(): void
    {
        $this->app['config']->set('webx-catalog.suggest.limit', 2);
        $this->product('ThinkPad E14', $this->laptops);
        $this->product('ThinkPad T14', $this->laptops, ['price' => 999]);
        $this->product('ThinkPad X1', $this->laptops, ['price' => 1299, 'old_price' => 1499]);
        $this->product('MacBook', $this->laptops, ['price' => 1999]);
        $this->product('ThinkPad Hidden', $this->laptops, ['is_published' => false]);

        $response = $this->get('/catalog/suggest?q=thinkpad')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $html = (string) $response->getContent();

        // A section with a heading the widget names the group by, its count of all found.
        $this->assertStringContainsString('<div class="webx-catalog-suggest" data-webx-section="products">', $html);
        $this->assertStringContainsString('<p class="webx-catalog-suggest__heading" data-webx-heading>Products (3)</p>', $html);
        // Two cards — the limit — each a link to choose, with its price; the hidden one is not found.
        $this->assertSame(2, substr_count($html, 'class="webx-catalog-suggest__product"'));
        $this->assertMatchesRegularExpression('~<a class="webx-catalog-suggest__product" href="[^"]+" data-webx-option>\s*<span class="webx-catalog-suggest__name">ThinkPad~', $html);
        $this->assertMatchesRegularExpression('~<s>1499(\.00)?</s>\s*<strong>1299(\.00)?</strong>~', $html);
        $this->assertStringNotContainsString('Hidden', $html);
        $this->assertStringNotContainsString('MacBook', $html);
        // «See all» — the search page for the same words, an option too.
        $this->assertStringContainsString('<a class="webx-catalog-suggest__all" href="http://localhost/catalog/search?q=thinkpad" data-webx-option>See all results: 3</a>', $html);
        // A fragment, not a page: no layout, no assets.
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    #[Test]
    public function nothing_found_is_said_in_the_sites_words_without_an_option(): void
    {
        $this->product('MacBook', $this->laptops);

        $html = (string) $this->get('/catalog/suggest?q='.urlencode('<zebra>'))->assertOk()->getContent();

        $this->assertStringContainsString('<p class="webx-catalog-suggest__nothing">Nothing found for “&lt;zebra&gt;”.</p>', $html);
        $this->assertStringNotContainsString('data-webx-option', $html);
    }

    #[Test]
    public function an_empty_question_has_no_answer(): void
    {
        $this->get('/catalog/suggest?q=+')->assertNoContent();
        $this->get('/catalog/suggest')->assertNoContent();
    }

    #[Test]
    public function the_words_follow_the_language(): void
    {
        $this->app->setLocale('ru');
        $html = view('webx-catalog::suggest', [
            'term' => 'think', 'products' => new Collection([$this->product('ThinkPad', $this->laptops)]), 'total' => 7,
            'corrected' => 'thinkpad', 'all' => '/ru/catalog/search?q=thinkpad', 'priced' => false,
        ])->render();

        $this->assertStringContainsString('Товары (7)', $html);
        $this->assertStringContainsString('Показаны результаты для thinkpad.', $html);
        $this->assertStringContainsString('>Все результаты: 7</a>', $html);
        $this->assertStringNotContainsString('webx-catalog-suggest__price', $html);
        $this->assertStringContainsString('По запросу «зебра» ничего не найдено.', view('webx-catalog::suggest', [
            'term' => 'зебра', 'products' => new Collection, 'total' => 0, 'corrected' => null, 'all' => '/', 'priced' => true,
        ])->render());
    }

    #[Test]
    public function the_search_page_box_is_the_widget_and_a_plain_get_search_without_javascript(): void
    {
        $this->product('ThinkPad X1', $this->laptops);

        $html = (string) $this->get('/catalog/search?q=thinkpad')->assertOk()->getContent();

        $this->assertStringContainsString('<form method="get" action="http://localhost/catalog/search" role="search" data-webx-suggest="http://localhost/catalog/suggest" data-param="q"', $html);
        $this->assertStringContainsString('class="webx-suggest webx-catalog-search__box"', $html);
        $this->assertMatchesRegularExpression('~<input class="webx-suggest__input" type="search" id="suggest-1" name="q" value="thinkpad"\s+placeholder="Name or article number"~', $html);
        $this->assertStringContainsString('<span class="webx-suggest__submit-text">Find</span>', $html);

        $empty = (string) $this->get('/catalog/search')->assertOk()->getContent();
        $this->assertStringContainsString('data-webx-suggest="http://localhost/catalog/suggest"', $empty);
        $this->assertStringContainsString('name="q" value=""', $empty);
    }

    #[Test]
    #[DefineEnvironment('withRoot')]
    public function the_root_of_the_catalogue_does_not_take_the_address(): void
    {
        $this->product('Pixel', $this->laptops);

        $this->get('/catalog/suggest?q=pixel')->assertOk()->assertSee('data-webx-option', false);
    }

    /**
     * @param  Application  $app
     */
    protected function withRoot($app): void
    {
        $app['config']->set('webx-catalog.root.enabled', true);
    }
}
