<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Admin\Categories\CategoryException;
use WebxUi\Blog\Models\Rubric;

/**
 * Rubrics: the refusal to delete a full one (§6), and which of an article's rubrics is the
 * main one (§2.6).
 */
final class RubricTest extends TestCase
{
    #[Test]
    public function a_rubric_with_articles_in_it_refuses_to_be_deleted(): void
    {
        $rubric = $this->rubric('repairs');
        $this->article('changing-a-belt')->rubrics()->attach($rubric);

        $this->expectException(CategoryException::class);
        // The number is in the message because the editor's next move depends on it: one
        // article gets moved, forty means the rubric was the right idea after all.
        $this->expectExceptionMessageMatches('/\b1\b/');

        $rubric->delete();
    }

    #[Test]
    public function the_refused_rubric_is_still_there_afterwards(): void
    {
        $rubric = $this->rubric('repairs');
        $this->article('changing-a-belt')->rubrics()->attach($rubric);

        try {
            $rubric->delete();
        } catch (Throwable) {
            // The refusal itself is the test above. What matters here is that nothing was half
            // done on the way out of it.
        }

        $this->assertNotNull(Rubric::query()->find($rubric->getKey()));
        $this->get('/blog/repairs')->assertOk();
    }

    #[Test]
    public function an_empty_rubric_is_soft_deleted(): void
    {
        $rubric = $this->rubric('repairs');

        $this->assertTrue((bool) $rubric->delete());
        $this->assertNull(Rubric::query()->find($rubric->getKey()));
        $this->assertNotNull(Rubric::withTrashed()->find($rubric->getKey()));
        $this->get('/blog/repairs')->assertNotFound();
    }

    #[Test]
    public function a_rubric_emptied_first_can_then_be_deleted(): void
    {
        $rubric = $this->rubric('repairs');
        $article = $this->article('changing-a-belt');
        $article->rubrics()->attach($rubric);

        $article->rubrics()->detach($rubric);

        $this->assertTrue((bool) $rubric->fresh()?->delete());
    }

    #[Test]
    public function the_first_rubric_is_the_main_one_everywhere(): void
    {
        $repairs = $this->rubric('repairs');
        $news = $this->rubric('news');

        $article = $this->article('changing-a-belt');
        $article->rubrics()->attach([
            $news->getKey() => ['position' => 1],
            $repairs->getKey() => ['position' => 0],
        ]);

        $this->assertSame($repairs->getKey(), $article->fresh()?->mainRubric()?->getKey());

        // Reordering the list is the whole of "which is the main one": there is no second
        // switch, because one control beats two (§2.6).
        $article->rubrics()->updateExistingPivot($news->getKey(), ['position' => -1]);

        $this->assertSame($news->getKey(), $article->fresh()?->mainRubric()?->getKey());
    }

    #[Test]
    public function the_feed_lists_the_rubrics_in_the_order_the_panel_set(): void
    {
        $this->rubric('second')->update(['position' => 2]);
        $this->rubric('first')->update(['position' => 1]);
        $this->rubric('hidden', visible: false)->update(['position' => 0]);

        $response = $this->get('/blog');

        $response->assertOk();
        $response->assertSeeInOrder(['First', 'Second']);
        $response->assertDontSee('>Hidden<', false);
    }
}
