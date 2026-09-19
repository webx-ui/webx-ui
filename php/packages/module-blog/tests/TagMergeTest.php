<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blog\Models\Tag;
use WebxUi\Blog\Support\TagMerge;
use WebxUi\Seo\Models\SeoRedirect;

/**
 * Merging tags into one (§6).
 *
 * The test that matters is the first: an article that carried both tags has to come out
 * carrying one. `unique(article_id, tag_id)` is what guarantees that, which means a merge
 * written as an `update` on the pivot would not merge, it would crash.
 */
final class TagMergeTest extends TestCase
{
    #[Test]
    public function an_article_that_carried_both_tags_comes_out_carrying_one(): void
    {
        $keep = $this->tag('belts');
        $merged = $this->tag('belt');

        $both = $this->article('changing-a-belt');
        $both->tags()->attach([$keep->getKey(), $merged->getKey()]);

        $only = $this->article('choosing-a-belt');
        $only->tags()->attach($merged);

        $carried = $this->merge([$merged], $keep);

        $this->assertSame(2, $carried);
        $this->assertSame([$keep->getKey()], $both->fresh()?->tags->modelKeys());
        $this->assertSame([$keep->getKey()], $only->fresh()?->tags->modelKeys());
        $this->assertNull(Tag::query()->find($merged->getKey()));
    }

    #[Test]
    public function without_the_checkbox_the_old_address_simply_stops_answering(): void
    {
        $keep = $this->tag('belts');
        $merged = $this->tag('belt');

        $this->merge([$merged], $keep);

        $this->assertSame(0, SeoRedirect::query()->count());
        $this->get('/blog/tag/belt')->assertNotFound();
    }

    #[Test]
    public function with_the_checkbox_the_old_address_answers_301(): void
    {
        $keep = $this->tag('belts');
        $merged = $this->tag('belt');

        $this->merge([$merged], $keep, redirect: true);

        // An alias of `webx-ui/routing` would have died with the tag — it is a row keyed to the
        // entity. A redirect in `seo_redirects` is not, which is the whole point of the
        // checkbox writing one (§6).
        $this->get('/blog/tag/belt')->assertRedirect('/blog/tag/belts')->assertStatus(301);
    }

    #[Test]
    public function merging_several_at_once_leaves_one_tag_and_one_redirect_each(): void
    {
        $keep = $this->tag('belts');
        $first = $this->tag('belt');
        $second = $this->tag('drive-belts');

        $article = $this->article('changing-a-belt');
        $article->tags()->attach([$first->getKey(), $second->getKey()]);

        $this->merge([$first, $second], $keep, redirect: true);

        $this->assertSame([$keep->getKey()], $article->fresh()?->tags->modelKeys());
        $this->assertSame(2, SeoRedirect::query()->count());
        $this->assertSame(1, Tag::query()->count());
    }

    #[Test]
    public function merging_a_tag_into_itself_is_refused(): void
    {
        $keep = $this->tag('belts');

        $this->expectExceptionMessage('itself');

        $this->merge([$keep], $keep);
    }

    /**
     * @param  list<Tag>  $merged
     */
    private function merge(array $merged, Tag $keep, bool $redirect = false): int
    {
        return $this->app->make(TagMerge::class)->merge($merged, $keep, $redirect);
    }
}
