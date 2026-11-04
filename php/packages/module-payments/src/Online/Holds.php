<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use WebxUi\Cart\Money;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Contracts\CapturesPayments;
use WebxUi\Payments\Exchange\ExchangeContext;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Exchange\Outcome;
use WebxUi\Payments\GatewayRefused;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\HoldRefused;
use WebxUi\Payments\Method;
use WebxUi\Payments\MethodCredentials;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Reported;
use WebxUi\Payments\SettleResult;

/**
 * A hold taken or let go (spec §9.5, decisions 32 and 52): under the attempt's lock — the one
 * the return, the notification and the poll take —, the gateway first, then `settle()` with its
 * answer. A capture writes `capture_amount` before the call: the gateway may confirm it later by
 * a notification, and the sum is checked against it. A refusal of the gateway leaves the attempt
 * `authorized` with the problem `capture_failed`; a gateway that does not answer leaves it as it
 * is — the poll asks again.
 */
final readonly class Holds
{
    /** A manager's click waits this long for a return or a notification of the same attempt. */
    public const int WAIT_SECONDS = 10;

    public function __construct(
        private Methods $methods,
        private MethodCredentials $credentials,
        private GatewayCalls $calls,
        private Settlement $settlement,
        private InternalEvents $internal,
        private Money $money,
        private Actors $actors,
    ) {}

    /**
     * @param  int|null  $amount  null — all that is held; less only where the type captures partially
     *
     * @throws HoldRefused
     * @throws GatewayUnavailable
     */
    public function capture(Payment $payment, ?int $amount, string $source, bool $dryRun = false): SettleResult
    {
        [$type, $method] = $this->held($payment);
        $held = (int) ($payment->authorized_amount ?? $payment->amount);
        $amount ??= $held;

        match (true) {
            $amount <= 0 => throw new HoldRefused(HoldRefused::AMOUNT),
            $amount > $held => throw new HoldRefused(HoldRefused::TOO_MUCH),
            $amount < $held && ! $type->capturesPartially() => throw new HoldRefused(HoldRefused::PARTIAL),
            default => null,
        };

        $words = $this->money->format($amount, $payment->currency).' of '.$this->money->format($held, $payment->currency);

        if ($dryRun) {
            return new SettleResult(Outcome::Same, PaymentStatus::Authorized, PaymentStatus::Paid, Reported::Paid, 'capture '.$words.'; '.$this->consequence($payment, $amount), dryRun: true);
        }

        return $this->locked($payment, function () use ($payment, $type, $method, $amount, $source, $words): SettleResult {
            $this->stillHeld($payment);
            $payment->forceFill(['capture_amount' => $amount])->save();
            $trace = ExchangeContext::newTrace();
            $credentials = $this->credentials->for($method);
            $http = $this->calls->http($type, $method, $credentials, ExchangeKind::Capture, $source, $payment, $trace);

            try {
                $status = $type->capture($method, $credentials, $payment, $amount, $http);
            } catch (GatewayRefused $refused) {
                $because = Settlement::CAPTURE_REFUSED_NOTE.$this->money->format($amount, $payment->currency).': '.$refused->getMessage();
                $this->settlement->flag($payment, SettleResult::CAPTURE_FAILED, $because);
                $this->journal(ExchangeKind::Captured, $payment, $source, Outcome::Rejected, PaymentStatus::Authorized, $words.' — refused by the gateway', $trace, $type->provider());

                return new SettleResult(Outcome::Rejected, PaymentStatus::Authorized, PaymentStatus::Authorized, null, $because, SettleResult::CAPTURE_FAILED, refusal: $refused->getMessage());
            }

            $result = $this->settlement->settle($payment, $status, actor: $this->actor($source));
            $this->journal(ExchangeKind::Captured, $payment, $source, $result->outcome, $result->after, $words, $trace, $type->provider());

            return $result;
        });
    }

    /**
     * @throws HoldRefused
     * @throws GatewayRefused
     * @throws GatewayUnavailable
     */
    public function void(Payment $payment, string $source, bool $dryRun = false): SettleResult
    {
        [$type, $method] = $this->held($payment);
        $words = $this->money->format((int) ($payment->authorized_amount ?? $payment->amount), $payment->currency).' back to the buyer';

        if ($dryRun) {
            return new SettleResult(Outcome::Same, PaymentStatus::Authorized, PaymentStatus::Cancelled, Reported::Cancelled, 'release the hold: '.$words.'; the order awaits payment as before', dryRun: true);
        }

        return $this->locked($payment, function () use ($payment, $type, $method, $source, $words): SettleResult {
            $this->stillHeld($payment);
            $trace = ExchangeContext::newTrace();
            $credentials = $this->credentials->for($method);
            $http = $this->calls->http($type, $method, $credentials, ExchangeKind::Void, $source, $payment, $trace);

            try {
                $status = $type->void($method, $credentials, $payment, $http);
            } catch (GatewayRefused $refused) {
                $this->journal(ExchangeKind::Released, $payment, $source, Outcome::Rejected, PaymentStatus::Authorized, $words.' — refused by the gateway: '.$refused->getMessage(), $trace, $type->provider());

                throw $refused;
            }

            $result = $this->settlement->settle($payment, $status, actor: $this->actor($source));
            $this->journal(ExchangeKind::Released, $payment, $source, $result->outcome, $result->after, $words, $trace, $type->provider());

            return $result;
        });
    }

    /**
     * The type and the method of a held attempt.
     *
     * @return array{0: CapturesPayments, 1: Method}
     *
     * @throws HoldRefused
     */
    private function held(Payment $payment): array
    {
        $type = $this->calls->type($payment->type);

        if (! $type instanceof CapturesPayments) {
            throw new HoldRefused(HoldRefused::NO_HOLD);
        }

        $this->stillHeld($payment);
        $method = $this->methods->any($payment->method_key) ?? throw GatewayUnavailable::because('The payment method of the attempt is gone');

        return [$type, $method];
    }

    private function stillHeld(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::Authorized) {
            throw new HoldRefused(HoldRefused::NOT_HELD);
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $then
     * @return T
     */
    private function locked(Payment $payment, callable $then): mixed
    {
        $lock = Cache::lock('webx-payments:'.$payment->uuid, Checks::LOCK_SECONDS);

        try {
            $lock->block(self::WAIT_SECONDS);
        } catch (LockTimeoutException) {
            throw new HoldRefused(HoldRefused::BUSY);
        }

        try {
            $payment->refresh();

            return $then();
        } finally {
            $lock->release();
        }
    }

    /**
     * What a capture of `$amount` does to the order (decision of 03.11.2026): the milestone «paid»
     * only where it covers what the order owes; less — money in, and the rest still to pay.
     */
    public function consequence(Payment $payment, int $amount): string
    {
        /** @var Order $order */
        $order = Order::query()->findOrFail($payment->order_id);
        $owes = max(0, Balance::stored($order));

        if ($order->paid_at !== null || $amount >= $owes) {
            return $order->paid_at !== null ? 'the order is paid already' : 'the order gets its milestone «paid»';
        }

        return sprintf(
            'the order costs %s: %s stay unpaid and the order gets no milestone «paid» — lower its items first',
            $this->money->format($owes, $payment->currency),
            $this->money->format($owes - $amount, $payment->currency),
        );
    }

    /** The order's history says the end of a hold in the name of a person, not of code. */
    private function actor(string $source): ?string
    {
        return in_array($source, ['panel', 'mcp', 'api'], true) ? $source : null;
    }

    private function journal(ExchangeKind $kind, Payment $payment, string $source, Outcome $outcome, PaymentStatus $after, string $words, string $trace, string $gateway): void
    {
        $this->internal->write($kind, $payment, $source, $outcome, PaymentStatus::Authorized, $after, $words.' by '.$this->actors->who($source), $trace, $gateway);
    }
}
