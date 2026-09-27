<?php

declare(strict_types=1);

namespace WebxUi\Press\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Pages\Models\Page;
use WebxUi\Press\PressServiceProvider;
use WebxUi\Routing\Models\Route;

/**
 * An outlet's page and who sees it (decision 7, §4.4, §4.7): an article is seen in a language when
 * it is titled in it; an outlet when it has an article seen there — and otherwise it is a 404 in
 * that language, absent from the sitemap and from the hreflang of its page.
 */
final class PageTest extends TestCase
{
    #[Test]
    public function the_page_lists_the_articles_in_their_own_order(): void
    {
        $this->outlet('Tatler', [
            $this->row('Later one', ['published_on' => '2024-01-01', 'kind' => 'interview']),
            $this->row('Earlier one', ['published_on' => '2023-08-12', 'date_precision' => 'month', 'excerpt' => ['en' => 'About the clinic.']]),
        ], values: ['summary' => ['en' => 'A magazine.'], 'website_url' => 'https://tatler.example']);

        $page = (string) $this->get('/press/tatler')->assertOk()->getContent();

        $this->assertLessThan(strpos($page, 'Earlier one'), strpos($page, 'Later one'), 'the order of the rows, not the date');
        $this->assertStringContainsString('<h1>Tatler</h1>', $page);
        $this->assertStringContainsString('A magazine.', $page);
        $this->assertStringContainsString('Articles: 2', $page);
        $this->assertStringContainsString('href="https://tatler.example" target="_blank" rel="noopener"', $page);
        $this->assertStringContainsString('href="https://news.example/later-one" target="_blank" rel="noopener">Later one</a>', $page);
        $this->assertStringContainsString('Interview', $page);
        $this->assertStringContainsString('<time datetime="2023-08-12">August 2023</time>', $page);
        $this->assertStringContainsString('About the clinic.', $page);
        $this->assertStringContainsString('<title>Tatler</title>', $page);
        $this->assertStringContainsString('<meta name="description" content="A magazine.">', $page);
    }

    #[Test]
    public function a_pdf_is_where_the_title_leads_or_a_second_link(): void
    {
        $scan = $this->file();
        $this->outlet('Tatler', [
            ['title' => ['en' => 'Only a scan'], 'file' => ['path' => $scan->path]],
            $this->row('Both', ['file' => ['path' => $scan->path]]),
        ]);

        $page = (string) $this->get('/press/tatler')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<a href="[^"]*/scan\.pdf[^"]*" target="_blank" rel="noopener">Only a scan</a>\s*<span class="wx-press-article__pdf">PDF</span>#', $page);
        $this->assertStringContainsString('href="https://news.example/both" target="_blank" rel="noopener">Both</a>', $page);
        $this->assertMatchesRegularExpression('#Read the article</a>\s*· <a href="[^"]*/scan\.pdf#', $page);
    }

    #[Test]
    public function an_article_without_a_title_in_the_language_is_not_seen_in_it(): void
    {
        $this->outlet('Tatler', [
            $this->row('English only'),
            $this->row('Both', ['title' => ['en' => 'Both', 'ru' => 'Обе']]),
        ]);

        $this->get('/press/tatler')->assertOk()->assertSee('English only')->assertSee('Both');
        $this->get('/ru/press/tatler')->assertOk()->assertSee('Обе')->assertDontSee('English only')->assertSee('Материалов: 1');
    }

