<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Sync\Limits;
use WebxUi\Delivery\Sync\Schedule;

final class PointScheduleTest extends TestCase
{
    private function schedule(): Schedule
    {
        return Schedule::fromArray([
            'timezone' => 'UTC',
            'week' => [
                'mon' => [['09:00', '18:00']],
                'tue' => [['09:00', '13:00'], ['14:00', '18:00']],
                'wed' => [['18:00', '09:00'], 'nonsense'],
                'sat' => [],
                'sun' => null,
            ],
            'exceptions' => [['date' => '2026-12-31', 'hours' => [['09:00', '15:00']]]],
            'text' => ['en' => 'Closed on public holidays'],
        ]) ?? $this->fail('No schedule.');
    }

    #[Test]
    public function a_point_is_open_within_its_hours(): void
    {
        $schedule = $this->schedule();

        // 2026-11-02 is a Monday, 2026-11-03 a Tuesday.
        $this->assertTrue($schedule->openAt(new DateTimeImmutable('2026-11-02 10:00 UTC')));
        $this->assertFalse($schedule->openAt(new DateTimeImmutable('2026-11-02 18:00 UTC')));
        $this->assertFalse($schedule->openAt(new DateTimeImmutable('2026-11-03 13:30 UTC')));
        $this->assertFalse($schedule->openAt(new DateTimeImmutable('2026-11-07 10:00 UTC')), 'Saturday: closed');
        $this->assertNull($schedule->openAt(new DateTimeImmutable('2026-11-08 10:00 UTC')), 'Sunday: the carrier did not say');
        $this->assertNull($schedule->openAt(new DateTimeImmutable('2026-11-06 10:00 UTC')), 'Friday: no key');
        $this->assertFalse($schedule->openAt(new DateTimeImmutable('2026-11-04 10:00 UTC')), 'Wednesday: a broken interval is dropped');
        $this->assertTrue($schedule->openAt(new DateTimeImmutable('2026-12-31 14:00 UTC')), 'Thursday 31st: the exception');
        $this->assertFalse($schedule->openAt(new DateTimeImmutable('2026-12-31 16:00 UTC')));
    }

    #[Test]
    public function today_is_said_in_a_few_words(): void
    {
        $schedule = $this->schedule();

        $this->assertSame('Until 18:00', $schedule->todayLabel('en', new DateTimeImmutable('2026-11-02 10:00 UTC')));
        $this->assertSame('Opens at 14:00', $schedule->todayLabel('en', new DateTimeImmutable('2026-11-03 13:30 UTC')));
        $this->assertSame('Closed', $schedule->todayLabel('en', new DateTimeImmutable('2026-11-07 10:00 UTC')));
        $this->assertSame('Closed on public holidays', $schedule->todayLabel('en', new DateTimeImmutable('2026-11-08 10:00 UTC')));
        $this->assertSame('Open 24 hours', (new Schedule(['mon' => [['00:00', '24:00']]]))->todayLabel('en', new DateTimeImmutable('2026-11-02 03:00 UTC')));
    }

    #[Test]
    public function the_hours_and_limits_keep_only_what_was_said(): void
    {
        $this->assertSame(['mon', 'tue', 'wed', 'sat', 'sun'], array_keys($this->schedule()->toArray()['week']));
        $this->assertSame([], $this->schedule()->week['wed']);
        $this->assertSame(['max_weight_g' => 30000, 'cod' => false], Limits::fromArray(['max_weight_g' => '30000', 'cod' => false, 'max_value' => 'a lot'])?->toArray());
    }
}
