<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Tests;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Notifications\Doctor\NotificationsCheck;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;

/**
 * What runs on a deploy and at night: webx:doctor (§13) and the trim of the log (§9.1).
 */
final class MaintenanceTest extends TestCase
{
    #[Test]
    public function the_trim_removes_only_old_rows_counts_on_a_dry_run_and_keeps_everything_at_zero(): void
    {
        $old = $this->row(Carbon::now()->subDays(100));
        $fresh = $this->row(Carbon::now()->subDays(10));

        $this->artisan('webx:notifications:prune', ['--dry-run' => true])->expectsOutputToContain('Would remove 1 delivery row')->assertSuccessful();
        $this->assertSame(2, Delivery::query()->count());

        $this->artisan('webx:notifications:prune', ['--days' => 0])->assertSuccessful();
        $this->assertSame([$old->id, $fresh->id], Delivery::query()->orderBy('id')->pluck('id')->all());

        $this->artisan('webx:notifications:prune')->expectsOutputToContain('Removed 1 delivery row')->assertSuccessful();
        $this->assertSame([$fresh->id], Delivery::query()->pluck('id')->all());
        $this->assertNull(Delivery::query()->find($old->id));
    }

    #[Test]
    public function doctor_says_everything_is_fine_when_it_is(): void
    {
        $this->telegram();

        $this->assertSame([[Diagnosis::OK, 'every enabled channel can send.']], $this->diagnose());
    }

    #[Test]
    public function doctor_names_what_waits_what_failed_and_channels_that_cannot_send(): void
    {
        config()->set('mail.default', 'log');
        $this->row(Carbon::now()->subHour());
        $this->row(Carbon::now()->subHour(), Delivery::FAILED);
        $this->email();
        $orphan = $this->telegram();
        $orphan->forceFill(['provider' => 'pigeon', 'event' => 'gone.event'])->save();
        $unreadable = $this->telegram(['name' => 'Old key']);
        Channel::query()->whereKey($unreadable->id)->update(['credentials' => 'eyJpdiI6Im5vdCBhIHJlYWwgY2lwaGVydGV4dCJ9']);

        $found = $this->diagnose();
        $words = implode("\n", array_column($found, 1));

        $this->assertStringContainsString('1 notification waits for a queue worker', $words);
        $this->assertStringContainsString('1 notification was not sent in the last day', $words);
        $this->assertStringContainsString('"pigeon"', $words);
        $this->assertStringContainsString('"gone.event"', $words);
        $this->assertStringContainsString('"Old key"', $words);
        $this->assertStringContainsString('its mailer is "log"', $words);
    }

    #[Test]
    public function doctor_says_how_notifications_leave_without_a_worker(): void
    {
        config()->set('queue.default', 'sync');

        $this->assertStringContainsString('after the response', $this->diagnose()[0][1]);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function diagnose(): array
    {
        return array_map(
            static fn (Diagnosis $diagnosis): array => [$diagnosis->state, $diagnosis->detail],
            $this->app->make(NotificationsCheck::class)->run(),
        );
    }

    private function row(Carbon $at, string $status = Delivery::QUEUED): Delivery
    {
        return Delivery::query()->create([
            'channel_name' => 'Orders',
            'provider' => 'telegram',
            'event' => 'shop.order',
            'recipient' => '1',
            'status' => $status,
            'variables' => ['number' => '1'],
            'created_at' => $at,
        ]);
    }
}
