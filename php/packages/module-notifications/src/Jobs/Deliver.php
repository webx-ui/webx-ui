<?php

declare(strict_types=1);

namespace WebxUi\Notifications\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Throwable;
use WebxUi\Notifications\Models\Channel;
use WebxUi\Notifications\Models\Delivery;
use WebxUi\Notifications\Sending\Deliverer;

/**
 * The deliveries of one channel for one event, on their way (§8.2).
 *
 * It carries ids, never credentials: the channel is read when the job runs. Three attempts, each
 * only for the rows not sent yet, 30 and 120 seconds apart — or as long as Telegram asked (429).
 *
 * Without a worker (`inline`: the queue is `sync`, or the site sends after the response) the three
 * attempts are made in one go with short pauses: a long sleep would hold a PHP worker that serves
 * visitors. Nothing is thrown out of the job — on `sync` that would be an error in the request of
 * the visitor who sent the form — so `failed_jobs` does not fill up either.
 */
final class Deliver implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const int ATTEMPTS = 3;

    /** Seconds before the second and the third attempt, through the queue. */
    public const array PAUSES = [30, 120];

    /** The same, in one go after the response. */
    public const array INLINE_PAUSES = [2, 5];

    /** The job counts its own attempts; the worker is not to give up on it first. */
    public int $tries = self::ATTEMPTS;

    /**
     * @param  list<int>  $deliveries
     */
    public function __construct(
        public readonly int $channel,
        public readonly array $deliveries,
        public bool $inline = false,
    ) {}

    public function handle(Deliverer $deliverer): void
    {
        $rows = Delivery::query()->whereKey($this->deliveries)->where('status', Delivery::QUEUED)->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return;
        }

        try {
            $channel = Channel::query()->withTrashed()->find($this->channel);

            if ($this->inline || $this->job === null || $this->job instanceof SyncJob) {
                $this->inOneGo($deliverer, $channel, $rows->all());

                return;
            }

            $failure = $deliverer->attempt($channel, $rows);
            $left = $rows->filter(static fn (Delivery $row): bool => $row->status === Delivery::QUEUED);

            if ($left->isEmpty()) {
                return;
            }

            $attempt = $this->attempts();

            if ($attempt < self::ATTEMPTS) {
                $this->release($failure !== null && $failure->retryAfter !== null ? $failure->retryAfter : self::PAUSES[min($attempt, count(self::PAUSES)) - 1]);

                return;
            }

            $deliverer->giveUp($left);
        } catch (Throwable $exception) {
            Log::warning('Notifications: deliveries '.implode(', ', $this->deliveries).' were not sent. '.$exception->getMessage());

            $deliverer->giveUp($rows);
        }
    }

    /**
     * @param  list<Delivery>  $rows
     */
    private function inOneGo(Deliverer $deliverer, ?Channel $channel, array $rows): void
    {
        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            $failure = $deliverer->attempt($channel, $rows);

            if ($failure === null || $attempt === self::ATTEMPTS) {
                break;
            }

            $pause = self::INLINE_PAUSES[min($attempt, count(self::INLINE_PAUSES)) - 1];
            Sleep::for(min($failure->retryAfter ?? $pause, $pause))->seconds();
        }

        $deliverer->giveUp($rows);
    }
}
