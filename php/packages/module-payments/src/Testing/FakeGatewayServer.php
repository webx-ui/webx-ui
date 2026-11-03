<?php

declare(strict_types=1);

namespace WebxUi\Payments\Testing;

use Carbon\CarbonImmutable;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use WebxUi\Payments\GatewayAmount;

/**
 * The API of «gateway A» (spec §21.1), answered inside the process at `gateway-a.test`
 * (`InProcessHosts`): the journal sees its requests and answers as real ones, and the site never
 * calls itself over the network. The gateway's payments live in the site's cache
 * (`webx-payments:fake:{uuid}`, 7 days): after a flush of the cache the gateway «forgets» a payment
 * and answers «not found» — that is a case too.
 *
 * Three APIs, one per type of gateway A:
 *
 * - `card` — OAuth-like token (`POST /v1/token`, `X-Gateway-A-Key`), then JSON with a bearer:
 *   create, status, cancel, capture, void; notifications — signed JSON (HMAC-SHA256, hex, header
 *   `X-Gateway-A-Signature`);
 * - `form` — signed `data` + `signature` (base64 JSON, base64 HMAC-SHA256) both ways: status and
 *   complete; notifications the same, and it waits for a signed answer;
 * - `unsigned` — a status by GET with the password in the query; notifications without a
 *   signature.
 *
 * What a test needs to go wrong goes wrong on request — `failNext()`; the same failures are
 * buttons of the gateway's page on a development site.
 */
final class FakeGatewayServer
{
    public const string HOST = 'gateway-a.test';

    /** A timeout: the gateway may have done what it was asked, the answer never came. */
    public const string TIMEOUT = 'timeout';

    public const string SERVER_ERROR = 'server_error';

    public const string NOT_FOUND = 'not_found';

    public const string UNKNOWN_STATUS = 'unknown_status';

    public const string WRONG_AMOUNT = 'wrong_amount';

    public const string CAPTURE_REFUSED = 'capture_refused';

    public const array FAILURES = [self::TIMEOUT, self::SERVER_ERROR, self::NOT_FOUND, self::UNKNOWN_STATUS, self::WRONG_AMOUNT, self::CAPTURE_REFUSED];

    /** What the 500 of `server_error` says: a test checks the words for the panel do not carry it. */
    public const string ERROR_BODY = 'gateway-a internal error #0001';

    private const string PREFIX = 'webx-payments:fake:';

    private const int DAYS = 7;

    /** @var list<string> «METHOD /path» of every request served, in order */
    private array $served = [];

    public function __construct(
        private readonly Cache $cache,
        private readonly Router $router,
    ) {}

    /** Every request the API answered (or failed) since this object was made. */
    public function served(): int
    {
        return count($this->served);
    }

    /** @return list<string> */
    public function log(): array
    {
        return $this->served;
    }

    /** The next request that this failure fits fails so. */
    public function failNext(string $failure): void
    {
        if (! in_array($failure, self::FAILURES, true)) {
            throw new InvalidArgumentException("[{$failure}] is not a failure of gateway A: ".implode(', ', self::FAILURES).'.');
        }

        $this->cache->put(self::PREFIX.'fail', [...$this->failures(), $failure], now()->addDays(self::DAYS));
    }

    /** @return array<string, mixed>|null */
    public function payment(string $uuid): ?array
    {
        $state = $this->cache->get(self::PREFIX.$uuid);

        return is_array($state) ? $state : null;
    }

    /** @param  array<string, mixed>  $state */
    public function put(array $state): void
    {
        $this->cache->put(self::PREFIX.$state['uuid'], $state, now()->addDays(self::DAYS));
    }

    public function forget(string $uuid): void
    {
        $this->cache->forget(self::PREFIX.$uuid);
    }

