<?php

declare(strict_types=1);

namespace WebxUi\Vacancies\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Vacancies\Models\Vacancy;

/**
 * Open and closed (decision 13): closed by hand, or past its last day in the application's
 * timezone — one expression for SQL and for php. In Hong Kong, where "today" is already tomorrow
 * for a UTC clock at 20:00, so a comparison in the wrong zone shows.
 */
final class ClosedTest extends TestCase
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
    public function the_last_day_is_open_all_day_where_the_site_is(): void
    {
        $manual = $this->vacancy('manual', attributes: ['is_closed' => true, 'valid_through' => '2030-01-01']);
        $today = $this->vacancy('today', attributes: ['valid_through' => '2026-10-11']);
        $yesterday = $this->vacancy('yesterday', attributes: ['valid_through' => '2026-10-10']);
        $both = $this->vacancy('both', attributes: ['is_closed' => true, 'valid_through' => '2026-10-01']);
        $open = $this->vacancy('open');

        // 20:00 UTC on the 10th is 04:00 on the 11th in Hong Kong: the 10th is over there.
        Carbon::setTestNow(Carbon::parse('2026-10-10 20:00:00', 'UTC')->setTimezone('Asia/Hong_Kong'));

        $this->assertSame(['today', 'open'], $this->slugs(Vacancy::query()->scopes(['open', 'byPosition'])->get()->all()));
        $this->assertSame(['manual', 'yesterday', 'both'], $this->slugs(Vacancy::query()->scopes(['closed', 'byPosition'])->get()->all()));

        $this->assertSame('manual', $manual->refresh()->closedReason());
        $this->assertNull($today->refresh()->closedReason());
        $this->assertSame('expired', $yesterday->refresh()->closedReason());
        // Somebody decided: that wins over the calendar.
        $this->assertSame('manual', $both->refresh()->closedReason());
        $this->assertFalse($open->refresh()->isClosed());
    }

    #[Test]
    public function a_day_is_kept_as_written_whatever_came_with_it(): void
    {
        $vacancy = $this->vacancy('dated', attributes: ['valid_through' => '2026-11-30T23:30:00-05:00', 'posted_at' => '2026-10-01']);

        $this->assertSame('2026-11-30', $vacancy->valid_through?->toDateString());
        $this->assertSame('2026-10-01', $vacancy->posted_at?->toDateString());
    }

    /**
     * @param  list<Vacancy>  $vacancies
     * @return list<string>
     */
    private function slugs(array $vacancies): array
    {
        return array_map(static fn (Vacancy $vacancy): string => (string) $vacancy->getTranslation('slug', 'en'), $vacancies);
    }
}
