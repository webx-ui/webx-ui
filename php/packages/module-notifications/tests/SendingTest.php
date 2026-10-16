<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Tests;

use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Notifications\Jobs\Deliver;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\Notifications;
use WebxUi\Notifications\Refusal;
use WebxUi\Notifications\Sending\Deliverer;

/**
 * `Notifications::send()` and the job (§8): a row per recipient at once, a job per channel, three
 * attempts only for what is not sent, after the response when there is no worker, and nothing
 * thrown at the consumer.
 */
final class SendingTest extends TestCase
{
    #[Test]
    public function every_recipient_is_logged_at_once_and_one_job_per_channel_carries_ids_not_secrets(): void
    {
        Bus::fake();
        $telegram = $this->telegram(['recipients' => [['chat_id' => '1'], ['chat_id' => '2']]]);
        $email = $this->email(['recipients' => [['email' => 'manager@example.com'], ['variable' => 'customer_email']]]);

        $dispatch = $this->notifications()->send('shop.order', [$telegram->id, $email->id, $telegram->id], ['number' => 12, 'note' => null, 'customer_email' => 'customer@example.com'], locale: 'en');

        $this->assertCount(4, $dispatch->queued);
        $rows = Delivery::query()->orderBy('id')->get();
        $this->assertSame(['1', '2', 'manager@example.com', 'customer@example.com'], $rows->pluck('recipient')->all());
        $this->assertSame(['number' => '12', 'note' => '', 'customer_email' => 'customer@example.com'], $rows[0]->variables);
        $this->assertSame('Orders in Telegram', $rows[0]->channel_name);

        Bus::assertDispatchedTimes(Deliver::class, 2);
        Bus::assertDispatched(Deliver::class, function (Deliver $job) use ($telegram): bool {
            $this->assertStringNotContainsString(self::TOKEN, serialize($job));

            return $job->channel === $telegram->id && count($job->deliveries) === 2 && $job->afterCommit === true && ! $job->inline;
        });
    }

    #[Test]
    public function a_channel_that_does_not_send_is_one_skipped_row_with_the_reason(): void
    {
        Bus::fake();
        $off = $this->telegram(['enabled' => false]);
        $deleted = $this->telegram();
        $deleted->delete();
        $foreign = $this->telegram(['event' => null]);
        $foreign->forceFill(['event' => 'inbox.submission'])->save();
        $nobody = $this->email(['recipients' => [['variable' => 'customer_email']]]);

        $dispatch = $this->notifications()->send('shop.order', [$off->id, $deleted->id, $foreign->id, 999, $nobody->id], ['number' => '1']);

        $this->assertSame([], $dispatch->queued);
        $this->assertSame(
            ['channel_disabled', 'channel_deleted', 'wrong_event', 'channel_deleted', 'no_address'],
            Delivery::query()->orderBy('id')->pluck('reason')->all(),
        );
        $this->assertSame([null], Delivery::query()->where('channel_name', '#999')->pluck('channel_id')->all());
        Bus::assertNothingDispatched();
    }

    #[Test]
    public function a_channel_for_any_event_takes_every_event(): void
    {
        Bus::fake();
        $any = $this->telegram(['event' => null, 'template' => ['en' => '{{whatever}}']]);

        $dispatch = $this->notifications()->send('inbox.submission', [$any->id], ['whatever' => 'x']);

        $this->assertCount(1, $dispatch->queued);
    }

    #[Test]
    public function the_job_sends_what_is_queued_and_retries_only_what_did_not_go(): void
    {
        $channel = $this->telegram(['recipients' => [['chat_id' => '1'], ['chat_id' => '2']]]);
        Bus::fake();
        $this->notifications()->send('shop.order', [$channel->id], ['number' => '12', 'note' => 'Fragile <glass>']);
        $ids = Delivery::query()->orderBy('id')->pluck('id')->all();

        Http::fake(['telegram.test/*' => Http::sequence()
            ->push(['ok' => true])
            ->push(['ok' => false, 'description' => 'Bad Gateway'], 502)
            ->push(['ok' => false, 'description' => 'Too Many Requests', 'parameters' => ['retry_after' => 9]], 429)
            ->push(['ok' => true]),
        ]);

        $job = (new Deliver($channel->id, $ids))->withFakeQueueInteractions();
        $job->handle($this->app->make(Deliverer::class));
        $job->assertReleased(30);

        $this->assertSame([Delivery::SENT, Delivery::QUEUED], Delivery::query()->orderBy('id')->pluck('status')->all());

        $second = $this->job($channel->id, $ids, 2);
        $second->handle($this->app->make(Deliverer::class));
        $second->assertReleased(9);

        $third = $this->job($channel->id, $ids, 3);
        $third->handle($this->app->make(Deliverer::class));
        $third->assertNotReleased();

        $rows = Delivery::query()->orderBy('id')->get();
        $this->assertSame([Delivery::SENT, Delivery::SENT], $rows->pluck('status')->all());
        $this->assertSame([1, 3], $rows->pluck('attempts')->all());
        Http::assertSentCount(4);
        Http::assertSent(static fn ($request): bool => $request['text'] === '<b>Order 12</b>: Fragile &lt;glass&gt;');
    }

    #[Test]
    public function after_the_last_attempt_the_row_fails_with_the_words_of_the_last_try_and_nothing_is_thrown(): void
    {
        $channel = $this->telegram();
        Bus::fake();
        $this->notifications()->send('shop.order', [$channel->id], ['number' => '1']);
        Http::fake(['telegram.test/*' => Http::response(['ok' => false, 'description' => 'Bad Gateway'], 502)]);

        $job = $this->job($channel->id, Delivery::query()->pluck('id')->all(), 3);
        $job->handle($this->app->make(Deliverer::class));

        $row = Delivery::query()->sole();
        $this->assertSame(Delivery::FAILED, $row->status);
        $this->assertStringContainsString('Bad Gateway', (string) $row->error);
        $job->assertNotFailed();
    }

