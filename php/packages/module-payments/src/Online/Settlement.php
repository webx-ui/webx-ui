<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use WebxUi\Cart\Money;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Orders;
use WebxUi\Payments\Contracts\CapturesPayments;
use WebxUi\Payments\Events\PaymentFailed;
use WebxUi\Payments\Events\PaymentProblem;
use WebxUi\Payments\Events\PaymentSucceeded;
use WebxUi\Payments\Exchange\Outcome;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Reported;
use WebxUi\Payments\SettleResult;

/**
 * The only door from «the gateway says» to our state (spec §9.1): one short transaction with no
 * network inside — the attempt's row `FOR UPDATE`, its move by the table of §8.1, the order through
 * `Orders` in the same transaction. Locks always go «attempt → order», so two attempts of one
 * order never wait for each other in a circle.
 *
 * A notification and the buyer's return saying the same thing queue on the row's lock: the second
 * sees `same`, and the milestone «paid» is set once.
 *
 * The events of a payment (`PaymentSucceeded`, `PaymentFailed`, `PaymentProblem`) go after the
 * commit; a rollback takes them with it.
 */
final readonly class Settlement
{
    public function __construct(
        private ConnectionInterface $db,
        private Orders $orders,
        private Money $money,
        private Dispatcher $events,
        private GatewayCalls $calls,
    ) {}

    /**
     * @param  bool  $closing  the base closes an earlier attempt for a new one (§9.4): a
     *                         cancellation then is no failure of the order's payment
     */
    public function settle(Payment $payment, GatewayStatus $status, bool $closing = false): SettleResult
    {
        return $this->db->transaction(function () use ($payment, $status, $closing): SettleResult {
            /** @var Payment $row */
            $row = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $result = $this->move($row, $status, $closing);

            $payment->setRawAttributes($row->getAttributes(), true);

            return $result;
        });
    }

    /**
     * A problem the base finds itself, not in an answer of the gateway (§9.3, §9.4): a hold about
     * to expire, a capture refused. The row, a line in the order's history, `PaymentProblem`.
     *
     * @param  array<string, mixed>  $meta  merged into the row's meta
     */
    public function flag(Payment $payment, string $problem, string $words, array $meta = []): void
    {
        $this->db->transaction(function () use ($payment, $problem, $words, $meta): void {
            /** @var Payment $row */
            $row = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($meta !== []) {
                $row->meta = [...($row->meta ?? []), ...$meta];
            }

            $this->problem($row, new SettleResult(Outcome::Same, $row->status, $row->status), $problem, $words);
            $payment->setRawAttributes($row->getAttributes(), true);
        });
    }

    private function move(Payment $row, GatewayStatus $status, bool $closing): SettleResult
    {
        $before = $row->status;

        if ($status->gatewayRef !== null && $row->gateway_ref === null) {
            $row->gateway_ref = mb_substr($status->gatewayRef, 0, 190);
        }

        return match ($status->status) {
            Reported::Pending => $before === PaymentStatus::Created
                ? $this->to($row, $status, PaymentStatus::Pending)
                : $this->same($row, $status, $status->reason),
            Reported::Authorized => $this->authorized($row, $status),
            Reported::Paid => $before === PaymentStatus::Paid ? $this->same($row, $status) : $this->paid($row, $status),
            Reported::Failed => $this->failed($row, $status, PaymentStatus::Failed, $closing),
            Reported::Cancelled => $this->failed($row, $status, PaymentStatus::Cancelled, $closing),
            Reported::Expired => $this->failed($row, $status, PaymentStatus::Expired, $closing),
            Reported::Refunded => $this->refunded($row, $status),
        };
    }

    /** Money held (§9.5): the order keeps awaiting payment, its history says how much and till when. */
    private function authorized(Payment $row, GatewayStatus $status): SettleResult
    {
        $before = $row->status;

        if ($before === PaymentStatus::Authorized) {
            return $this->same($row, $status);
        }

        if (! in_array($before, [PaymentStatus::Created, PaymentStatus::Pending], true)) {
            return $this->ignored($row, $status, "The gateway reported a hold of an attempt that is {$before->value}");
        }

        $amount = $status->amount ?? $row->amount;
        $row->authorized_amount = $amount;
        $row->authorized_at = Carbon::now();
        // A gateway that does not say how long it holds holds as long as its type says (§6.4).
        $type = $this->calls->type($row->type);
        $expires = $status->holdExpiresAt ?? ($type instanceof CapturesPayments ? CarbonImmutable::now()->addDays($type->maxHoldDays()) : null);
        $row->hold_expires_at = $expires !== null ? Carbon::instance($expires) : null;
        $result = $this->to($row, $status, PaymentStatus::Authorized);

        $until = $expires !== null ? ' until '.$expires->format('d.m.Y') : '';
        $this->note($row, 'Money held: '.$this->money->format($amount, $row->currency).$until.' — capture or release it');

        return $result;
    }

    /**
     * Paid is stronger than anything (§8.1): from any unfinished or failed status. The order gets
     * its milestone, or a top-up when it is paid and owes more (decision 29); money for an order
     * that owes nothing, a cancelled one or not the sum asked is a problem for the manager (§9.4).
     */
    private function paid(Payment $row, GatewayStatus $status): SettleResult
    {
        $held = $row->status === PaymentStatus::Authorized;
        $expected = $held ? ($row->capture_amount ?? $row->authorized_amount ?? $row->amount) : $row->amount;
        $amount = $status->amount ?? $expected;
        $currency = $status->currency !== null ? strtoupper($status->currency) : $row->currency;
        $paidAt = $status->paidAt ?? CarbonImmutable::now();
        $source = $this->source($row);

        $row->paid_amount = $amount;
        $row->paid_at = Carbon::instance($paidAt);
        $row->failure_reason = null;
        $result = $this->to($row, $status, PaymentStatus::Paid);

        if ($amount !== $expected || $currency !== $row->currency) {
            return $this->problem($row, $result, SettleResult::AMOUNT_MISMATCH, sprintf(
                'The gateway confirmed %s instead of %s — check and mark the payment by hand',
                $this->money->format($amount, $currency),
                $this->money->format($expected, $row->currency),
            ));
        }

        /** @var Order $order */
        $order = Order::query()->findOrFail($row->order_id);

        if ($order->cancelled_at !== null) {
            return $this->problem($row, $result, SettleResult::ORDER_CANCELLED, sprintf(
                'Money came for a cancelled order: %s, payment %s — give it back',
                $this->money->format($amount, $row->currency),
                $row->uuid,
            ));
        }

        if ($order->paid_at === null) {
            $mark = $this->orders->mark($order, Milestone::Paid, $source, $paidAt, $row->gateway_ref ?? $row->uuid, null, $amount);

            if ($mark->marked()) {
                return $this->succeeded($row, $result);
            }
        }

        // A top-up after an edit of a paid order (decision 29): money in, no second milestone.
        if ($this->orders->balance($order->refresh())->due > 0) {
            $this->orders->recordPayment($order, $amount, $source, $row->gateway_ref ?? $row->uuid, $paidAt);

            return $this->succeeded($row, $result);
        }

        return $this->problem($row, $result, SettleResult::DUPLICATE, sprintf(
            'A second payment of %s for a paid order, payment %s — give it back',
            $this->money->format($amount, $row->currency),
            $row->uuid,
        ));
    }

    /** Failed, cancelled, expired: the order hears of it only when this is its last attempt and it is unpaid. */
    private function failed(Payment $row, GatewayStatus $status, PaymentStatus $to, bool $closing): SettleResult
    {
        $before = $row->status;

        if ($before === $to) {
            return $this->same($row, $status);
        }

        if ($before === PaymentStatus::Paid) {
            $this->note($row, "The gateway reported «{$to->value}» for a paid attempt {$row->uuid} — check it in the gateway's cabinet");

            return $this->ignored($row, $status, "The gateway reported {$to->value} for a paid attempt");
        }

        if (! in_array($before, [PaymentStatus::Created, PaymentStatus::Pending, PaymentStatus::Authorized], true)) {
            return $this->ignored($row, $status, "The attempt is {$before->value} already");
        }

        $held = $before === PaymentStatus::Authorized;
        $reason = $status->reason ?? match (true) {
            $held && $to === PaymentStatus::Cancelled => 'The hold was released by the shop',
            $held && $to === PaymentStatus::Expired => 'The hold expired',
            $to === PaymentStatus::Cancelled => 'The payment was cancelled',
            $to === PaymentStatus::Expired => 'The payment expired',
            default => 'The payment did not go through',
        };
        $row->failure_reason = mb_substr($reason, 0, 500);
        $result = $this->to($row, $status, $to);

        /** @var Order $order */
        $order = Order::query()->findOrFail($row->order_id);
        $last = (int) Payment::query()->where('order_id', $row->order_id)->max('id') === $row->id;

        if (! $closing && $last && $order->paid_at === null && $order->cancelled_at === null) {
            $this->orders->paymentFailed($order, $this->source($row), $reason);
        }

        $this->events->dispatch(new PaymentFailed($row->id, $row->uuid, $row->order_id, $row->method_key, $to->value, $row->failure_reason));

        return $result;
    }

    /** Given back from the gateway's cabinet (§8.1): a line for the manager, who records the refund. */
    private function refunded(Payment $row, GatewayStatus $status): SettleResult
    {
        $amount = $status->refundedAmount;

        if ($amount === null || $amount === $row->refunded_amount) {
            return $this->same($row, $status);
        }

        $row->refunded_amount = $amount;
        $row->save();
        $this->note($row, 'The gateway reported a refund of '.$this->money->format($amount, $row->currency)." for payment {$row->uuid} — record the refund");

        return new SettleResult(Outcome::Applied, $row->status, $row->status, Reported::Refunded, 'refunded '.$amount);
    }

    private function to(Payment $row, GatewayStatus $status, PaymentStatus $to): SettleResult
    {
        $before = $row->status;
        $row->status = $to;

        // A hold's problem is put right by its end — a capture, a release, an expiry (§9.4).
        if ($before === PaymentStatus::Authorized && in_array($row->problem, SettleResult::HOLD_PROBLEMS, true)) {
            $row->problem = null;
        }

        $row->save();

        return new SettleResult(Outcome::Applied, $before, $to, $status->status, $status->reason);
    }

    private function same(Payment $row, GatewayStatus $status, ?string $message = null): SettleResult
    {
        if ($row->isDirty()) {
            $row->save();
        }

        return new SettleResult(Outcome::Same, $row->status, $row->status, $status->status, $message);
    }

    private function ignored(Payment $row, GatewayStatus $status, string $message): SettleResult
    {
        if ($row->isDirty()) {
            $row->save();
        }

        return new SettleResult(Outcome::Ignored, $row->status, $row->status, $status->status, $message);
    }

    private function problem(Payment $row, SettleResult $result, string $problem, string $words): SettleResult
    {
        $row->problem = $problem;
        $row->save();
        $this->note($row, $words);
        $this->events->dispatch(new PaymentProblem(
            $row->id,
            $row->uuid,
            $row->order_id,
            $row->method_key,
            $problem,
            $words,
            $row->paid_amount ?? $row->authorized_amount ?? $row->amount,
            $row->currency,
            $row->gateway_ref,
        ));

        return new SettleResult($result->outcome, $result->before, $result->after, $result->reported, $words, $problem);
    }

    private function succeeded(Payment $row, SettleResult $result): SettleResult
    {
        $this->events->dispatch(new PaymentSucceeded($row->id, $row->uuid, $row->order_id, $row->method_key, (int) $row->paid_amount, $row->currency, $row->gateway_ref));

        return $result;
    }

    private function note(Payment $row, string $text): void
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($row->order_id);
        $this->orders->note($order, $text, $this->source($row));
    }

    private function source(Payment $row): string
    {
        return 'payment:'.$row->method_key;
    }
}