    /**
     * A payment the gateway knows, in its own status — what a recorded answer is for a real
     * satellite's contract test.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function open(string $uuid, string $type, int $amount, string $currency, string $status, array $extra = []): array
    {
        $state = [
            'uuid' => $uuid,
            'type' => $type,
            'id' => 'ga_'.strtolower($uuid),
            'status' => $status,
            'amount' => $amount,
            'currency' => strtoupper($currency),
            'capture' => true,
            'authorized' => null,
            'captured' => null,
            'refunded' => null,
            'charges' => 0,
            'return_url' => null,
            'notify_url' => null,
            'email' => null,
            'phone' => null,
            'lines' => 0,
            'paid_at' => null,
            'hold_expires_at' => null,
            'reason' => null,
            'card' => '4444********1111',
            ...$extra,
        ];
        $this->put($state);

        return $state;
    }

    /**
     * What the gateway's page does on a button (§21.2): the payment's new status at the gateway.
     * The notification and the way back to the shop are the site's (/payments/notify, /pay/return).
     *
     * @return array<string, mixed>|null
     */
    public function act(string $uuid, string $action): ?array
    {
        $state = $this->payment($uuid);

        if ($state === null) {
            return null;
        }

        $now = CarbonImmutable::now()->toIso8601String();
        $paid = ['card' => 'captured', 'form' => 'success', 'unsigned' => 'PAYED'][$state['type']] ?? 'captured';

        $state = match ($action) {
            'pay', 'pay-later' => $state['type'] === 'card' && ! $state['capture']
                ? [...$state, 'status' => 'authorized', 'authorized' => $state['amount'], 'hold_expires_at' => CarbonImmutable::now()->addDays(7)->toIso8601String()]
                : [...$state, 'status' => $paid, 'captured' => $state['amount'], 'charges' => $state['charges'] + 1, 'paid_at' => $now],
            'hold' => [...$state, 'status' => 'authorized', 'authorized' => $state['amount'], 'hold_expires_at' => CarbonImmutable::now()->addDays(7)->toIso8601String()],
            'approve' => [...$state, 'status' => 'approved'],
            'decline' => [...$state, 'status' => ['card' => 'declined', 'form' => 'failure', 'unsigned' => 'REJECTED'][$state['type']], 'reason' => 'Card declined (test)'],
            'cancel' => [...$state, 'status' => ['card' => 'cancelled', 'form' => 'cancelled', 'unsigned' => 'CANCELLED'][$state['type']]],
            default => $state,
        };

        $this->put($state);

        return $state;
    }

    /**
     * A notification as gateway A would post it to the site (§21.2): the raw body and headers of
     * the type's kind — `valid`, or spoiled: `signature` (a broken signature), `amount` and
     * `status` (the body changed under the old signature), `foreign` (an attempt nobody has),
     * `unrelated` (an event not about a payment; `card` only).
     */
    public function notification(string $uuid, string $variant = 'valid'): Request
    {
        $state = $this->payment($uuid) ?? $this->open($uuid, 'card', 0, 'EUR', 'created');

        if ($variant === 'foreign') {
            $state = [...$state, 'uuid' => (string) Str::ulid(), 'id' => 'ga_foreign'];
        }

        $url = $state['notify_url'] ?? 'https://shop.test/payments/notify/gateway-a-'.$state['type'];

        return match ($state['type']) {
            'form' => $this->formNotification($state, $variant, (string) $url),
            'unsigned' => $this->unsignedNotification($state, $variant, (string) $url),
            default => $this->cardNotification($state, $variant, (string) $url),
        };
    }

    /** The address of the gateway's page for a payment: the site's /pay/fake/{uuid} where it is served. */
    public function pageUrl(string $uuid, ?string $action = null): string
    {
        $route = $action === null ? FakeGatewaySite::PAGE : FakeGatewaySite::ACTION;

        if ($this->router->has($route)) {
            return route($route, $action === null ? ['uuid' => $uuid] : ['uuid' => $uuid, 'action' => $action]);
        }

        return 'https://'.self::HOST.'/checkout/'.$uuid.($action !== null ? '/'.$action : '');
    }

    /** The API: one request in, one answer out — or the failure it was told to have. */
    public function __invoke(RequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $this->served[] = $request->getMethod().' '.$path;

        if ($this->take(self::TIMEOUT, $path !== '/v1/token')) {
            // The gateway did what it was asked, the answer never came: a payment may exist (§6.3).
            $this->route($request, $path);

            throw new ConnectException('cURL error 28: Operation timed out after 10000 milliseconds', $request);
        }

        if ($path !== '/v1/token' && $this->take(self::SERVER_ERROR)) {
            return new Response(500, ['Content-Type' => 'text/plain'], self::ERROR_BODY);
        }

        return $this->route($request, $path);
    }

