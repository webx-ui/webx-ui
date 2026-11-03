<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use WebxUi\Payments\Contracts\CompletesApproval;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\MethodCredentials;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\SettleResult;

/**
 * «Ask the gateway, then settle» (spec §9.1, decision 39): the network goes before the
 * transaction, under the attempt's atomic lock `webx-payments:{uuid}` — the same one `/pay/go`
 * takes —, so that a return, a notification to poke and the page «we are checking» never ask the
 * gateway about one attempt at once.
 */
final readonly class Checks
{
    public const int LOCK_SECONDS = 30;

    public const int RECHECK_SECONDS = 15;

    public function __construct(
        private Methods $methods,
        private MethodCredentials $credentials,
        private GatewayCalls $calls,
        private Settlement $settlement,
    ) {}

    /**
     * The page «we are checking» and its JSON (§8.4): asks the gateway about a pending attempt at
     * most once in 15 seconds and never waits for a lock — one buyer is not a stream of requests
     * to the gateway's API. A failure leaves the row as it is: the page reads the row.
     */
    public function recheck(Payment $payment, string $source): void
    {
        if ($payment->status !== PaymentStatus::Pending || ! Cache::add('webx-payments:checked:'.$payment->uuid, true, self::RECHECK_SECONDS)) {
            return;
        }

        try {
            $this->check($payment, $source, complete: true);
        } catch (Throwable $e) {
            Log::warning('webx-payments: the check of a pending attempt failed', ['payment' => $payment->uuid, 'exception' => $e::class]);
        }
    }

    /**
     * Asks and settles. Null — the lock is held by another request and `$wait` seconds were not
     * enough (or no wait was asked): the attempt's row is the answer then.
     *
     * @param  bool  $complete  a type that completes an approval does it first (§6.4): the return and the poll
     * @param  bool  $dryRun  the gateway is asked — the request and its answer are in the journal, as
     *                        every exchange is — and what it says is settled and rolled back: the
     *                        answer is what would change, nothing of the attempt or the order is
     *
     * @throws GatewayUnavailable the gateway failed or did not answer: the attempt is not touched
     */
    public function check(Payment $payment, string $source, ?string $trace = null, bool $complete = false, int $wait = 0, bool $dryRun = false): ?SettleResult
    {
        $method = $this->methods->any($payment->method_key);
        $type = $this->calls->type($payment->type);

        if ($method === null || $type === null) {
            throw GatewayUnavailable::because('The payment method of the attempt is gone');
        }

        $lock = Cache::lock('webx-payments:'.$payment->uuid, self::LOCK_SECONDS);

        try {
            $got = $wait > 0 ? $lock->block($wait) : $lock->get();
        } catch (LockTimeoutException) {
            $got = false;
        }

        if (! $got) {
            return null;
        }

        try {
            $payment->refresh();
            $credentials = $this->credentials->for($method);
            $http = $this->calls->http($type, $method, $credentials, ExchangeKind::Status, $source, $payment, $trace);

            try {
                if ($complete && $type instanceof CompletesApproval && in_array($payment->status, [PaymentStatus::Created, PaymentStatus::Pending], true)) {
                    $type->complete($method, $credentials, $payment, $http->kind(ExchangeKind::Complete));
                }

                $status = $type->fetchStatus($method, $credentials, $payment, $http);
            } catch (ConnectionException $e) {
                throw GatewayUnavailable::timeout($e);
            }

            if (! $dryRun) {
                return $this->settlement->settle($payment, $status);
            }

            $db = $payment->getConnection();
            $db->beginTransaction();

            try {
                $would = $this->settlement->settle($payment, $status);
            } finally {
                $db->rollBack();
                $payment->refresh();
            }

            return new SettleResult($would->outcome, $would->before, $would->after, $would->reported, $would->message, $would->problem, dryRun: true);
        } finally {
            $lock->release();
        }
    }
}
