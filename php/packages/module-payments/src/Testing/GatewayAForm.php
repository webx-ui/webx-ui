<?php

declare(strict_types=1);

namespace WebxUi\Payments\Testing;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Payments\Contracts\CompletesApproval;
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
 * `gateway-a.form` (spec §21.1) — the minimum and a server step: the browser posts signed `data`
 * and `signature` (base64 JSON, base64 HMAC-SHA256) to the gateway's page, no API call to open;
 * an approved payment is not paid until the shop completes it (`CompletesApproval`); the
 * notifications are signed the same way and wait for a signed answer.
 */
final class GatewayAForm extends GatewayAType implements CompletesApproval
{
    public function statuses(): array
    {
        return [
            'wait' => Reported::Pending,
            // Approved is not paid: the money is taken by complete() (§6.4).
            'approved' => Reported::Pending,
            'success' => Reported::Paid,
            'failure' => Reported::Failed,
            'cancelled' => Reported::Cancelled,
            'expired' => Reported::Expired,
            'reversed' => Reported::Refunded,
        ];
    }

    public function needs(): StartNeeds
    {
        return new StartNeeds;
    }

    public function trustsNotifications(): bool
    {
        return true;
    }

    public function start(Method $method, Credentials $credentials, StartRequest $request, GatewayHttp $http): Handoff
    {
        $data = base64_encode((string) json_encode([
            'shop_id' => FakeGateway::SHOP,
            'order_id' => $request->uuid,
            'amount' => GatewayAmount::toDecimal($request->amount, $request->currency),
            'currency' => $request->currency,
            'description' => $request->description,
            'result_url' => $request->returnUrl,
            'server_url' => $request->notifyUrl,
            'language' => $request->locale,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return Handoff::post($this->server->pageUrl($request->uuid, 'open'), [
            'data' => $data,
            'signature' => self::sign($data, $credentials),
        ], expiresAt: $request->expiresAt);
    }

    public function fetchStatus(Method $method, Credentials $credentials, Payment $payment, GatewayHttp $http): GatewayStatus
    {
        return $this->ask($credentials, $payment, $http->kind(ExchangeKind::Status), '/v1/form/status');
    }

    public function complete(Method $method, Credentials $credentials, Payment $payment, GatewayHttp $http): GatewayStatus
    {
        return $this->ask($credentials, $payment, $http->kind(ExchangeKind::Complete), '/v1/form/complete');
    }

    public function readNotification(Method $method, Credentials $credentials, Request $request, GatewayHttp $http): Notification
    {
        $raw = $request->getContent();
        parse_str($raw, $fields);
        $data = is_string($fields['data'] ?? null) ? $fields['data'] : '';
        $signature = is_string($fields['signature'] ?? null) ? $fields['signature'] : '';

        if ($data === '' || ! hash_equals(self::sign($data, $credentials), $signature)) {
            $claim = json_decode((string) base64_decode($data, true), true);

            throw InvalidNotification::signature(uuid: is_array($claim) && is_string($claim['order_id'] ?? null) ? $claim['order_id'] : null);
        }

        $payload = json_decode((string) base64_decode($data, true), true);

        if (! is_array($payload) || ! is_string($payload['order_id'] ?? null)) {
            throw InvalidNotification::malformed();
        }

        try {
            $status = $this->status($payload);
        } catch (GatewayUnavailable) {
            throw InvalidNotification::malformed();
        }

        return Notification::status(Notification::keyOf($raw), $payload['order_id'], $status->gatewayRef, $status);
    }

    /** A signed answer, as the gateway waits for it: otherwise it repeats the notification. */
    public function acceptResponse(Method $method, Credentials $credentials, Notification $notification): Response
    {
        $data = base64_encode((string) json_encode(['order_id' => $notification->uuid, 'status' => 'accept', 'time' => time()], JSON_UNESCAPED_SLASHES));

        return new JsonResponse(['data' => $data, 'signature' => self::sign($data, $credentials)], 200);
    }

    public function rejectResponse(Method $method, Credentials $credentials, InvalidNotification $e): Response
    {
        return new Response('rejected', 400, ['Content-Type' => 'text/plain']);
    }

    public static function sign(string $data, Credentials $credentials): string
    {
        return base64_encode(hash_hmac('sha256', $data, (string) $credentials->get('secret_key'), true));
    }

    protected function kindKey(): string
    {
        return 'form';
    }

    protected function defaultTitle(): string
    {
        return 'Gateway A page';
    }

    private function ask(Credentials $credentials, Payment $payment, GatewayHttp $http, string $path): GatewayStatus
    {
        $data = base64_encode((string) json_encode(['shop_id' => FakeGateway::SHOP, 'order_id' => $payment->uuid], JSON_UNESCAPED_SLASHES));
        $answer = self::call(static fn () => $http->request()->asForm()->post(FakeGateway::API.$path, [
            'data' => $data,
            'signature' => self::sign($data, $credentials),
        ]));

        if (! $answer->successful()) {
            throw GatewayUnavailable::answered($answer->status());
        }

        $body = self::json($answer);
        $signed = is_string($body['data'] ?? null) ? $body['data'] : '';

        // The gateway signs its answers too: an unsigned one is not the gateway's.
        if (! hash_equals(self::sign($signed, $credentials), is_string($body['signature'] ?? null) ? $body['signature'] : '')) {
            throw GatewayUnavailable::malformed('signature');
        }

        $payload = json_decode((string) base64_decode($signed, true), true);

        if (! is_array($payload)) {
            throw GatewayUnavailable::malformed();
        }

        if (($payload['status'] ?? null) === 'error') {
            return new GatewayStatus(Reported::Pending, null, reason: 'The gateway does not know the payment');
        }

        return $this->status($payload);
    }

    /** @param  array<string, mixed>  $payload */
    private function status(array $payload): GatewayStatus
    {
        $currency = is_string($payload['currency'] ?? null) ? $payload['currency'] : throw GatewayUnavailable::malformed();
        $gatewayStatus = is_string($payload['status'] ?? null) ? $payload['status'] : '';
        $reported = $this->reported($gatewayStatus);

        return new GatewayStatus(
            status: $reported,
            gatewayRef: is_string($payload['payment_id'] ?? null) ? $payload['payment_id'] : null,
            amount: self::amount($payload['amount'] ?? null, $currency),
            currency: $currency,
            paidAt: $reported === Reported::Paid ? self::time($payload['paid_at'] ?? null) : null,
            reason: $this->unknown($gatewayStatus) ?? ($gatewayStatus === 'approved' ? 'Approved, waits for the shop to complete' : (is_string($payload['err_description'] ?? null) ? $payload['err_description'] : null)),
        );
    }
}
