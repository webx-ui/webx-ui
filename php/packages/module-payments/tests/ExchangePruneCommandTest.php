<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payments\Exchange\ExchangeContext;
use WebxUi\Payments\Exchange\ExchangeEntry;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Models\PaymentGatewayEvent;

/**
 * The only removal (spec §20.6): asked for with `--days`, never younger than a year (decision 51),
 * counted first with `--dry-run`, confirmed unless `--force`.
 */
final class ExchangePruneCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->event(400, 'old one');
        $this->event(366, 'old two');
        $this->event(364, 'eleven months');
        $this->event(0, 'today');
    }

    #[Test]
    public function without_days_nothing_is_removed(): void
    {
        $this->artisan('webx:payments:exchange-prune', ['--force' => true])
            ->expectsOutputToContain('--days=N, at least 365')
            ->assertExitCode(2);

        $this->assertSame(4, PaymentGatewayEvent::query()->count());
    }

    #[Test]
    public function events_younger_than_a_year_are_never_removed(): void
    {
        $this->artisan('webx:payments:exchange-prune', ['--days' => '30', '--force' => true])
            ->expectsOutputToContain('younger than 365 days are never pruned')
            ->assertExitCode(2);

        $this->artisan('webx:payments:exchange-prune', ['--days' => '364', '--force' => true])->assertExitCode(2);

        $this->assertSame(4, PaymentGatewayEvent::query()->count());
    }

    #[Test]
    public function a_dry_run_only_counts(): void
    {
        $this->artisan('webx:payments:exchange-prune', ['--days' => '365', '--dry-run' => true])
            ->expectsOutputToContain('Would remove 2 events older than')
            ->assertExitCode(0);

        $this->assertSame(4, PaymentGatewayEvent::query()->count());
    }

    #[Test]
    public function it_asks_before_it_removes(): void
    {
        $this->artisan('webx:payments:exchange-prune', ['--days' => '365'])
            ->expectsConfirmation('Remove 2 events older than '.CarbonImmutable::now()->subDays(365)->toDateString().' (14 B of bodies)? This cannot be undone.', 'no')
            ->assertExitCode(1);

        $this->assertSame(4, PaymentGatewayEvent::query()->count());
    }

    #[Test]
    public function it_removes_only_what_is_older_than_the_days(): void
    {
        $this->artisan('webx:payments:exchange-prune', ['--days' => '365', '--force' => true])
            ->expectsOutputToContain('Removed 2 events')
            ->assertExitCode(0);

        $this->assertSame(['eleven months', 'today'], PaymentGatewayEvent::query()->orderBy('id')->pluck('request_body')->all());
    }

    private function event(int $daysAgo, string $body): void
    {
        $this->travelTo(CarbonImmutable::now()->subDays($daysAgo));
        $this->app->make(ExchangeJournal::class)->record(new ExchangeEntry(
            kind: ExchangeKind::Notify,
            trace: ExchangeContext::newTrace(),
            requestBody: $body,
        ), $this->secrets());
        $this->travelBack();

        $this->assertSame(0, DB::transactionLevel());
    }
}