    private function route(RequestInterface $request, string $path): ResponseInterface
    {
        $method = $request->getMethod();

        return match (true) {
            $method === 'POST' && $path === '/v1/token' => $this->token($request),
            $method === 'GET' && $path === '/v1/account' => $this->authorized($request) ? self::json(200, ['shop_id' => FakeGateway::SHOP]) : self::json(401, ['error' => 'unauthorized']),
            $method === 'POST' && $path === '/v1/payments' => $this->create($request),
            $method === 'GET' && preg_match('#^/v1/payments/([^/]+)$#', $path, $m) === 1 => $this->cardStatus($request, $m[1]),
            $method === 'POST' && preg_match('#^/v1/payments/([^/]+)/(cancel|capture|void)$#', $path, $m) === 1 => $this->cardAction($request, $m[1], $m[2]),
            $method === 'POST' && $path === '/v1/form/status' => $this->formCall($request, false),
            $method === 'POST' && $path === '/v1/form/complete' => $this->formCall($request, true),
            $method === 'GET' && $path === '/v1/unsigned/status' => $this->unsignedStatus($request),
            default => self::json(404, ['error' => 'no such endpoint']),
        };
    }

    // ---- card ---------------------------------------------------------------------------------

    private function token(RequestInterface $request): ResponseInterface
    {
        if ($request->getHeaderLine('X-Gateway-A-Key') !== FakeGateway::SECRET) {
            return self::json(401, ['error' => 'invalid key']);
        }

        $token = 'tok_'.Str::random(32);
        $this->cache->put(self::PREFIX.'token:'.$token, true, now()->addMinutes(10));

        return self::json(200, ['access_token' => $token, 'token_type' => 'bearer', 'expires_in' => 600]);
    }

    private function authorized(RequestInterface $request): bool
    {
        $token = preg_replace('/^Bearer\s+/i', '', $request->getHeaderLine('Authorization'));

        return is_string($token) && $token !== '' && $this->cache->has(self::PREFIX.'token:'.$token);
    }

    private function create(RequestInterface $request): ResponseInterface
    {
        if (! $this->authorized($request)) {
            return self::json(401, ['error' => 'unauthorized']);
        }

        $body = json_decode((string) $request->getBody(), true);

        if (! is_array($body) || ! is_string($body['order_id'] ?? null) || ! is_string($body['amount'] ?? null) || ! is_string($body['currency'] ?? null)) {
            return self::json(422, ['error' => 'order_id, amount and currency are required']);
        }

        $uuid = $body['order_id'];
        $existing = $this->payment($uuid);

        if ($existing !== null && $existing['status'] !== 'created') {
            return self::json(409, ['error' => 'order_id is taken']);
        }

        $state = $this->open($uuid, 'card', GatewayAmount::fromDecimal($body['amount'], $body['currency']), $body['currency'], 'created', [
            'capture' => (bool) ($body['capture'] ?? true),
            'return_url' => $body['return_url'] ?? null,
            'notify_url' => $body['notify_url'] ?? null,
            'email' => $body['customer']['email'] ?? null,
            'lines' => is_array($body['items'] ?? null) ? count($body['items']) : 0,
        ]);

        return self::json(201, [
            'id' => $state['id'],
            'status' => 'created',
            'checkout_url' => $this->pageUrl($uuid),
            'expires_at' => CarbonImmutable::now()->addHour()->toIso8601String(),
        ]);
    }

    private function cardStatus(RequestInterface $request, string $id): ResponseInterface
    {
        if (! $this->authorized($request)) {
            return self::json(401, ['error' => 'unauthorized']);
        }

        $state = $this->byId($id);

        if ($state === null || $this->take(self::NOT_FOUND)) {
            return self::json(404, ['error' => 'payment not found']);
        }

        return self::json(200, $this->cardPayment($state));
    }