    #[Test]
    public function a_permanent_failure_is_not_retried(): void
    {
        $channel = $this->telegram();
        Bus::fake();
        $this->notifications()->send('shop.order', [$channel->id], ['number' => '1']);
        Http::fake(['telegram.test/*' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401)]);

        $job = (new Deliver($channel->id, Delivery::query()->pluck('id')->all()))->withFakeQueueInteractions();
        $job->handle($this->app->make(Deliverer::class));

        $job->assertNotReleased();
        $this->assertSame(Delivery::FAILED, Delivery::query()->sole()->status);
    }

    #[Test]
    public function on_a_sync_queue_the_message_leaves_after_the_response_with_three_quick_attempts(): void
    {
        config()->set('queue.default', 'sync');
        Sleep::fake();
        $channel = $this->telegram();
        Http::fake(['telegram.test/*' => Http::sequence()
            ->push(['ok' => false, 'description' => 'Bad Gateway'], 502)
            ->push(['ok' => false, 'description' => 'Bad Gateway'], 502)
            ->push(['ok' => true]),
        ]);

        $this->assertSame('after_response', $this->notifications()->mode());
        $this->notifications()->send('shop.order', [$channel->id], ['number' => '1']);

        $this->assertSame(Delivery::QUEUED, Delivery::query()->sole()->status, 'Nothing is sent before the response.');
        Http::assertNothingSent();

        $this->app->terminate();

        $row = Delivery::query()->sole();
        $this->assertSame(Delivery::SENT, $row->status);
        $this->assertSame(3, $row->attempts);
        Sleep::assertSleptTimes(2);
        Sleep::assertSequence([Sleep::for(2)->seconds(), Sleep::for(5)->seconds()]);
    }

    #[Test]
    public function a_queue_that_cannot_take_the_job_fails_the_rows_instead_of_the_consumer(): void
    {
        // `database` without its table: what a site with a broken queue looks like.
        $channel = $this->telegram();

        $dispatch = $this->notifications()->send('shop.order', [$channel->id], ['number' => '1']);

        $this->assertSame([], $dispatch->queued);
        $this->assertCount(1, $dispatch->failed);
        $this->assertSame(Delivery::FAILED, Delivery::query()->sole()->status);
    }

    #[Test]
    public function a_switched_off_channel_at_the_time_of_sending_sends_nothing(): void
    {
        $channel = $this->telegram();
        Bus::fake();
        $this->notifications()->send('shop.order', [$channel->id], ['number' => '1']);
        $channel->forceFill(['enabled' => false])->save();
        Http::fake();

        (new Deliver($channel->id, Delivery::query()->pluck('id')->all()))->withFakeQueueInteractions()->handle($this->app->make(Deliverer::class));

        $this->assertSame('channel_disabled', Delivery::query()->sole()->reason);
        Http::assertNothingSent();
    }

    #[Test]
    public function a_failed_row_is_sent_again_with_the_same_values_and_a_sent_one_is_not(): void
    {
        $channel = $this->telegram();
        Bus::fake();
        $this->notifications()->send('shop.order', [$channel->id], ['number' => '1']);
        $row = Delivery::query()->sole();
        $row->forceFill(['status' => Delivery::FAILED, 'error' => 'Bad Gateway', 'attempts' => 3])->save();

        $retried = $this->notifications()->retry($row);

        $this->assertSame(Delivery::QUEUED, $retried->status);
        $this->assertNull($retried->error);
        Bus::assertDispatchedTimes(Deliver::class, 2);

        $retried->forceFill(['status' => Delivery::SENT])->save();
        $this->expectException(Refusal::class);
        $this->notifications()->retry($retried);
    }

    #[Test]
    public function a_test_goes_now_past_the_queue_even_through_a_switched_off_channel_with_the_examples(): void
    {
        Bus::fake();
        Http::fake(['telegram.test/*' => Http::response(['ok' => true])]);
        $channel = $this->telegram(['enabled' => false, 'recipients' => [['chat_id' => '1'], ['variable' => 'telegram_id'], ['chat_id' => '2']]]);

        $results = $this->notifications()->test($channel, '2');

        $this->assertSame([['recipient' => '2', 'label' => null, 'status' => 'sent', 'reason' => null, 'error' => null, 'truncated' => false, 'unknown_variables' => []]], $results);
        Http::assertSent(static fn ($request): bool => $request['chat_id'] === '2' && $request['text'] === '<b>Order 1024</b>: Leave at the door');
        $this->assertTrue(Delivery::query()->sole()->is_test);
        Bus::assertNothingDispatched();
    }

    #[Test]
    public function the_log_of_a_record_is_found_by_its_subject(): void
    {
        Bus::fake();
        $channel = $this->telegram();
        $subject = $this->admin();

        $this->notifications()->send('shop.order', [$channel->id], ['number' => '1'], subject: $subject);
        $this->notifications()->send('shop.order', [$channel->id], ['number' => '2']);

        $this->assertSame([['number' => '1']], $this->notifications()->deliveriesFor($subject)->get()->pluck('variables')->all());
    }

    /**
     * The job as a worker runs it for the given attempt.
     *
     * @param  list<int>  $ids
     */
    private function job(int $channel, array $ids, int $attempt): Deliver
    {
        $fake = new FakeJob;
        $fake->attempts = $attempt;

        $job = new Deliver($channel, $ids);
        $job->setJob($fake);

        return $job;
    }

    private function notifications(): Notifications
    {
        return $this->app->make(Notifications::class);
    }
}
