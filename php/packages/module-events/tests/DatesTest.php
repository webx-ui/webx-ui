<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Events\Models\Event;

/**
 * What "to come" and "over" mean (decisions 3, 7) and that a moment survives the trip into the
 * column (decision 15) — in an application whose timezone is not UTC, because in UTC every
 * mistake about offsets cancels out (CLAUDE.md §4).
 */
final class DatesTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.timezone', 'Asia/Hong_Kong');
        date_default_timezone_set('Asia/Hong_Kong');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        date_default_timezone_set('UTC');
    }

    #[Test]
    public function the_ones_to_come_start_with_the_undated_and_the_past_ones_with_the_latest(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Hong_Kong'));

        $later = $this->event('later', '2026-10-20 10:00:00');
        $sooner = $this->event('sooner', '2026-10-12 10:00:00');
        $undated = $this->event('undated');
        // Began an hour ago and ends tonight: still to come — somebody may be on the way.
        $running = $this->event('running', '2026-10-10 11:00:00', attributes: ['ends_at' => '2026-10-10 20:00:00']);
        // Began an hour ago with no end: over from the moment it began.
        $begun = $this->event('begun', '2026-10-10 11:00:00');
        $old = $this->event('old', '2026-09-01 10:00:00');

        $this->assertSame(
            [$undated->id, $running->id, $sooner->id, $later->id],
            Event::query()->scopes(['upcoming'])->pluck('id')->all(),
        );
        $this->assertSame([$begun->id, $old->id], Event::query()->scopes(['past'])->pluck('id')->all());

        $this->assertFalse($undated->isPast());
        $this->assertFalse($running->isPast());
        $this->assertTrue($begun->isPast());
    }

    #[Test]
    public function a_moment_with_an_offset_is_kept_as_the_same_moment_in_the_application_zone(): void
    {
        $event = $this->event('class', '2026-10-12T10:00:00+03:00');

        // 10:00 in Moscow is 15:00 in Hong Kong, and the column holds the wall clock of the app.
        $this->assertSame('2026-10-12 15:00:00', $event->getRawOriginal('starts_at'));
        $this->assertSame('2026-10-12T15:00:00+08:00', $event->starts_at?->toAtomString());
        $this->assertTrue($event->starts_at->equalTo(Carbon::parse('2026-10-12T07:00:00Z')));
    }

    #[Test]
    public function an_event_of_days_covers_them_whole(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-12 23:00:00', 'Asia/Hong_Kong'));

        $today = $this->event('today', '2026-10-12 00:00:00', attributes: ['all_day' => true]);

        $this->assertSame('2026-10-12 00:00:00', $today->getRawOriginal('starts_at'));
        $this->assertSame('2026-10-12 23:59:59', $today->getRawOriginal('ends_at'));
        // An hour before midnight the day is not over.
        $this->assertFalse($today->isPast());
        $this->assertSame([$today->id], Event::query()->scopes(['upcoming'])->pluck('id')->all());
    }
}
