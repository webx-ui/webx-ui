<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Models\Rubric;

/**
 * The blog's side of §17.5 of the SEO spec: feed → main rubric → article, printed once as a
 * `BreadcrumbList` and once as the crumbs a reader sees — and both change together when the main
 * rubric does. Plus the `BlogPosting` of an article and the `ItemList` a rubric page pushes.
 */
final class BreadcrumbsTest extends TestCase
{
    #[Test]
    public function a_site_can_switch_the_visible_trail_off_and_keep_the_breadcrumb_list(): void
    {
        config()->set('webx-blog.breadcrumbs', false);
        $article = $this->article('belts');
        $article->rubrics()->attach([$this->rubric('parts')->id => ['position' => 0]]);

        foreach (['/blog/belts', '/blog/parts'] as $url) {
            $page = (string) $this->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('webx-breadcrumbs', $page);
            $this->assertContains('Parts', $this->names($page));
        }
    }

    #[Test]
    public function an_article_page_prints_one_trail_twice(): void
    {
        $article = $this->article('belts');
        $article->rubrics()->attach([$this->rubric('parts')->id => ['position' => 0], $this->rubric('news')->id => ['position' => 1]]);

        $page = (string) $this->get('/blog/belts')->assertOk()->getContent();

        $this->assertSame(['Home', 'Blog', 'Parts', 'Belts'], $this->names($page));
        $this->assertSame(['Home', 'Blog', 'Parts', 'Belts'], $this->visible($page));
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/blog/belts">', $page);

        // The editor drags another rubric to the top: the main one changes, and both trails with it.
        $article->rubrics()->updateExistingPivot($this->rubricId('news'), ['position' => -1]);

        $page = (string) $this->get('/blog/belts')->getContent();

        $this->assertSame(['Home', 'Blog', 'News', 'Belts'], $this->names($page));
        $this->assertSame(['Home', 'Blog', 'News', 'Belts'], $this->visible($page));
    }

    #[Test]
    public function a_hidden_rubric_is_no_step_of_the_trail(): void
    {
        $this->article('belts')->rubrics()->attach([$this->rubric('secret', visible: false)->id => ['position' => 0]]);

        $page = (string) $this->get('/blog/belts')->getContent();

        $this->assertSame(['Home', 'Blog', 'Belts'], $this->names($page));
        $this->assertSame(['Home', 'Blog', 'Belts'], $this->visible($page));
    }

    #[Test]
    public function an_article_is_a_blog_posting(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00'));
        $this->article('belts');

        $posting = $this->block((string) $this->get('/blog/belts')->getContent(), 'BlogPosting');

        $this->assertSame('Belts', $posting['headline']);
        $this->assertSame('http://localhost/blog/belts', $posting['mainEntityOfPage']);
        $this->assertSame('2026-09-01T10:00:00+00:00', $posting['datePublished']);
        $this->assertSame('2026-09-01T10:00:00+00:00', $posting['dateModified']);
        $this->assertArrayNotHasKey('image', $posting);
    }

    #[Test]
    public function a_rubric_page_lists_its_articles_and_counts_on_from_the_pages_before(): void
    {
        config()->set('webx-blog.per_page', 2);

        $rubric = $this->rubric('parts');

        foreach (['one', 'two', 'three'] as $i => $slug) {
            Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00')->addDays($i));
            $this->article($slug)->rubrics()->attach([$rubric->id => ['position' => 0]]);
        }

        $list = $this->block((string) $this->get('/blog/parts')->getContent(), 'ItemList');

        $this->assertSame([1, 2], array_column($list['itemListElement'], 'position'));
        $this->assertSame(['http://localhost/blog/three', 'http://localhost/blog/two'], array_column($list['itemListElement'], 'url'));

        $second = $this->block((string) $this->get('/blog/parts?page=2')->getContent(), 'ItemList');

        $this->assertSame([3], array_column($second['itemListElement'], 'position'));
        $this->assertSame(['Home', 'Blog', 'Parts'], $this->names((string) $this->get('/blog/parts')->getContent()));
    }

    #[Test]
    public function a_tag_page_has_the_feed_above_it(): void
    {
        $this->tag('rubber', noindex: false);

        $page = (string) $this->get('/blog/tag/rubber')->assertOk()->getContent();

        $this->assertSame(['Home', 'Blog', 'Rubber'], $this->names($page));
        $this->assertSame(['Home', 'Blog', 'Rubber'], $this->visible($page));
    }

    #[Test]
    public function an_article_names_itself_in_every_language_it_is_written_in(): void
    {
        $this->useLocales('en', 'uk');

        $article = new Article(['title' => ['en' => 'Belts', 'uk' => 'Паски'], 'slug' => ['en' => 'belts', 'uk' => 'pasky']]);
        $article->save();
        $article->publish();

        $page = (string) $this->get('/uk/blog/pasky')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="http://localhost/blog/belts">', $page);
        $this->assertStringContainsString('<link rel="alternate" hreflang="uk" href="http://localhost/uk/blog/pasky">', $page);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="http://localhost/blog/belts">', $page);
        $this->assertSame(['Головна', 'Блог', 'Паски'], $this->names($page));

        // The feed has no entity, and is the same path under every prefix.
        $feed = (string) $this->get('/uk/blog')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="alternate" hreflang="en" href="http://localhost/blog">', $feed);
        $this->assertStringContainsString('<link rel="alternate" hreflang="uk" href="http://localhost/uk/blog">', $feed);
    }

    private function rubricId(string $slug): int
    {
        return (int) Rubric::query()->whereTranslation('slug', $slug)->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function block(string $page, string $type): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $page, $matches);

        foreach ($matches[1] as $json) {
            $block = (array) json_decode($json, true);

            if (($block['@type'] ?? null) === $type) {
                return $block;
            }
        }

        $this->fail("No {$type} on the page.");
    }

    /**
     * @return list<string>
     */
    private function names(string $page): array
    {
        return array_column($this->block($page, 'BreadcrumbList')['itemListElement'], 'name');
    }

    /**
     * @return list<string>
     */
    private function visible(string $page): array
    {
        preg_match('#<nav class="webx-breadcrumbs".*?</nav>#s', $page, $nav);
        preg_match_all('#<li[^>]*>(?:<a [^>]*>)?([^<]+)#', $nav[0] ?? '', $items);

        return array_map(static fn (string $text): string => html_entity_decode(trim($text)), $items[1]);
    }
}
