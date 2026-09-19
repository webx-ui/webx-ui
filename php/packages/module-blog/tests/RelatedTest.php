<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blog\Models\Article;
use WebxUi\Blog\Rendering\Related;

/**
 * What to read next (§8): the pinned list first, the worked-out ones under it.
 */
final class RelatedTest extends TestCase
{
    #[Test]
    public function the_pinned_ones_come_first_and_in_the_order_they_were_dragged_into(): void
    {
        $article = $this->article('changing-a-belt');
        $first = $this->article('first-pick');
        $second = $this->article('second-pick');

        $article->related()->attach([
            $second->getKey() => ['position' => 1],
            $first->getKey() => ['position' => 0],
        ]);

        $found = $this->related($article);

        $this->assertSame([$first->getKey(), $second->getKey()], $found->modelKeys());
    }

    #[Test]
    public function the_articles_with_the_most_tags_in_common_are_filled_in_first(): void
    {
        config()->set('webx-blog.related', 2);

        $belts = $this->tag('belts');
        $repairs = $this->tag('repairs');

        $article = $this->article('changing-a-belt');
        $article->tags()->attach([$belts->getKey(), $repairs->getKey()]);

        $one = $this->article('one-tag-in-common');
        $one->tags()->attach($belts);

        $two = $this->article('two-tags-in-common');
        $two->tags()->attach([$belts->getKey(), $repairs->getKey()]);

        $found = $this->related($article);

        // Tags before rubrics because they are the sharper signal: two articles sharing two
        // tags are about the same thing, while two in "News" share a filing cabinet.
        $this->assertSame([$two->getKey(), $one->getKey()], $found->modelKeys());
    }

    #[Test]
    public function an_article_in_the_same_rubric_counts_when_no_tags_do(): void
    {
        $rubric = $this->rubric('repairs');

        $article = $this->article('changing-a-belt');
        $article->rubrics()->attach($rubric);

        $sibling = $this->article('changing-a-filter');
        $sibling->rubrics()->attach($rubric);

        $this->article('nothing-in-common');

        $this->assertSame([$sibling->getKey()], $this->related($article)->modelKeys());
    }

    #[Test]
    public function nothing_unpublished_scheduled_or_already_pinned_is_suggested(): void
    {
        $belts = $this->tag('belts');

        $article = $this->article('changing-a-belt');
        $article->tags()->attach($belts);

        $pinned = $this->article('pinned-by-hand');
        $pinned->tags()->attach($belts);
        $article->related()->attach($pinned);

        $draft = $this->article('a-draft', published: false);
        $draft->tags()->attach($belts);

        $later = $this->article('next-week', at: Carbon::now()->addWeek());
        $later->tags()->attach($belts);

        $found = $this->related($article);

        // The pinned one exactly once, itself never, and nothing a reader cannot open.
        $this->assertSame([$pinned->getKey()], $found->modelKeys());
    }

    #[Test]
    public function a_limit_of_zero_leaves_the_pinned_list_alone(): void
    {
        config()->set('webx-blog.related', 0);

        $belts = $this->tag('belts');

        $article = $this->article('changing-a-belt');
        $article->tags()->attach($belts);

        $sibling = $this->article('same-tag');
        $sibling->tags()->attach($belts);

        $this->assertSame([], $this->related($article)->modelKeys());

        $article->related()->attach($sibling);

        $this->assertSame([$sibling->getKey()], $this->related($article->fresh())->modelKeys());
    }

    /**
     * @return Collection<int, Article>
     */
    private function related(Article $article): Collection
    {
        return $this->app->make(Related::class)->for($article);
    }
}
