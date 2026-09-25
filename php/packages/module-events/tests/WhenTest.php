<?php

declare(strict_types=1);

namespace WebxUi\Events\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Events\Models\Event;
use WebxUi\Events\Rendering\When;

/**
 * Every line of the table in §4.6, in two languages: one helper says when an event is, for the
 * page, the cards, the panel and an agent alike.
 */
final class WhenTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string|null, 1: string|null, 2: bool, 3: string, 4: string}>
     */
    public static function dates(): iterable
    {
        yield 'no date' => [null, null, false, '', ''];
        yield 'one day, a time and an end' => ['2026-10-12 10:00:00', '2026-10-12 12:30:00', false, '12 October 2026, 10:00–12:30', '12 октября 2026, 10:00–12:30'];
        yield 'one day, a time' => ['2026-10-12 10:00:00', null, false, '12 October 2026, 10:00', '12 октября 2026, 10:00'];
        yield 'one day of an event of days' => ['2026-10-12 00:00:00', null, true, '12 October 2026', '12 октября 2026'];
        yield 'days in one month' => ['2026-10-12 00:00:00', '2026-10-14 00:00:00', true, '12–14 October 2026', '12–14 октября 2026'];
        yield 'days across a month' => ['2026-09-30 00:00:00', '2026-10-02 00:00:00', true, '30 September – 2 October 2026', '30 сентября – 2 октября 2026'];
        yield 'days across a year' => ['2026-12-30 18:00:00', '2027-01-02 12:00:00', false, '30 December 2026 – 2 January 2027', '30 декабря 2026 – 2 января 2027'];
    }

    #[Test]
    #[DataProvider('dates')]
    public function it_prints_the_date_the_way_the_table_says(?string $start, ?string $end, bool $allDay, string $english, string $russian): void
    {
        $this->useLocales('en', 'ru');

        $event = $this->event('class', $start, attributes: ['ends_at' => $end, 'all_day' => $allDay]);

        $this->assertSame($english, When::of($event, 'en'));
        $this->assertSame($russian, When::of($event, 'ru'));
    }

    #[Test]
    public function a_note_overrides_what_is_printed_but_not_what_is_known(): void
    {
        $this->useLocales('en', 'ru');

        $event = $this->event('saturdays', '2020-01-04 10:00:00', attributes: [
            'date_note' => ['en' => 'Every Saturday', 'ru' => 'Каждую субботу'],
        ]);

        $this->assertSame('Every Saturday', When::of($event, 'en'));
        $this->assertSame('Каждую субботу', When::of($event, 'ru'));
        // The date still decides: this one is over.
        $this->assertTrue($event->isPast());
        $this->assertSame([], Event::query()->scopes(['upcoming'])->pluck('id')->all());
    }
}
