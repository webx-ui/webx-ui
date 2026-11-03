<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use WebxUi\Cart\Money;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Reported;
use WebxUi\Payments\SettleResult;

/**
 * The background poll (spec §9.3): a notification that got lost, a buyer who closed the tab —
 * the attempt still comes to its final status. Attempts `created` / `pending` older than five
 * minutes are asked once a run (`check()`, an approval completed first); past their deadline —
 * the gateway's `expires_at`, or 48 hours — without an answer they expire, with an internal
 * event of the journal. An attempt that stays unfinished is asked less and less often
 * (`backOff()`). Holds that end within a day are asked too, and the manager is told once a day
 * per attempt (`hold_expiring`).
 */
final readonly class Poll
{
    public const int AFTER_MINUTES = 5;

    public const int DEADLINE_HOURS = 48;

    public const int BATCH = 50;

    /** The longest pause between two questions about one unfinished attempt. */
    public const int MAX_PAUSE_MINUTES = 360;

    public const string SOURCE = 'poll';

    public function __construct(
        private Checks $checks,
        private Settlement $settlement,
        private InternalEvents $internal,
        private Money $money,
    ) {}

    /** @return array{checked: int, expired: int, warned: int, errors: int} */
    public function run(): array
    {
        $report = ['checked' => 0, 'expired' => 0, 'warned' => 0, 'errors' => 0];
        $now = CarbonImmutable::now();

        Payment::query()
            ->whereIn('status', [PaymentStatus::Created->value, PaymentStatus::Pending->value])
            ->where('created_at', '<=', $now->subMinutes(self::AFTER_MINUTES))
            ->chunkById(self::BATCH, function (Collection $rows) use (&$report, $now): void {
                foreach ($rows as $payment) {
                    /** @var Payment $payment */
                    $this->unfinished($payment, $now, $report);
                }
            });

        Payment::query()
            ->where('status', PaymentStatus::Authorized->value)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', $now->addDay())
            ->chunkById(self::BATCH, function (Collection $rows) use (&$report, $now): void {
                foreach ($rows as $payment) {
                    /** @var Payment $payment */
                    $this->hold($payment, $now, $report);
                }
            });

        return $report;
    }

    /** @param  array{checked: int, expired: int, warned: int, errors: int}  $report */
    private function unfinished(Payment $payment, CarbonImmutable $now, array &$report): void
    {
        // A `created` attempt that never reached the gateway has nothing to ask: the buyer may
        // still go — until the deadline.
        if (($payment->status === PaymentStatus::Pending || $payment->gateway_ref !== null) && $this->due($payment, $now)) {
            $this->ask($payment, $report, complete: true);
            $this->backOff($payment, $now);
        }

        $payment->refresh();
        $deadline = $payment->expires_at !== null
            ? CarbonImmutable::instance($payment->expires_at)
            : CarbonImmutable::instance($payment->created_at ?? $now)->addHours(self::DEADLINE_HOURS);

        if (! in_array($payment->status, [PaymentStatus::Created, PaymentStatus::Pending], true) || $now->lessThanOrEqualTo($deadline)) {
            return;
        }

        $lock = Cache::lock('webx-payments:'.$payment->uuid, Checks::LOCK_SECONDS);

        if (! $lock->get()) {
            return;
        }

        try {
            $result = $this->settlement->settle($payment, new GatewayStatus(Reported::Expired, reason: 'The gateway did not answer in time'));
            $this->internal->write(ExchangeKind::Expire, $payment, self::SOURCE, $result->outcome, $result->before, $result->after, 'no final answer of the gateway by '.$deadline->toIso8601String(), gateway: $this->gateway($payment));
            $report['expired']++;
        } catch (Throwable $e) {
            $report['errors']++;
            Log::warning('webx-payments: the poll could not expire an attempt', ['payment' => $payment->uuid, 'exception' => $e::class]);
        } finally {
            $lock->release();
        }
    }

    /** The attempt's next question is due: never asked, or its pause is over. */
    private function due(Payment $payment, CarbonImmutable $now): bool
    {
        $next = $payment->meta['poll']['next_at'] ?? null;

        return ! is_string($next) || CarbonImmutable::parse($next)->lessThanOrEqualTo($now);
    }

    /**
     * An attempt still unfinished after the question waits twice as long before the next one —
     * 5, 10, 20… minutes, at most MAX_PAUSE_MINUTES: a payment the gateway does not know, or one
     * the buyer left, is not asked about every five minutes until its deadline (the journal keeps
     * every question). The pause sits in `meta.poll`; a final status ends the polling anyway.
     */
    private function backOff(Payment $payment, CarbonImmutable $now): void
    {
        $payment->refresh();

        if (! in_array($payment->status, [PaymentStatus::Created, PaymentStatus::Pending], true)) {
            return;
        }

        $asked = (int) ($payment->meta['poll']['asked'] ?? 0) + 1;
        $pause = min(self::AFTER_MINUTES * 2 ** $asked, self::MAX_PAUSE_MINUTES);
        $payment->meta = [...($payment->meta ?? []), 'poll' => ['asked' => $asked, 'next_at' => $now->addMinutes($pause)->toIso8601String()]];
        $payment->save();
    }

    /** @param  array{checked: int, expired: int, warned: int, errors: int}  $report */
    private function hold(Payment $payment, CarbonImmutable $now, array &$report): void
    {
        $this->ask($payment, $report);
        $payment->refresh();

        if ($payment->status !== PaymentStatus::Authorized || $payment->hold_expires_at === null) {
            return;
        }

        $today = $now->toDateString();

        if (($payment->meta['hold_warned_on'] ?? null) === $today) {
            return;
        }

        $number = (string) Order::query()->whereKey($payment->order_id)->value('number');
        $this->settlement->flag($payment, SettleResult::HOLD_EXPIRING, sprintf(
            'The hold of %s for order %s ends %s — capture it or let it go',
            $this->money->format((int) ($payment->authorized_amount ?? $payment->amount), $payment->currency),
            $number,
            CarbonImmutable::instance($payment->hold_expires_at)->format('d.m.Y H:i'),
        ), ['hold_warned_on' => $today]);
        $report['warned']++;
    }

    /** @param  array{checked: int, expired: int, warned: int, errors: int}  $report */
    private function ask(Payment $payment, array &$report, bool $complete = false): void
    {
        try {
            if ($this->checks->check($payment, self::SOURCE, complete: $complete) !== null) {
                $report['checked']++;
            }
        } catch (Throwable $e) {
            // One gateway down is not a stop for the others: the next run asks again.
            $report['errors']++;
            Log::warning('webx-payments: the poll could not check an attempt', ['payment' => $payment->uuid, 'exception' => $e::class]);
        }
    }

    private function gateway(Payment $payment): ?string
    {
        $prefix = strstr($payment->type, '.', true);

        return $prefix !== false ? $prefix : null;
    }
}
