<?php

declare(strict_types=1);

namespace WebxUi\Users\Tests;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Users\Doctor\MailCheck;
use WebxUi\Users\Passwords\ResetLinks;

/**
 * `webx:users:prune` (spec §14.3) — out-of-date links only, never an account — and the line of
 * `webx:doctor` about letters that cannot leave (§8.6).
 */
final class PruneTest extends TestCase
{
    #[Test]
    public function the_prune_deletes_the_links_out_of_date_and_nothing_else(): void
    {
        $links = app(ResetLinks::class);
        $old = $this->visitor();
        $fresh = $this->visitor();
        $links->issue($old);
        $this->travel(2)->hours();
        $links->issue($fresh);

        $this->artisan('webx:users:prune', ['--dry-run' => true])->expectsOutputToContain('1 link out of date would be deleted.')->assertSuccessful();
        $this->assertSame(2, DB::table(ResetLinks::TABLE)->count());

        $this->artisan('webx:users:prune')->expectsOutputToContain('1 link out of date deleted.')->assertSuccessful();
        $this->assertSame([$fresh->id], DB::table(ResetLinks::TABLE)->pluck('user_id')->map(static fn ($id): int => (int) $id)->all());
        $this->assertSame(2, DB::table('users')->count(), 'Accounts are not pruned (decision 23).');
    }

    #[Test]
    public function the_prune_is_on_the_schedule_daily(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(static fn ($event): bool => str_contains((string) $event->command, 'webx:users:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', $events->first()->expression);
    }

    #[Test]
    public function the_doctor_warns_of_a_transport_that_sends_nothing_except_on_a_local_machine(): void
    {
        config(['mail.default' => 'log', 'mail.mailers.log' => ['transport' => 'log']]);

        $this->app['env'] = 'production';
        [$line] = app(MailCheck::class)->run();
        $this->assertSame(Diagnosis::WARN, $line->state);
        $this->assertStringContainsString('reach nobody', $line->detail);

        $this->app['env'] = 'local';
        [$line] = app(MailCheck::class)->run();
        $this->assertSame(Diagnosis::OK, $line->state);

        $this->app['env'] = 'production';
        config(['mail.default' => 'smtp', 'mail.mailers.smtp' => ['transport' => 'smtp']]);
        [$line] = app(MailCheck::class)->run();
        $this->assertSame(Diagnosis::OK, $line->state);
    }
}
