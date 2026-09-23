<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Contracts\LinkSource;
use WebxUi\Admin\Links\LinkCandidate;
use WebxUi\Admin\Links\LinkSources;
use WebxUi\Blog\Links\ArticleLinkSource;
use WebxUi\Blog\Links\RubricLinkSource;
use WebxUi\Blog\Links\TagLinkSource;

/**
 * The blog's three things a link can point at (§13 of the menu spec).
 */
final class LinkSourcesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['url']->forceRootUrl('https://example.test');
        $this->app['url']->forceScheme('https');
    }

    #[Test]
    public function the_module_registers_all_three(): void
    {
        $sources = $this->app->make(LinkSources::class);
        $articles = $sources->find('article');
        $rubrics = $sources->find('rubric');

        $this->assertInstanceOf(ArticleLinkSource::class, $articles);
        $this->assertInstanceOf(RubricLinkSource::class, $rubrics);
        $this->assertInstanceOf(TagLinkSource::class, $sources->find('tag'));

        $this->assertSame('blog.articles.view', $articles->permission());
        $this->assertSame('blog.taxonomy.manage', $rubrics->permission());
    }

    #[Test]
    public function an_article_on_the_site_is_available(): void
    {
        $article = $this->article('how-we-work');

        $candidate = $this->resolve($this->articles(), $article->getKey());

        $this->assertSame('How we work', $candidate->title);
        $this->assertSame('https://example.test/blog/how-we-work', $candidate->url);
        $this->assertTrue($candidate->available);
    }

    /**
     * §7 of the blog spec: a stamp is not the same as being on the site. One dated next week has an
     * address in the registry and nothing at it, so `available` has to go through the article.
     */
    #[Test]
    public function a_scheduled_article_has_an_address_and_is_not_available(): void
    {
        $article = $this->article('later', published: false, at: Carbon::now()->addWeek());

        $candidate = $this->resolve($this->articles(), $article->getKey());

        $this->assertSame('https://example.test/blog/later', $candidate->url);
        $this->assertFalse($candidate->available);
    }

    #[Test]
    public function a_draft_article_is_offered_and_marked(): void
    {
        $article = $this->article('draft', published: false);

        $this->assertFalse($this->resolve($this->articles(), $article->getKey())->available);
    }

    #[Test]
    public function the_hint_of_an_article_is_the_rubrics_it_is_filed_under(): void
    {
        $article = $this->article('how-we-work');
        $article->rubrics()->attach([$this->rubric('repairs')->getKey(), $this->rubric('advice')->getKey()]);

        $this->assertSame('Repairs, Advice', $this->resolve($this->articles(), $article->getKey())->hint);
    }

    /** An article in no rubric still has the other thing a blog is sorted by. */
    #[Test]
    public function an_article_in_no_rubric_falls_back_to_its_date(): void
    {
        $article = $this->article('how-we-work');

        $this->assertSame(
            $article->published_at?->toDateString(),
            $this->resolve($this->articles(), $article->getKey())->hint,
        );
    }

    #[Test]
    public function articles_are_searched_by_name_and_slug(): void
    {
        $this->article('how-we-work');
        $this->article('pricing');

        $this->assertSame(['How we work'], $this->titles($this->articles()->search('we work', 'en', 20)));
        $this->assertSame(['Pricing'], $this->titles($this->articles()->search('pricing', 'en', 20)));
    }

    #[Test]
    public function a_visible_rubric_is_available_and_a_hidden_one_is_not(): void
    {
        $shown = $this->rubric('repairs');
        $hidden = $this->rubric('drafts', visible: false);

        $this->assertTrue($this->resolve($this->rubrics(), $shown->getKey())->available);
        $this->assertSame('https://example.test/blog/repairs', $this->resolve($this->rubrics(), $shown->getKey())->url);
        $this->assertFalse($this->resolve($this->rubrics(), $hidden->getKey())->available);
    }

    /**
     * A tag is a word: nothing to publish and nothing to hide, so an address is the whole of it —
     * `noindex` deliberately does not come into it, because linking to a `noindex` page from a menu
     * is an ordinary thing to do.
     */
    #[Test]
    public function a_tag_is_available_as_soon_as_it_has_an_address(): void
    {
        $tag = $this->tag('remont');

        $candidate = $this->resolve($this->tags(), $tag->getKey());

        $this->assertSame('https://example.test/blog/tag/remont', $candidate->url);
        $this->assertTrue($candidate->available);
    }

    #[Test]
    public function a_tag_with_no_slug_in_this_language_has_no_address(): void
    {
        $this->useLocales('en', 'uk');

        $tag = $this->tag('remont');

        $candidate = $this->resolve($this->tags(), $tag->getKey(), 'uk');

        $this->assertNull($candidate->url);
        $this->assertFalse($candidate->available);
    }

    #[Test]
    public function resolving_an_id_that_is_gone_leaves_out_the_key(): void
    {
        $article = $this->article('how-we-work');

        $resolved = $this->articles()->resolve([(int) $article->getKey(), 9999], 'en');

        $this->assertArrayHasKey((int) $article->getKey(), $resolved);
        $this->assertArrayNotHasKey(9999, $resolved);
    }

    #[Test]
    public function an_article_in_the_bin_is_not_offered(): void
    {
        $article = $this->article('how-we-work');
        $article->delete();

        $this->assertSame([], $this->articles()->resolve([(int) $article->getKey()], 'en'));
        $this->assertSame([], $this->articles()->search('', 'en', 20));
    }

    private function articles(): ArticleLinkSource
    {
        return $this->app->make(ArticleLinkSource::class);
    }

    private function rubrics(): RubricLinkSource
    {
        return $this->app->make(RubricLinkSource::class);
    }

    private function tags(): TagLinkSource
    {
        return $this->app->make(TagLinkSource::class);
    }

    private function resolve(LinkSource $source, int|string $id, string $locale = 'en'): LinkCandidate
    {
        $candidate = $source->resolve([(int) $id], $locale)[(int) $id] ?? null;

        $this->assertInstanceOf(LinkCandidate::class, $candidate);

        return $candidate;
    }

    /**
     * @param  list<LinkCandidate>  $candidates
     * @return list<string>
     */
    private function titles(array $candidates): array
    {
        return array_map(static fn (LinkCandidate $candidate): string => $candidate->title, $candidates);
    }
}
