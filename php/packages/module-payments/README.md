# webx-ui/module-payments

Payments for a WebX UI shop on Laravel: the methods of the site, the attempts to pay an order, and
the journal of every exchange with a payment gateway.

This release is the base of the module (spec §23, stage П1): the schema, the gateway exchange
journal and the HTTP client gateway satellites use. Methods on receipt and by bank transfer, the
gateway contract, the pay page, the panel section and MCP come next.

## Install

```bash
composer require webx-ui/module-payments
php artisan migrate
```

Requires `webx-ui/module-orders` (and through it `webx-ui/module-cart`).

## The exchange journal

Everything sent to a gateway and everything it sends back is one row of `payment_gateway_events`:
direction, kind, URL, headers, the raw body as it went, status code, time, duration, the signature
check and the decision of the base. Rows are only added; the decision is written once.

```php
use WebxUi\Payments\Exchange\ExchangeContext;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Exchange\ExchangeSecrets;
use WebxUi\Payments\GatewayHttp;

$http = new GatewayHttp(
    app(ExchangeJournal::class),
    config(),
    new ExchangeContext(gateway: 'gateway-a', methodKey: 'gateway-a-card', paymentId: $payment->id, orderId: $payment->order_id, source: 'poll'),
    new ExchangeSecrets($credentials, headers: ['X-Token'], fields: ['access_token']),
    ExchangeKind::Status,
);

$answer = $http->request()->post('https://gateway-a.test/api/status', ['order_id' => $payment->uuid]);
```

- **Masking** — the method's keys wherever they stand (as is, url-encoded, JSON-escaped, in
  base64), secret headers, the gateway's secret fields and card numbers. The signature is kept.
- **Size** — a body is kept whole up to 1 MB (`webx-payments.exchange.body_limit`); above it the
  first megabyte, with the full size and sha256.
- **Its own connection** — a copy of the default connection: an event commits by itself and is not
  rolled back with a failed status change. A failed write is logged and the payment goes on.
- **Pruning** — never by itself: `php artisan webx:payments:exchange-prune --days=N`, `N` at least
  365, with `--dry-run` and a confirmation.

## Configuration

`php artisan vendor:publish --tag=webx-payments-config`:

| Key                                    | Default             | What                                 |
| -------------------------------------- | ------------------- | ------------------------------------ |
| `exchange.connection`                  | copy of the default | `WEBX_PAYMENTS_EXCHANGE_CONNECTION`  |
| `exchange.body_limit`                  | 1 MB                | bytes kept of one body, up to 16 MB  |
| `exchange.warn_size`                   | 1 GB                | `webx:doctor` notes a bigger journal |
| `http.timeout`, `http.connect_timeout` | 15, 5               | seconds of a gateway request         |

## License

MIT
