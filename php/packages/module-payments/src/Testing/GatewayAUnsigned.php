<?php

declare(strict_types=1);

namespace WebxUi\Payments\Testing;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\GatewayAmount;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\Handoff;
use WebxUi\Payments\InvalidNotification;
use WebxUi\Payments\Method;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Notification;
use WebxUi\Payments\Reported;
use WebxUi\Payments\StartNeeds;
use WebxUi\Payments\StartRequest;

/**
 * `gateway-a.unsigned` (spec §21.1) — the minimum without trust (decision 33): a form of plain
 * fields with the buyer's phone, a status by GET with the password in the query — the journal
 * wipes it by value (§20.5) —, and notifications without a signature: only a reason to ask.
 */
final class GatewayAUnsigned extends GatewayAType
{
    public function statuses(): array
    {
        return [
            'CREATED' => Reported::Pending,
            'PAYED' => Reported::Paid,
            'REJECTED' => Reported::Failed,
            'CANCELLED' => Reported::Cancelled,
            'EXPIRED' => Reported::Expired,
            'RETURN' => Reported::Refunded,
        ];
    }

    public function needs(): StartNeeds
    {
        return new StartNeeds(phone: true);
    }

    public function trustsNotifications(): bool
    {
        return false;
    }

    public function start(Method $method, Credentials $credentials, StartRequest $request, GatewayHttp $http): Handoff
    {
        return Handoff::post($this->server->pageUrl($request->uuid, 'open'), array_filter([
            'shop_id' => FakeGateway::SHOP,
            'order_id' => $request->uuid,
            'amount' => GatewayAmount::toDecimal($request->amount, $request->currency),
            'currency' => $request->currency,
            'description' => $request->description,
            'success_url' => $request->returnUrl,
            'failure_url' => $request->returnUrl,
            'phone' => $request->customerPhone,
        ], static fn (?string $value): bool => $value !== null), expiresAt: $request->expiresAt);
    }

    public function fetchStatus(Method $method, Credentials $credentials, Payment $payment, GatewayHttp $http): GatewayStatus
    {
        $http = $http->kind(ExchangeKind::Status);
        $answer = self::call(static fn () => $http->request()->get(FakeGateway::API.'/v1/unsigned/status', [
            'shop_id' => FakeGateway::SHOP,
            'password' => (string) $credentials->get('secret_key'),
            'order_id' => $payment->uuid,
        ]));

        if ($answer->status() === 404) {
            return self::unknownPayment($answer->json('error'));
        }

        if (! $answer->successful()) {
            throw GatewayUnavailable::answered($answer->status());
        }

        $body = self::json($answer);
        $currency = is_string($body['currency'] ?? null) ? $body['currency'] : throw GatewayUnavailable::malformed();
        $gatewayStatus = is_string($body['status'] ?? null) ? $body['status'] : '';
        $reported = $this->reported($gatewayStatus);

        return new GatewayStatus(
            status: $reported,
            gatewayRef: null,
            amount: self::amount($body['amount'] ?? null, $currency),
            currency: $currency,
            paidAt: $reported === Reported::Paid ? self::time($body['paid_at'] ?? null) : null,
            reason: $this->unknown($gatewayStatus),
        );
    }

    /** Whatever the body says, it is believed in nothing but which attempt to ask about. */
    public function readNotification(Method $method, Credentials $credentials, Request $request, GatewayHttp $http): Notification
    {
        $raw = $request->getContent();
        parse_str($raw, $fields);

        if (! is_string($fields['order_id'] ?? null) || $fields['order_id'] === '') {
            throw InvalidNotification::malformed();
        }

        return Notification::poke(Notification::keyOf($raw), $fields['order_id'], null);
    }

    public function acceptResponse(Method $method, Credentials $credentials, Notification $notification): Response
    {
        return new Response('OK', 200, ['Content-Type' => 'text/plain']);
    }

    public function rejectResponse(Method $method, Credentials $credentials, InvalidNotification $e): Response
    {
        return new Response('ERROR', 400, ['Content-Type' => 'text/plain']);
    }

    protected function kindKey(): string
    {
        return 'unsigned';
    }

    protected function defaultTitle(): string
    {
        return 'Gateway A (unsigned)';
    }
}
