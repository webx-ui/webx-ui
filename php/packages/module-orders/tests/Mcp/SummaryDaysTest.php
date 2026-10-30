<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Mcp;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;

/**
 * «Today» of the figures and of `orders_summary` is the site's calendar day (decision 95): from
 * midnight in the site's zone, not the last 24 hours, and 7 and 30 are today and the days before
 * it. The zone is eight hours east of UTC and the clock stands at midnight UTC, so a count by the
 * last 24 hours or by UTC's midnight would draw each border somewhere else.
 */
final class SummaryDaysTest extends McpTestCase
{
    private const string ZONE = 'Asia/Hong_Kong';

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.timezone', self::ZONE);
        date_default_timezone_set(self::ZONE);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        date_default_timezone_set('UTC');
    }

    #[Test]
    public function today_begins_at_the_site_s_midnight_and_the_periods_are_calendar_days(): void
    {
        $placed = [
            'yesterday' => '2026-10-29 23:59:00',
            'today' => '2026-10-30 00:01:00',
            'sixth day back' => '2026-10-24 00:01:00',
            'seventh day back' => '2026-10-23 23:59:00',
            'twenty-ninth day back' => '2026-10-01 00:01:00',
            'thirtieth day back' => '2026-09-30 23:59:00',
        ];

        foreach ($placed as $at) {
            $this->travelTo(Carbon::parse($at, self::ZONE));
            $this->order();
        }

        // 08:00 in the site, midnight in UTC.
        $this->travelTo(Carbon::parse('2026-10-30 08:00:00', self::ZONE));
        $this->signIn(['orders.view']);
        $panel = $this->getJson($this->api('summary'))->assertOk()->json('data');

        $this->assertSame($panel, $this->ask('orders_summary'));

        $placedIn = array_combine(
            array_column($panel['periods'], 'days'),
            array_map(static fn (array $period): int => $period['by_currency'][0]['placed'] ?? 0, $panel['periods']),
        );
        // Today: the order of 00:01, not the one of 23:59 yesterday.
        $this->assertSame(1, $placedIn[1]);
        // Seven days: today and the six before it — the 24th in, the 23rd out.
        $this->assertSame(3, $placedIn[7]);
        // Thirty days: today and the twenty-nine before it — the 1st in, September out.
        $this->assertSame(5, $placedIn[30]);

        // The bars by the site's days: today last, yesterday before it.
        $this->assertSame(['date' => '2026-10-30', 'orders' => 1], $panel['daily'][29]);
        $this->assertSame(['date' => '2026-10-29', 'orders' => 1], $panel['daily'][28]);
        $this->assertSame(['date' => '2026-10-01', 'orders' => 1], $panel['daily'][0]);
    }
}
