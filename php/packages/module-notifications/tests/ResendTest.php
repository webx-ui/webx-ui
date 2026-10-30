<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Tests;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Notifications\Jobs\Deliver;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\Notifications;
use WebxUi\Notifications\Refusal;

/**
 * A message sent by hand (§8.6): a row that went is sent again as a new row — marked manual, with
 * who asked and the row it repeats — and an event goes to its addressee alone. A second press
 * within the pause sends nothing; a channel that would not send refuses before anything is written.
 */
final class ResendTest extends TestCase
{
    #[Test]
    public function a_row_that_went_goes_again_as_a_new_row_and_the_first_stays(): void
    {
        Bus::fake();
        $channel = $this->email();
        $this->notifications()->send('shop.order', [$channel->id], ['number' => 12, 'note' => 'Fragile'], locale: 'en');
        $first = Delivery::query()->sole();
        $first->forceFill(['status' => Delivery::SENT, 'sent_at' => Carbon::now()])->save();

        $this->assertFalse($first->retryable());
        $this->assertTrue($first->resendable());

        $dry = $this->notifications()->resend($first, 7, dryRun: true);
        $this->assertFalse($dry->exists);
        $this->assertSame(1, Delivery::query()->count());

        $again = $this->notifications()->resend($first, 7);

        $this->assertSame([Delivery::QUEUED, 'manager@example.com', true, 7, $first->id], [$again->status, $again->recipient, $again->manual, $again->manual_by, $again->resent_from]);
        $this->assertSame($first->variables, $again->variables);
        $this->assertSame(Delivery::SENT, $first->refresh()->status);
        Bus::assertDispatched(Deliver::class, static fn (Deliver $job): bool => $job->deliveries === [(int) $again->id]);

        $this->expectExceptionObject(new Refusal((string) __('webx-notifications::errors.resent-recently')));
        $this->notifications()->resend($first, 7);
    }

    #[Test]
    public function a_test_a_skipped_row_and_a_switched_off_channel_are_refused(): void
    {
        Bus::fake();
        $channel = $this->email();
        $this->notifications()->send('shop.order', [$channel->id], ['number' => 12], locale: 'en');
        $row = Delivery::query()->sole();
        $row->forceFill(['status' => Delivery::FAILED])->save();

        $channel->forceFill(['enabled' => false])->save();

        try {
            $this->notifications()->resend($row);
            $this->fail('A switched-off channel sent.');
        } catch (Refusal $refusal) {
            $this->assertSame(__('webx-notifications::errors.resend-channel-off', ['channel' => 'Orders by mail']), $refusal->getMessage());
        }

        $row->forceFill(['status' => Delivery::SKIPPED, 'recipient' => ''])->save();
        $this->assertFalse($row->resendable());
        $row->forceFill(['status' => Delivery::SENT, 'recipient' => 'manager@example.com', 'is_test' => true])->save();
        $this->assertFalse($row->resendable());

        $this->assertSame(1, Delivery::query()->count());
    }

    #[Test]
    public function an_event_by_hand_goes_to_its_addressee_alone(): void
    {
        Bus::fake();
        $channel = $this->email(['recipients' => [['email' => 'manager@example.com'], ['variable' => 'customer_email']]]);
        $other = $this->email(['name' => 'Managers only']);
        $subject = $channel;

        $this->assertSame([$channel->id], array_map(static fn ($one): int => $one->id, $this->notifications()->addressed([$channel->id, $other->id], 'shop.order', 'customer_email')));

        $values = ['number' => 12, 'customer_email' => 'customer@example.com'];
        $dry = $this->notifications()->sendByHand('shop.order', [$channel->id, $other->id], 'customer_email', $values, $subject, 'en', 3, dryRun: true);
        $this->assertSame(['customer@example.com'], array_map(static fn (Delivery $row): string => $row->recipient, $dry));
        $this->assertSame(0, Delivery::query()->count());

        $this->notifications()->sendByHand('shop.order', [$channel->id, $other->id], 'customer_email', $values, $subject, 'en', 3);
        $this->assertSame([['customer@example.com', true, 3]], Delivery::query()->get()->map(static fn (Delivery $row): array => [$row->recipient, $row->manual, $row->manual_by])->all());
        Bus::assertDispatchedTimes(Deliver::class, 1);

        $this->assertSame('Order 12', $this->notifications()->render($channel, $values, 'en')['subject']);

        $this->expectException(Refusal::class);
        $this->notifications()->sendByHand('shop.order', [$channel->id], 'customer_email', $values, $subject, 'en', 3);
    }

    private function notifications(): Notifications
    {
        return $this->app->make(Notifications::class);
    }
}
