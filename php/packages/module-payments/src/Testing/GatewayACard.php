<?php

declare(strict_types=1);

namespace WebxUi\Payments\Testing;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Payments\Contracts\CancelsPayments;
use WebxUi\Payments\Contracts\CapturesPayments;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\GatewayAmount;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\GatewayRefused;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\Handoff;
use WebxUi\Payments\InvalidNotification;
use WebxUi\Payments\Method;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Notification;
use WebxUi\Payments\PaymentLine;
use WebxUi\Payments\Reported;
use WebxUi\Payments\StartNeeds;
use WebxUi\Payments\StartRequest;

/**
 * `gateway-a.card` (spec §21.1) — everything a gateway may have: a payment made by API and a
 * redirect to its page, the e-mail and the lines of the receipt, closing an unpaid payment, a hold
 * with a full or a partial capture and a void; notifications in signed JSON (HMAC-SHA256 of the raw
 * body, hex, `X-Gateway-A-Signature`) answered `{"received": true}`. Not final: a test spoils it
 * on purpose (a request past GatewayHttp).
 */
class GatewayACard extends GatewayAType implements CancelsPayments, CapturesPayments
{
    public const string SIGNATURE_HEADER = 'X-Gateway-A-Signature';

    public function statuses(): array
    {
        return [
            'created' => Reported::Pending,
            'processing' => Reported::Pending,
            'authorized' => Reported::Authorized,
            'captured' => Reported::Paid,
            'declined' => Reported::Failed,
            'cancelled' => Reported::Cancelled,
            'expired' => Reported::Expired,
            'refunded' => Reported::Refunded,
        ];
    }

    public function settings(): array
    {
        return [
            ...parent::settings(),
            ['name' => 'hold', 'type' => 'switch', 'label' => 'Hold the money, capture later', 'default' => false],
        ];
    }

    public function needs(): StartNeeds
    {
        return new StartNeeds(email: true, lines: true);
    }

    public function trustsNotifications(): bool
    {
        return true;
    }

    public function maxHoldDays(): int
    {
        return 7;
    }

    public function capturesPartially(): bool
    {
        return true;
    }

    public function start(Method $method, Credentials $credentials, StartRequest $request, GatewayHttp $http): Handoff
    {
        $token = GatewayAToken::fetch($credentials, $http);
        $answer = self::call(static fn () => $http->request()->withToken($token)->asJson()->post(FakeGateway::API.'/v1/payments', [
            'shop_id' => FakeGateway::SHOP,
            'order_id' => $request->uuid,
            'amount' => GatewayAmount::toDecimal($request->amount, $request->currency),
            'currency' => $request->currency,
            'description' => $request->description,
            'capture' => ! $request->hold,
            'return_url' => $request->returnUrl,
            'notify_url' => $request->notifyUrl,
            'language' => $request->locale,
            'customer' => ['email' => $request->customerEmail],
            'items' => array_map(static fn (PaymentLine $line): array => [
                'name' => $line->name,
                'qty' => $line->qty,
                'price' => GatewayAmount::toDecimal($line->unitPrice, $request->currency),
                'total' => GatewayAmount::toDecimal($line->total, $request->currency),
            ], $request->lines),
            'expires_at' => $request->expiresAt?->toIso8601String(),
        ]));

        if ($answer->status() !== 201) {
            throw GatewayUnavailable::answered($answer->status());
        }

        $body = self::json($answer);

        if (! is_string($body['checkout_url'] ?? null) || ! is_string($body['id'] ?? null)) {
            throw GatewayUnavailable::malformed();
        }

        return Handoff::redirect($body['checkout_url'], $body['id'], self::time($body['expires_at'] ?? null));
    }

    public function fetchStatus(Method $method, Credentials $credentials, Payment $payment, GatewayHttp $http): GatewayStatus
    {
        $http = $http->kind(ExchangeKind::Status);
        $token = GatewayAToken::fetch($credentials, $http);
        $answer = self::call(static fn () => $http->request()->withToken($token)->get(FakeGateway::API.'/v1/payments/'.self::ref($payment)));

        if ($answer->status() === 404) {
            return new GatewayStatus(Reported::Pending, $payment->gateway_ref, reason: 'The gateway does not know the payment');
        }

        if (! $answer->successful()) {
            throw GatewayUnavailable::answered($answer->status());
        }

        return $this->status(self::json($answer));
    }

    public function cancel(Method $method, Credentials $credentials, Payment $payment, GatewayHttp $http): bool
    {
        $http = $http->kind(ExchangeKind::Cancel);
        $token = GatewayAToken::fetch($credentials, $http);
        $answer = self::call(static fn () => $http->request()->withToken($token)->post(FakeGateway::API.'/v1/payments/'.self::ref($payment).'/cancel'));

        return match (true) {
            $answer->successful() => true,
            in_array($answer->status(), [404, 409], true) => false,
            default => throw GatewayUnavailable::answered($answer->status()),
        };
    }

