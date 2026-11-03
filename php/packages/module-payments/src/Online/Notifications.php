<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use WebxUi\Payments\Contracts\ChargesOnline;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeContext;
use WebxUi\Payments\Exchange\ExchangeDecision;
use WebxUi\Payments\Exchange\ExchangeEntry;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Exchange\ExchangeSecrets;
use WebxUi\Payments\Exchange\JournalUnavailable;
use WebxUi\Payments\Exchange\Outcome;
use WebxUi\Payments\Exchange\SignatureCheck;
use WebxUi\Payments\InvalidNotification;
use WebxUi\Payments\Method;
use WebxUi\Payments\MethodCredentials;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Notification;
use WebxUi\Payments\SettleResult;

/**
 * POST /payments/notify/{method} (spec §9.2), step by step:
 *
 * 0. the event `in/notify` — method, address, headers, the raw body, IP — is written and committed
 *    through the journal's own connection before anything is parsed; no write — 503, the gateway
 *    repeats it (decision 48);
 * 1. the method by its key, online, with keys — else 404, `rejected`;
 * 2. `readNotification()`: a bad signature or body — `rejected`, the type's reject answer;
 * 3. not about a payment — `ignored`; 4. no such attempt — `unmatched`; both accepted: repeating
 *    them fixes nothing;
 * 5. the same `event_key` of a status applied before — `duplicate`, nothing is touched (a poke
 *    always asks: its body is believed in nothing);
 * 6. a poke (an unsigned type, decision 33) — the gateway's API is asked under the attempt's lock,
 *    the body is never a status; a status of a trusted type — settled as it is;
 * 7–9. the decision, the statuses before and after and the answer the gateway got go into the
 *    same event; a failure on step 6 rolls the settle back and answers 500 — the gateway repeats.
 *
 * Called by the controller and by «gateway A» of a development site, which posts its
 * notifications through here as a real gateway's would come (§21.2).
 */