    #[Test]
    public function an_outlet_with_nothing_to_show_in_a_language_is_404_there_and_out_of_the_sitemap_and_hreflang(): void
    {
        $this->outlet('English', [$this->row('English only')]);
        $both = $this->outlet('Both', [$this->row('Both', ['title' => ['en' => 'Both', 'ru' => 'Обе']])]);

        $this->get('/press/english')->assertOk();
        $this->get('/ru/press/english')->assertNotFound();
        $this->assertNull(Route::query()->where('path', 'press/english')->where('locale', 'ru')->first(), 'no address in Russian at all');

        $sitemap = (string) $this->get('/sitemap-press-outlet.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/press/english<', $sitemap);
        $this->assertStringNotContainsString('/ru/press/english<', $sitemap);
        $this->assertStringContainsString('/ru/press/both<', $sitemap);

        $this->assertStringNotContainsString('hreflang="ru"', (string) $this->get('/press/english')->getContent());
        $this->assertStringContainsString('hreflang="ru"', (string) $this->get('/press/both')->getContent());

        // The first Russian article gives it the address; hiding it takes the address away again.
        $this->assertSame('both', $both->getTranslation('slug', 'ru'));
    }

    #[Test]
    public function the_address_follows_the_articles(): void
    {
        $outlet = $this->outlet('Tatler', [$this->row('English only')]);
        $this->get('/ru/press/tatler')->assertNotFound();

        $article = $outlet->articles()->firstOrFail();
        $article->setTranslation('title', 'ru', 'По-русски');
        $article->save();

        $this->get('/ru/press/tatler')->assertOk()->assertSee('По-русски');

        $article->is_hidden = true;
        $article->save();

        $this->get('/ru/press/tatler')->assertNotFound();
        $this->get('/press/tatler')->assertNotFound();
    }

    #[Test]
    public function the_name_is_taken_from_any_language_that_has_it(): void
    {
        $this->outlet('Tatler', [$this->row('Both', ['title' => ['en' => 'Both', 'ru' => 'Обе']])]);

        $this->get('/ru/press/tatler')->assertOk()->assertSee('<h1>Tatler</h1>', false);
    }

    #[Test]
    public function an_unpublished_outlet_and_one_in_the_bin_are_404(): void
    {
        $draft = $this->outlet('Draft', [$this->row('One')], published: false);
        $binned = $this->outlet('Binned', [$this->row('One')]);

        $this->get('/press/draft')->assertNotFound();
        $this->assertStringNotContainsString('/press/draft<', (string) $this->get('/sitemap-press-outlet.xml')->getContent());

        $binned->delete();
        $this->get('/press/binned')->assertNotFound();

        $binned->restore();
        $this->get('/press/binned')->assertOk();

        $draft->published = true;
        $draft->save();
        $this->get('/press/draft')->assertOk();
    }

    #[Test]
    public function the_trail_goes_through_the_page_at_the_prefix_when_there_is_one(): void
    {
        $this->outlet('Tatler', [$this->row('One')]);

        $this->assertStringNotContainsString('"name":"Press"', $this->trail());

        $home = Page::home();
        $this->assertInstanceOf(Page::class, $home);
        $page = new Page(['title' => 'Press', 'slug' => 'press']);
        $page->appendTo($home);
        $page->save();
        $page->publish();

        $this->assertStringContainsString('"name":"Press"', $this->trail());
    }

    #[Test]
    public function an_empty_prefix_is_refused(): void
    {
        config()->set('webx-press.prefix', '/');

        $this->expectExceptionMessage('webx-press.prefix is empty');

        (new PressServiceProvider($this->app))->boot();
    }

    #[Test]
    public function a_menu_can_point_at_an_outlet(): void
    {
        $this->outlet('Tatler', [$this->row('One')], values: ['website_url' => 'https://www.tatler.example/asia']);
        $this->outlet('Draft', [$this->row('One')], published: false);

        $found = $this->actingAs($this->editor(), 'cms')->getJson('/api/cms/links/search?type=press-outlet&q=')->assertOk()->json('data');

        $this->assertSame(['Tatler', 'Draft'], array_column($found, 'title'));
        $this->assertSame([true, false], array_column($found, 'available'));
        $this->assertSame('www.tatler.example', $found[0]['hint']);
    }

    private function trail(): string
    {
        $page = (string) $this->get('/press/tatler')->assertOk()->getContent();
        preg_match('#<script type="application/ld\+json">(\{"@context":"https://schema.org","@type":"BreadcrumbList".*?)</script>#s', $page, $match);

        return $match[1] ?? '';
    }
}