    public function capture(Method $method, Credentials $credentials, Payment $payment, int $amount, GatewayHttp $http): GatewayStatus
    {
        $http = $http->kind(ExchangeKind::Capture);
        $token = GatewayAToken::fetch($credentials, $http);
        $answer = self::call(static fn () => $http->request()->withToken($token)->asJson()->post(
            FakeGateway::API.'/v1/payments/'.self::ref($payment).'/capture',
            ['amount' => GatewayAmount::toDecimal($amount, $payment->currency)],
        ));

        return $this->answer($answer, 'The gateway refused the capture');
    }

    public function void(Method $method, Credentials $credentials, Payment $payment, GatewayHttp $http): GatewayStatus
    {
        $http = $http->kind(ExchangeKind::Void);
        $token = GatewayAToken::fetch($credentials, $http);
        $answer = self::call(static fn () => $http->request()->withToken($token)->post(FakeGateway::API.'/v1/payments/'.self::ref($payment).'/void'));

        return $this->answer($answer, 'The gateway refused to release the hold');
    }

    public function readNotification(Method $method, Credentials $credentials, Request $request, GatewayHttp $http): Notification
    {
        $raw = $request->getContent();
        $signature = (string) $request->headers->get(self::SIGNATURE_HEADER, '');
        $expected = hash_hmac('sha256', $raw, (string) $credentials->get('secret_key'));

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            // What the body claims, for the journal's link only (§6.3): nothing of it is believed.
            $claim = json_decode($raw, true);
            $data = is_array($claim) && is_array($claim['data'] ?? null) ? $claim['data'] : [];

            throw InvalidNotification::signature(
                uuid: is_string($data['order_id'] ?? null) ? $data['order_id'] : null,
                gatewayRef: is_string($data['id'] ?? null) ? $data['id'] : null,
            );
        }

        $event = json_decode($raw, true);

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null) || ! is_array($event['data'] ?? null)) {
            throw InvalidNotification::malformed();
        }

        if ($event['type'] !== 'payment.updated') {
            return Notification::unrelated($event['id'], 'Not about a payment: '.$event['type'], gatewayRef: is_string($event['data']['payment_id'] ?? null) ? $event['data']['payment_id'] : null);
        }

        try {
            $status = $this->status($event['data']);
        } catch (GatewayUnavailable) {
            throw InvalidNotification::malformed();
        }

        return Notification::status($event['id'], is_string($event['data']['order_id'] ?? null) ? $event['data']['order_id'] : null, $status->gatewayRef, $status);
    }

    public function acceptResponse(Method $method, Credentials $credentials, Notification $notification): Response
    {
        return new JsonResponse(['received' => true], 200);
    }

    public function rejectResponse(Method $method, Credentials $credentials, InvalidNotification $e): Response
    {
        return new JsonResponse(['error' => $e->reason], 400);
    }

    protected function kindKey(): string
    {
        return 'card';
    }

    protected function defaultTitle(): string
    {
        return 'Card (gateway A)';
    }

    /** @throws GatewayRefused|GatewayUnavailable */
    private function answer(\Illuminate\Http\Client\Response $answer, string $refusal): GatewayStatus
    {
        if (in_array($answer->status(), [409, 422], true)) {
            throw GatewayRefused::because($refusal);
        }

        if (! $answer->successful()) {
            throw GatewayUnavailable::answered($answer->status());
        }

        return $this->status(self::json($answer));
    }

    /** @param  array<string, mixed>  $payment */
    private function status(array $payment): GatewayStatus
    {
        $currency = is_string($payment['currency'] ?? null) ? $payment['currency'] : throw GatewayUnavailable::malformed();
        $gatewayStatus = is_string($payment['status'] ?? null) ? $payment['status'] : '';
        $reported = $this->reported($gatewayStatus);

        $amount = match ($reported) {
            Reported::Paid => self::amount($payment['captured_amount'] ?? $payment['amount'] ?? null, $currency),
            Reported::Authorized => self::amount($payment['authorized_amount'] ?? $payment['amount'] ?? null, $currency),
            default => self::amount($payment['amount'] ?? null, $currency),
        };

        return new GatewayStatus(
            status: $reported,
            gatewayRef: is_string($payment['id'] ?? null) ? $payment['id'] : null,
            amount: $amount,
            currency: $currency,
            paidAt: $reported === Reported::Paid ? self::time($payment['paid_at'] ?? null) : null,
            reason: $this->unknown($gatewayStatus) ?? (is_string($payment['decline_reason'] ?? null) ? $payment['decline_reason'] : null),
            refundedAmount: self::amount($payment['refunded_amount'] ?? null, $currency),
            holdExpiresAt: $reported === Reported::Authorized ? self::time($payment['hold_expires_at'] ?? null) : null,
            meta: is_string($payment['card']['mask'] ?? null) ? ['card' => $payment['card']['mask']] : [],
        );
    }

    /** The gateway's id of the payment: given at start, or made of our uuid the same way. */
    private static function ref(Payment $payment): string
    {
        return $payment->gateway_ref ?? 'ga_'.strtolower($payment->uuid);
    }
}