final readonly class Notifications
{
    public function __construct(
        private ExchangeJournal $journal,
        private Methods $methods,
        private MethodCredentials $credentials,
        private GatewayCalls $calls,
        private Checks $checks,
        private Settlement $settlement,
        private Repository $config,
    ) {}

    public function receive(Request $request, string $methodKey): Response
    {
        $started = CarbonImmutable::now();
        $trace = ExchangeContext::newTrace();
        [$method, $type, $credentials] = $this->resolve($methodKey);
        $gateway = $type !== null ? $this->gatewayKey($type) : null;
        // The method's keys are wiped from the event even when it is about to be refused.
        $secrets = $method !== null && $type !== null && $gateway !== null
            ? $this->calls->http($type, $method, $credentials, ExchangeKind::Status, 'notify', null, $trace)->secrets()
            : ExchangeSecrets::none();

        try {
            $id = $this->journal->recordOrFail(new ExchangeEntry(
                kind: ExchangeKind::Notify,
                trace: $trace,
                gateway: $gateway,
                methodKey: mb_substr($methodKey, 0, 64),
                source: 'notify',
                httpMethod: $request->getMethod(),
                url: $request->fullUrl(),
                ip: $request->ip(),
                requestHeaders: $request->headers->all(),
                requestBody: $request->getContent(),
                startedAt: $started,
            ), $secrets);
        } catch (JournalUnavailable) {
            // Not written — not read: the gateway repeats, and the status waits (decision 48).
            return new Response('Service Unavailable', 503, ['Content-Type' => 'text/plain', 'Retry-After' => '60']);
        }

        $answer = function (Response $response, ExchangeDecision $decision) use ($id, $secrets): Response {
            $this->journal->decide($id, new ExchangeDecision(
                outcome: $decision->outcome,
                paymentId: $decision->paymentId,
                orderId: $decision->orderId,
                signature: $decision->signature,
                reported: $decision->reported,
                statusBefore: $decision->statusBefore,
                statusAfter: $decision->statusAfter,
                message: $decision->message,
                eventKey: $decision->eventKey,
                responseStatus: $response->getStatusCode(),
                responseHeaders: $response->headers->allPreserveCaseWithoutCookies(),
                responseBody: (string) $response->getContent(),
            ), $secrets);

            return $response;
        };

        if (strlen($request->getContent()) > $this->maxBody()) {
            return $answer(self::plain('Payload Too Large', 413), new ExchangeDecision(Outcome::Rejected, signature: SignatureCheck::None, message: 'The body is larger than webx-payments.notify.max_body'));
        }

        if ($method === null || $type === null || $credentials->isEmpty()) {
            return $answer(self::plain('Not Found', 404), new ExchangeDecision(Outcome::Rejected, signature: SignatureCheck::None, message: match (true) {
                $method === null => 'The method is not found',
                $type === null => 'The method is not online, or its type is not installed',
                default => 'The method has no keys',
            }));
        }

        if (! $this->fromAllowedIp($method, $request)) {
            return $answer(self::plain('Forbidden', 403), new ExchangeDecision(Outcome::Rejected, signature: SignatureCheck::None, message: 'The address '.$request->ip().' is not in the notify_ips of the method'));
        }

        $http = $this->calls->http($type, $method, $credentials, ExchangeKind::Status, 'notify', null, $trace);
        $trusted = $type->trustsNotifications();

        try {
            $notification = $type->readNotification($method, $credentials, $request, $http);
        } catch (InvalidNotification $e) {
            // A claim is a link for the manager, not a fact: the status stays as it is.
            $claimed = $e->claims() ? $this->claimed($e, $type) : null;

            return $answer($type->rejectResponse($method, $credentials, $e), new ExchangeDecision(
                Outcome::Rejected,
                paymentId: $claimed?->id,
                orderId: $claimed?->order_id,
                signature: $e->reason === InvalidNotification::SIGNATURE ? SignatureCheck::Invalid : ($trusted ? SignatureCheck::None : SignatureCheck::Untrusted),
                statusBefore: $claimed?->status->value,
                statusAfter: $claimed?->status->value,
                // Short: the link to the attempt and the status unchanged are the event's mark, «claimed».
                message: $e->getMessage(),
            ));
        } catch (Throwable $e) {
            return $answer(self::plain('Internal Server Error', 500), $this->error(null, $e, $trusted));
        }

        $signature = $trusted && ! $notification->isPoke() ? SignatureCheck::Valid : SignatureCheck::Untrusted;
        $reported = $notification->status?->status->value;

        if ($notification->isUnrelated()) {
            return $answer($type->acceptResponse($method, $credentials, $notification), new ExchangeDecision(
                Outcome::Ignored, signature: $signature, message: $notification->reason, eventKey: $notification->eventKey,
            ));
        }

        $payment = $this->attempt($notification, $method, $type);

        if ($payment === null) {
            return $answer($type->acceptResponse($method, $credentials, $notification), new ExchangeDecision(
                Outcome::Unmatched, signature: $signature, reported: $reported, eventKey: $notification->eventKey,
                message: 'No attempt of this method has '.($notification->uuid !== null ? 'uuid '.$notification->uuid : 'gateway id '.($notification->gatewayRef ?? '—')),
            ));
        }

        $base = ['paymentId' => $payment->id, 'orderId' => $payment->order_id];

        // A repeat of a status applied before is not applied again. A poke always asks: its body
        // is believed in nothing, and the same body may come before and after the payment.
        $poke = $notification->isPoke() || ! $trusted || $notification->status === null;

        if (! $poke && $this->seenBefore($method->key, $notification->eventKey, $id)) {
            return $answer($type->acceptResponse($method, $credentials, $notification), new ExchangeDecision(
                Outcome::Duplicate, ...$base, signature: $signature, reported: $reported, eventKey: $notification->eventKey,
                statusBefore: $payment->status->value, statusAfter: $payment->status->value, message: 'The gateway repeated a notification already applied',
            ));
        }

        try {
            $result = $poke || $notification->status === null
                ? $this->poke($payment, $trace)
                : $this->settlement->settle($payment, $notification->status);
        } catch (Throwable $e) {
            return $answer(self::plain('Internal Server Error', 500), $this->error($payment, $e, $trusted, $notification->eventKey));
        }

        return $answer($type->acceptResponse($method, $credentials, $notification), new ExchangeDecision(
            $result->outcome, ...$base,
            signature: $signature,
            reported: $result->reported->value ?? $reported,
            statusBefore: $result->before->value,
            statusAfter: $result->after->value,
            message: $result->message,
            eventKey: $notification->eventKey,
        ));
    }

    /** @return array{0: Method|null, 1: ChargesOnline|null, 2: Credentials} */
    private function resolve(string $methodKey): array
    {
        try {
            $method = $this->methods->any($methodKey);
            $type = $method !== null ? $this->calls->type($method->type) : null;

            return [$method, $type, $method !== null && $type !== null ? $this->credentials->for($method) : Credentials::none()];
        } catch (Throwable) {
            // The event is written whatever this was: the journal is the evidence.
            return [null, null, Credentials::none()];
        }
    }

    private function gatewayKey(ChargesOnline $type): ?string
    {
        try {
            return $this->calls->gateway($type)->key();
        } catch (Throwable) {
            return null;
        }
    }

    /** By our uuid first; else by the gateway's id of the payment — always within the method's own type. */
    private function attempt(Notification $notification, Method $method, ChargesOnline $type): ?Payment
    {
        if ($notification->uuid !== null) {
            $payment = Payment::query()->where('uuid', $notification->uuid)->first();

            if ($payment !== null) {
                return $payment->type === $type->key() ? $payment : null;
            }
        }

        if ($notification->gatewayRef !== null) {
            return Payment::query()->where('type', $type->key())->where('gateway_ref', $notification->gatewayRef)->first();
        }

        return null;
    }

    /** The attempt a rejected body claims to be about — within the method's own type, as `attempt()`. */
    private function claimed(InvalidNotification $e, ChargesOnline $type): ?Payment
    {
        try {
            $byUuid = $e->claimedUuid !== null ? Payment::query()->where('type', $type->key())->where('uuid', $e->claimedUuid)->first() : null;

            return $byUuid ?? ($e->claimedGatewayRef !== null ? Payment::query()->where('type', $type->key())->where('gateway_ref', $e->claimedGatewayRef)->first() : null);
        } catch (Throwable) {
            return null;
        }
    }

    /** The same event of the gateway applied — or found the same — before (decision 41: not a unique key). */
    private function seenBefore(string $methodKey, string $eventKey, int $id): bool
    {
        return PaymentGatewayEvent::query()
            ->where('method_key', $methodKey)
            ->where('event_key', $eventKey)
            ->whereIn('outcome', [Outcome::Applied->value, Outcome::Same->value])
            ->where('id', '!=', $id)
            ->exists();
    }

    /** Decision 33: the body is only a reason to ask; the status comes from the gateway's API. */
    private function poke(Payment $payment, string $trace): SettleResult
    {
        // Locked by another request for longer than a gateway waits: 500, and the gateway repeats.
        return $this->checks->check($payment, 'notify', $trace, wait: 10)
            ?? throw new RuntimeException('The attempt is locked by another request');
    }

    private function error(?Payment $payment, Throwable $e, bool $trusted, ?string $eventKey = null): ExchangeDecision
    {
        Log::error('webx-payments: notification failed', ['payment' => $payment?->uuid, 'exception' => $e::class]);

        return new ExchangeDecision(
            Outcome::Error,
            paymentId: $payment?->id,
            orderId: $payment?->order_id,
            signature: $trusted ? SignatureCheck::Valid : SignatureCheck::Untrusted,
            statusBefore: $payment?->status->value,
            statusAfter: $payment?->refresh()->status->value,
            message: mb_strcut($e::class.': '.$e->getMessage(), 0, 500),
            eventKey: $eventKey,
        );
    }

    /** An optional list of the gateway's addresses (§9.2): on top of the signature, never instead of it. */
    private function fromAllowedIp(Method $method, Request $request): bool
    {
        $list = $method->settings['notify_ips'] ?? null;
        $ips = is_string($list) ? preg_split('/[\s,]+/', $list, -1, PREG_SPLIT_NO_EMPTY) : (is_array($list) ? array_filter($list, 'is_string') : []);

        return $ips === [] || $ips === false || IpUtils::checkIp((string) $request->ip(), array_values($ips));
    }

    private function maxBody(): int
    {
        $max = $this->config->get('webx-payments.notify.max_body', 1024 * 1024);

        return is_numeric($max) ? max(1, (int) $max) : 1024 * 1024;
    }

    private static function plain(string $text, int $status): Response
    {
        return new Response($text, $status, ['Content-Type' => 'text/plain']);
    }
}
