<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Contracts\CancelsPayments;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\MethodCredentials;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Reported;

/**
 * No second payment of one order (spec §9.4): before a new attempt the earlier `pending` ones are
 * asked first — one paid already means no new attempt —, then closed at the gateway where the
 * type can (`CancelsPayments`). One the gateway will not close, or a type that cannot, stays
 * `pending` to its deadline, and the page of payment warns the buyer.
 *
 * A gateway that does not answer is not a reason to keep the buyer from paying: the attempt stays
 * as it is, the poll asks again, and a payment that slips through is the problem `duplicate`.
 */
final readonly class Closings
{
    /** A return or a notification of the same attempt may be holding its lock right now. */
    public const int WAIT_SECONDS = 5;

    public function __construct(
        private Checks $checks,
        private Methods $methods,
        private MethodCredentials $credentials,
        private GatewayCalls $calls,
        private Settlement $settlement,
    ) {}

    /** Whether an earlier attempt of the order turned out paid. */
    public function close(Order $order, string $source): bool
    {
        $paid = false;

        $pending = Payment::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentStatus::Pending->value)
            ->orderBy('id')
            ->get();

        foreach ($pending as $payment) {
            /** @var Payment $payment */
            try {
                $this->checks->check($payment, $source, complete: true, wait: self::WAIT_SECONDS);
            } catch (Throwable $e) {
                Log::warning('webx-payments: an earlier attempt could not be checked before a new one', ['payment' => $payment->uuid, 'exception' => $e::class]);

                continue;
            }

            $payment->refresh();

            if ($payment->status === PaymentStatus::Paid) {
                $paid = true;

                continue;
            }

            if ($payment->status === PaymentStatus::Pending) {
                $this->cancel($payment, $source);
            }
        }

        return $paid;
    }

    /** Whether the order has an attempt the gateway may still take money by, the new one aside. */
    public function open(Order $order, ?int $except = null): bool
    {
        return Payment::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentStatus::Pending->value)
            ->when($except !== null, static fn ($query) => $query->whereKeyNot($except))
            ->exists();
    }

    private function cancel(Payment $payment, string $source): void
    {
        $type = $this->calls->type($payment->type);
        $method = $this->methods->any($payment->method_key);

        if (! $type instanceof CancelsPayments || $method === null) {
            return;
        }

        $lock = Cache::lock('webx-payments:'.$payment->uuid, Checks::LOCK_SECONDS);

        try {
            $lock->block(self::WAIT_SECONDS);
        } catch (LockTimeoutException) {
            return;
        }

        try {
            $payment->refresh();

            if ($payment->status !== PaymentStatus::Pending) {
                return;
            }

            $credentials = $this->credentials->for($method);
            $http = $this->calls->http($type, $method, $credentials, ExchangeKind::Cancel, $source, $payment);

            if ($type->cancel($method, $credentials, $payment, $http)) {
                $this->settlement->settle($payment, new GatewayStatus(Reported::Cancelled, reason: 'Closed for a new attempt'), closing: true);
            }
        } catch (Throwable $e) {
            Log::warning('webx-payments: an earlier attempt could not be closed at the gateway', ['payment' => $payment->uuid, 'exception' => $e::class]);
        } finally {
            $lock->release();
        }
    }
}