    private function cardAction(RequestInterface $request, string $id, string $action): ResponseInterface
    {
        if (! $this->authorized($request)) {
            return self::json(401, ['error' => 'unauthorized']);
        }

        $state = $this->byId($id);

        if ($state === null) {
            return self::json(404, ['error' => 'payment not found']);
        }

        if ($action === 'cancel') {
            if (! in_array($state['status'], ['created', 'processing'], true)) {
                return self::json(409, ['error' => 'the payment cannot be cancelled']);
            }

            $state = [...$state, 'status' => 'cancelled'];
        }

        if ($action === 'void') {
            if ($state['status'] !== 'authorized') {
                return self::json(409, ['error' => 'nothing is held']);
            }

            $state = [...$state, 'status' => 'cancelled'];
        }

        if ($action === 'capture') {
            $body = json_decode((string) $request->getBody(), true);
            $amount = is_array($body) && is_string($body['amount'] ?? null) ? GatewayAmount::fromDecimal($body['amount'], $state['currency']) : (int) $state['authorized'];

            if ($state['status'] !== 'authorized' || $amount <= 0 || $amount > (int) $state['authorized'] || $this->take(self::CAPTURE_REFUSED)) {
                return self::json(422, ['error' => 'the capture is refused']);
            }

            $state = [...$state, 'status' => 'captured', 'captured' => $amount, 'charges' => $state['charges'] + 1, 'paid_at' => CarbonImmutable::now()->toIso8601String()];
        }

        $this->put($state);

        return self::json(200, $this->cardPayment($state));
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function cardPayment(array $state): array
    {
        $amount = (int) $state['amount'];

        if ($this->take(self::WRONG_AMOUNT)) {
            $amount -= 100;
        }

        return [
            'id' => $state['id'],
            'order_id' => $state['uuid'],
            'status' => $this->take(self::UNKNOWN_STATUS) ? 'on_review' : $state['status'],
            'amount' => GatewayAmount::toDecimal($amount, $state['currency']),
            'currency' => $state['currency'],
            'authorized_amount' => $state['authorized'] !== null ? GatewayAmount::toDecimal((int) $state['authorized'], $state['currency']) : null,
            'captured_amount' => $state['captured'] !== null ? GatewayAmount::toDecimal((int) $state['captured'], $state['currency']) : null,
            'refunded_amount' => $state['refunded'] !== null ? GatewayAmount::toDecimal((int) $state['refunded'], $state['currency']) : null,
            'paid_at' => $state['paid_at'],
            'hold_expires_at' => $state['hold_expires_at'],
            'decline_reason' => $state['reason'],
            'card' => ['mask' => '4444****1111'],
        ];
    }

    /** @param  array<string, mixed>  $state */
    private function cardNotification(array $state, string $variant, string $url): Request
    {
        $payment = $this->cardPayment($state);
        $event = $variant === 'unrelated'
            ? ['id' => 'evt_dispute_'.strtolower((string) $state['uuid']), 'type' => 'dispute.created', 'data' => ['payment_id' => $state['id']]]
            : ['id' => 'evt_'.substr(hash('sha256', $state['uuid'].$state['status']), 0, 24), 'type' => 'payment.updated', 'data' => $payment];
        $signed = self::encode($event);

        $body = match ($variant) {
            'amount' => self::encode([...$event, 'data' => [...$event['data'], 'amount' => GatewayAmount::toDecimal((int) $state['amount'] + 100, $state['currency'])]]),
            'status' => self::encode([...$event, 'data' => [...$event['data'], 'status' => $state['status'] === 'captured' ? 'declined' : 'captured']]),
            default => $signed,
        };
        $signature = hash_hmac('sha256', $signed, FakeGateway::SECRET);

        if ($variant === 'signature') {
            $signature = strrev($signature);
        }

        return self::request($url, $body, ['CONTENT_TYPE' => 'application/json', 'HTTP_X_GATEWAY_A_SIGNATURE' => $signature]);
    }

    // ---- form ---------------------------------------------------------------------------------

    private function formCall(RequestInterface $request, bool $complete): ResponseInterface
    {
        parse_str((string) $request->getBody(), $fields);
        $data = is_string($fields['data'] ?? null) ? $fields['data'] : '';

        if (! hash_equals(self::sign($data), is_string($fields['signature'] ?? null) ? $fields['signature'] : '')) {
            return self::json(403, ['error' => 'bad signature']);
        }

        $asked = json_decode((string) base64_decode($data, true), true);
        $state = is_array($asked) && is_string($asked['order_id'] ?? null) ? $this->payment($asked['order_id']) : null;

        if ($state === null || $this->take(self::NOT_FOUND)) {
            return self::signedJson(['status' => 'error', 'err_code' => 'payment_not_found']);
        }

        // Complete charges once per payment: the order id is the key of idempotence.
        if ($complete && $state['status'] === 'approved') {
            $state = [...$state, 'status' => 'success', 'captured' => $state['amount'], 'charges' => $state['charges'] + 1, 'paid_at' => CarbonImmutable::now()->toIso8601String()];
            $this->put($state);
        }

        return self::signedJson($this->formPayment($state));
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function formPayment(array $state): array
    {
        $amount = (int) $state['amount'] - ($this->take(self::WRONG_AMOUNT) ? 100 : 0);

        return [
            'order_id' => $state['uuid'],
            'payment_id' => $state['id'],
            'status' => $this->take(self::UNKNOWN_STATUS) ? 'hold_wait_3ds' : $state['status'],
            'amount' => GatewayAmount::toDecimal($amount, $state['currency']),
            'currency' => $state['currency'],
            'paid_at' => $state['paid_at'],
            'err_description' => $state['reason'],
        ];
    }

    /** @param  array<string, mixed>  $state */
    private function formNotification(array $state, string $variant, string $url): Request
    {
        $payment = $this->formPayment($state);
        $data = base64_encode(self::encode($payment));
        $signature = self::sign($data);

        $data = match ($variant) {
            'amount' => base64_encode(self::encode([...$payment, 'amount' => GatewayAmount::toDecimal((int) $state['amount'] + 100, $state['currency'])])),
            'status' => base64_encode(self::encode([...$payment, 'status' => $state['status'] === 'success' ? 'failure' : 'success'])),
            default => $data,
        };

        if ($variant === 'signature') {
            $signature = base64_encode(str_repeat("\0", 32));
        }

        return self::request($url, http_build_query(['data' => $data, 'signature' => $signature]), ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
    }

    // ---- unsigned -----------------------------------------------------------------------------

    private function unsignedStatus(RequestInterface $request): ResponseInterface
    {
        parse_str($request->getUri()->getQuery(), $query);

        if (($query['password'] ?? null) !== FakeGateway::SECRET) {
            return self::json(401, ['error' => 'invalid password']);
        }

        $state = is_string($query['order_id'] ?? null) ? $this->payment($query['order_id']) : null;

        if ($state === null || $this->take(self::NOT_FOUND)) {
            return self::json(404, ['error' => 'payment not found']);
        }

        $amount = (int) $state['amount'] - ($this->take(self::WRONG_AMOUNT) ? 100 : 0);

        return self::json(200, [
            'order_id' => $state['uuid'],
            'status' => $this->take(self::UNKNOWN_STATUS) ? 'PREAUTH_WAIT' : $state['status'],
            'amount' => GatewayAmount::toDecimal($amount, $state['currency']),
            'currency' => $state['currency'],
            'paid_at' => $state['paid_at'],
        ]);
    }

    /** @param  array<string, mixed>  $state */
    private function unsignedNotification(array $state, string $variant, string $url): Request
    {
        $fields = ['order_id' => $state['uuid'], 'status' => $state['status'], 'amount' => GatewayAmount::toDecimal((int) $state['amount'], $state['currency'])];

        // Nothing to spoil but the data itself: a forged «paid» — the base must not believe it.
        if (in_array($variant, ['signature', 'status'], true)) {
            $fields['status'] = 'PAYED';
        }

        if ($variant === 'amount') {
            $fields['amount'] = GatewayAmount::toDecimal((int) $state['amount'] + 100, $state['currency']);
        }

        return self::request($url, http_build_query($fields), ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
    }

    // ---- helpers ------------------------------------------------------------------------------

    /** base64 HMAC-SHA256 of the form API with the test key. */
    public static function sign(string $data): string
    {
        return base64_encode(hash_hmac('sha256', $data, FakeGateway::SECRET, true));
    }

    /** @return array<string, mixed>|null */
    private function byId(string $id): ?array
    {
        if (! str_starts_with($id, 'ga_')) {
            return null;
        }

        $state = $this->payment(strtoupper(substr($id, 3)));

        return $state !== null && $state['id'] === $id ? $state : null;
    }

    /** Whether the next failure is this one; takes it off the list. */
    private function take(string $failure, bool $applies = true): bool
    {
        $failures = $this->failures();

        if (! $applies || ($failures[0] ?? null) !== $failure) {
            return false;
        }

        array_shift($failures);
        $this->cache->put(self::PREFIX.'fail', $failures, now()->addDays(self::DAYS));

        return true;
    }

    /** @return list<string> */
    private function failures(): array
    {
        $failures = $this->cache->get(self::PREFIX.'fail');

        return is_array($failures) ? array_values(array_filter($failures, 'is_string')) : [];
    }

    /** @param  array<string, mixed>  $body */
    private static function json(int $status, array $body): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'application/json'], self::encode($body));
    }

    /** @param  array<string, mixed>  $payload */
    private static function signedJson(array $payload): ResponseInterface
    {
        $data = base64_encode(self::encode($payload));

        return self::json(200, ['data' => $data, 'signature' => self::sign($data)]);
    }

    /** @param  array<string, mixed>  $value */
    private static function encode(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param  array<string, string>  $server */
    private static function request(string $url, string $body, array $server): Request
    {
        return Request::create($url, 'POST', [], [], [], [...$server, 'REMOTE_ADDR' => '192.0.2.10'], $body);
    }
}
