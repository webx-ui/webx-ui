<?php

declare(strict_types=1);

namespace WebxUi\Blog\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Blog\Models\Article;

/**
 * Scheduled publication, which is one column and no scheduler (§7).
 *
 * Every test in here uses a date in the future deliberately. An article dated next Tuesday is
 * "published" as far as `HasDraft` is concerned — `published_at` is not null — so a test that
 * only ever publishes at `now()` stays green against code that never checks the date at all.
 * That is the failure this file exists to catch.
 */
final class ScheduleTest extends TestCase
{
    #[Test]
    public function a_scheduled_article_is_a_404_and_is_not_in_the_feed(): void
    {
        $this->article('next-week', at: Carbon::now()->addWeek());

        $this->get('/blog/next-week')->assertNotFound();
        $this->get('/blog')->assertOk()->assertDontSee('Next week');
    }

    #[Test]
    public function the_same_article_answers_once_its_day_comes_round(): void
    {
        $article = $this->article('next-week', at: Carbon::now()->addWeek());

        $this->get('/blog/next-week')->assertNotFound();

        // Nothing is written and nothing runs: the answer changes because the question is asked
        // at a different moment.
        Carbon::setTestNow(Carbon::now()->addWeeks(2));

        $this->get('/blog/next-week')->assertOk();
        $this->get('/blog')->assertOk()->assertSee('Next week');

        $this->assertTrue($article->fresh()?->isPublished());
    }

    #[Test]
    public function a_scheduled_article_looks_published_to_the_frame_underneath(): void
    {
        $article = $this->article('next-week', at: Carbon::now()->addWeek());

        // The trap worth writing down: `module-admin` reads "published" as "stamped", so a
        // general count of live records elsewhere in the panel includes this one. Inside the
        // module everything goes through `published()` instead.
        $this->assertFalse($article->isPublished());
        $this->assertTrue($article->isScheduled());
        $this->assertSame(Article::STATUS_SCHEDULED, $article->status());
        $this->assertNotNull($article->published_at);
        $this->assertSame(0, Article::query()->published()->count());
    }

    #[Test]
    public function a_date_in_the_past_moves_the_article_down_the_feed(): void
    {
        $this->article('older', at: Carbon::now()->subMonth());
        $this->article('newer');

        $this->get('/blog')->assertOk()->assertSeeInOrder(['Newer', 'Older']);
    }

    #[Test]
    public function publishing_keeps_the_date_the_editor_chose(): void
    {
        $when = Carbon::now()->addDays(3)->startOfHour();

        $article = $this->article('next-week', published: false);
        $article->saveDraft(['title' => ['en' => 'Next week']]);
        $article->publish(at: $when);

        // The date cannot travel through the draft — `applyDraft()` skips the column on purpose
        // — so `publish(at:)` is the only way it reaches the row in one save, and one save is
        // what keeps the history from holding a version stamped with the wrong day.
        $this->assertSame($when->toDateTimeString(), $article->fresh()?->published_at?->toDateTimeString());
    }

    #[Test]
    public function an_article_that_was_pulled_is_not_a_draft(): void
    {
        $article = $this->article('changing-a-belt');

        $this->assertSame(Article::STATUS_PUBLISHED, $article->status());

        $article->unpublish();

        // Both have an empty `published_at`; only the history tells them apart, and an editor
        // needs it to — "draft" on something that was live this morning is a lie.
        $this->assertSame(Article::STATUS_UNPUBLISHED, $article->fresh()?->status());
        $this->assertSame(Article::STATUS_DRAFT, $this->article('never', published: false)->status());
    }
}
