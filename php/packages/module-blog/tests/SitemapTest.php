<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Seo\Models\SeoUrl;
use WebxUi\Seo\Panel\UrlMatcher;

/**
 * The blog in the sitemap (§17.5 of the SEO spec): the same answer as the handler, and not a
 * line about the blog in the sitemap's code.
 */
final class SitemapTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
    }

    #[Test]
    public function a_scheduled_article_is_not_in_the_map_and_arrives_when_its_day_does(): void
    {
        $this->article('today');
        $this->article('next-week', at: Carbon::now()->addWeek());

        $map = $this->map('article');

        $this->assertStringContainsString('/blog/today<', $map);
        $this->assertStringNotContainsString('next-week', $map);

        // Nothing is saved when the date comes: the map finds out when what it built runs out.
        Carbon::setTestNow(Carbon::now()->addWeeks(2));

        $this->assertStringContainsString('/blog/next-week<', $this->map('article'));
    }

    #[Test]
    public function the_map_and_the_handler_give_the_same_answer(): void
    {
        $this->article('draft', published: false);
        $this->article('waiting', at: Carbon::now()->addDay());
        $this->article('binned')->delete();
        $this->rubric('hidden', visible: false);
        $this->rubric('shown');

        $articles = $this->map('article');
        $rubrics = $this->map('rubric');

        foreach (['draft', 'waiting'] as $slug) {
            $this->get("/blog/{$slug}")->assertNotFound();
            $this->assertStringNotContainsString("/blog/{$slug}<", $articles);
        }

        $this->assertStringNotContainsString('binned', $articles);

        $this->get('/blog/hidden')->assertNotFound();
        $this->assertStringNotContainsString('/blog/hidden<', $rubrics);

        $this->get('/blog/shown')->assertOk();
        $this->assertStringContainsString('/blog/shown<', $rubrics);
    }

    #[Test]
    public function a_tag_is_in_the_map_exactly_when_its_page_is_open_to_the_index(): void
    {
        $this->tag('flagged');
        $this->tag('ruled');
        $this->tag('open', noindex: false);

        // A rule that says nothing about robots still opens the page (§12) — and the map hears
        // that from the resolver, not from a rule of its own about tags.
        SeoUrl::query()->create(['match_type' => UrlMatcher::EXACT, 'pattern' => '/blog/tag/ruled', 'title' => ['en' => 'Ruled']]);

        $map = $this->map('tag');

        $this->assertStringNotContainsString('flagged', $map);
        $this->assertStringContainsString('/blog/tag/ruled<', $map);
        $this->assertStringContainsString('/blog/tag/open<', $map);
    }

    #[Test]
    public function the_feed_is_in_the_map_though_the_registry_has_no_row_for_it(): void
    {
        $this->assertStringContainsString('<loc>http://localhost/blog</loc>', $this->map('routes'));
    }

    #[Test]
    public function an_article_names_its_last_publication_as_lastmod(): void
    {
        // Backdated when first published: the reader is shown January, and the text reached the
        // site in March — which is what a crawler is asking about.
        Carbon::setTestNow(Carbon::parse('2026-03-01 09:00:00'));
        $article = $this->article('backdated', at: Carbon::parse('2026-01-10 10:00:00'));

        $this->assertStringContainsString('<lastmod>2026-03-01T09:00:00+00:00</lastmod>', $this->map('article'));

        // Republished under the same date: the text changed again.
        Carbon::setTestNow(Carbon::parse('2026-09-20 12:00:00'));
        $article->publish(at: Carbon::parse('2026-01-10 10:00:00'));

        $this->assertStringContainsString('<lastmod>2026-09-20T12:00:00+00:00</lastmod>', $this->map('article'));
    }

    private function map(string $file): string
    {
        $response = $this->get("/sitemap-{$file}.xml");

        return $response->getStatusCode() === 404 ? '' : (string) $response->getContent();
    }
}
