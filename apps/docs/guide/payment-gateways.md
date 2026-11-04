# Payment gateways

A payment gateway comes to a WebX UI shop as a satellite package, `payment-<gateway>`, next to
`webx-ui/module-payments`. The base owns everything around the money: the attempts and their
statuses, the order's milestones, the pay page, the return of the buyer, the notification route,
the poll, the hold's buttons and the exchange journal. The satellite only speaks the gateway's
language: it opens a payment, reads the gateway's notification, asks its API for a status — and,
where the gateway can, cancels, completes an approval, captures or releases a hold.

**Adding a gateway never changes the base.** What only some gateways can do comes as optional
interfaces; a satellite implements the ones its gateway has.

This guide writes one, «gateway B», after the model the base ships for its own tests —
`WebxUi\Payments\Testing\FakeGateway`, «gateway A», with three types: `card` (a hold, a partial
capture, a cancel), `form` (an approval to complete) and `unsigned` (notifications not believed).

## The package

```
payment-gateway-b/
  src/GatewayB.php                 # Gateway: keys, settings, secret headers, test of the keys
  src/Types/CardType.php           # ChargesOnline (+ the optional interfaces it can do)
  src/GatewayBServiceProvider.php
  config/webx-payment-gateway-b.php  # the API's addresses, test and live
  database/migrations/             # a method per type, switched off
  lang/                            # webx-payment-gateway-b: the gateway's own words only
  tests/GatewayContractTest.php    # on recorded answers and notifications
  tests/recorded/
  AGENTS.md, README.md
```

It requires `webx-ui/module-payments`. The gateway's name lives in the satellite only: the base,
its tests and its demo say «gateway A».

## The gateway

```php
use WebxUi\Payments\ConnectionResult;
use WebxUi\Payments\Contracts\Gateway;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\GatewayHttp;

final class GatewayB implements Gateway
{
    public function key(): string { return 'gateway-b'; }          // never 'store'

    public function title(string $locale): string { return 'Gateway B'; }

    public function icon(): ?string { return null; }

    /** Not secret, shared by the gateway's methods: the shop's id, the mode. */
    public function settings(): array
    {
        return [['name' => 'shop_id', 'type' => 'text', 'label' => 'Shop id']];
    }

    /** Stored encrypted, shown as «set», never sent back, never given to an agent. */
    public function credentials(): array
    {
        return [['name' => 'secret_key', 'type' => 'password', 'label' => 'Secret key', 'secret' => true]];
    }

    /** Headers the journal wipes whole, besides Authorization and the cookies. */
    public function secretHeaders(): array { return ['X-Gateway-B-Key']; }

    /** Fields of an answer that are secrets made at run time — an OAuth token. */
    public function secretFields(): array { return ['access_token']; }

    public function testConnection(Credentials $credentials, array $settings, GatewayHttp $http): ConnectionResult
    {
        $answer = $http->request()
            ->withHeaders(['X-Gateway-B-Key' => (string) $credentials->get('secret_key')])
            ->get(config('webx-payment-gateway-b.api').'/v1/me');

        return $answer->successful()
            ? ConnectionResult::ok()
            : ConnectionResult::failed('The gateway refused the key.');   // words, never its body
    }
}
```

The provider registers it, and its types, once the base is there:

```php
public function register(): void
{
    $this->callAfterResolving(Gateways::class, fn (Gateways $gateways) => $gateways->register(GatewayB::class));
    $this->callAfterResolving(MethodTypes::class, fn (MethodTypes $types) => $types->register(CardType::class));
}
```

The migration creates a method for each type, switched off: the shop types the keys and switches it
on in **Payments → Methods**.

## A type

A type is one way to pay through the gateway, keyed `<gateway>.<kind>` for ever — `gateway-b.card`.
It never reads the database, the environment or `payment_methods`: the base hands it the method
(`Method`, read-only), the keys (`Credentials`) and the HTTP client (`GatewayHttp`).

```php
final class CardType implements ChargesOnline, CancelsPayments
{
    public function key(): string { return 'gateway-b.card'; }
    public function provider(): string { return 'gateway-b'; }
    public function kind(): Kind { return Kind::Online; }
    public function awaitsPayment(): bool { return true; }
    public function currencies(): ?array { return ['EUR']; }        // null — any

    /** What start() needs of the buyer and the order: the base gives only these. */
    public function needs(): StartNeeds { return new StartNeeds(email: true, lines: true); }

    /** Signed, and the signature covers the data. False — never believed, only a reason to ask. */
    public function trustsNotifications(): bool { return true; }

    public function start(Method $method, Credentials $credentials, StartRequest $request, GatewayHttp $http): Handoff
    {
        $answer = $http->request()->asJson()->post($this->api('/v1/payments'), [
            'order_id' => $request->uuid,                 // our attempt: the key of every notification
            'amount' => GatewayAmount::toDecimal($request->amount, $request->currency),
            'currency' => $request->currency,
            'description' => $request->description,
            'return_url' => $request->returnUrl,
            'notify_url' => $request->notifyUrl,
            'capture' => ! $request->hold,
        ]);

        if ($answer->status() !== 201) {
            throw GatewayUnavailable::answered($answer->status());
        }

        return Handoff::redirect($answer->json('checkout_url'), $answer->json('id'));
    }

    public function fetchStatus(Method $method, Credentials $credentials, Payment $payment, GatewayHttp $http): GatewayStatus
    {
        $answer = $http->kind(ExchangeKind::Status)->request()->get($this->api('/v1/payments/'.$payment->gateway_ref));

        return $this->status($answer->json());     // the gateway's word → Reported, by the table below
    }

    // readNotification(), acceptResponse(), rejectResponse(), cancel(), present(), snapshot(), …
}
```

- **`fetchStatus()` of a payment the gateway does not have because the buyer never began it** —
  they cancelled on the gateway's page or closed it — answers
  `new GatewayStatus(Reported::Pending, notStarted: true)`. The attempt stays open to its deadline
  and a late payment counts, but the buyer sees «Payment not completed» with «Pay again» and
  «Choose another method», not an endless «We are checking». Any status but `Pending` with
  `notStarted` throws.
- **`start()`** is called once per attempt, when the buyer goes to `GET /pay/go/{uuid}` — never when
  the order is placed. `Handoff::redirect()` sends the buyer to the gateway's page; `Handoff::post()`
  posts a signed form to it — and throws on a card field: the shop never takes a card.
- **Amounts** go as `GatewayAmount::toDecimal()` and come back as `GatewayAmount::fromDecimal()`:
  minor units, never a float.
- **Statuses.** The satellite keeps its gateway's table: each word of the gateway → `Reported`
  (`Pending`, `Authorized`, `Paid`, `Failed`, `Cancelled`, `Expired`, `Refunded`). A word it does not
  know is `Pending` with a reason — never a guess. «Finished without a charge» is not `Paid`.
- **Errors.** No answer, a 5xx, a body that is not the API's — `GatewayUnavailable`; a refusal the
  manager must read — `GatewayRefused::because('…')`. Words, never the gateway's body: the body is in
  the journal.

## Notifications

The gateway posts to `POST /payments/notify/{method}`. Before the satellite sees the request, the
base has written it to the journal raw — the body, the headers, the time. No middleware stands in
front of it: no CSRF, no session, no throttle that could drop a notification unseen.

```php
public function readNotification(Method $method, Credentials $credentials, Request $request, GatewayHttp $http): Notification
{
    $raw = $request->getContent();
    $event = json_decode($raw, true);
    $expected = hash_hmac('sha256', $raw, (string) $credentials->get('secret_key'));

    if (! hash_equals($expected, (string) $request->header('X-Gateway-B-Signature'))) {
        // What the body claims links the event to the attempt; nothing of it is believed.
        throw InvalidNotification::signature(uuid: $event['data']['order_id'] ?? null);
    }

    if (! is_array($event)) {
        throw InvalidNotification::malformed();
    }

    return match ($event['type'] ?? null) {
        'payment.updated' => Notification::status($event['id'], $event['data']['order_id'], $event['data']['id'], $this->status($event['data'])),
        default => Notification::unrelated($event['id'], 'Not about a payment'),
    };
}
```

- `Notification::status()` — signed and about a payment: the base applies it under the attempt's
  lock. The first argument is the event's own key: a repeat with the same key is a duplicate.
- `Notification::poke()` — a type with `trustsNotifications() = false` answers only this, whatever the
  body says: the base asks `fetchStatus()` itself and the status comes from the API.
- `Notification::unrelated()` — a dispute, a token, anything not about a payment's status.
- `InvalidNotification::signature()` / `::malformed()` — rejected; the status does not move.
- `acceptResponse()` and `rejectResponse()` — the answer the gateway expects: `OK`, a signed JSON, an
  empty 200. Most gateways retry on a non-2xx.

If the journal cannot be written, the base answers 503 and changes nothing: the gateway will send
it again.

## What only some gateways can do

| The gateway can                                | The type implements                                                         |
| ---------------------------------------------- | --------------------------------------------------------------------------- |
| close a payment nobody has paid yet            | `CancelsPayments::cancel()` — false when it is too late                     |
| approve first, charge on the shop's confirm    | `CompletesApproval::complete()` — idempotent, the attempt's uuid is the key |
| hold the money and capture it later, or let go | `CapturesPayments`: `capture()`, `void()`, `maxHoldDays()`                  |
| capture less than it held                      | `CapturesPayments::capturesPartially()` — true; false: always the whole     |
| refuse settings that do not go together        | `ValidatesSettings::validateSettings()` — fields and words, empty: saves    |

A hold is a method's setting — the type adds a switch to its `settings()` and starts with
`capture: false` when `$request->hold`. The base then shows «Capture» and «Release the hold» on the
order and asks the type; a capture the gateway refuses leaves the money held and the attempt a
problem.

Some settings are fine alone and wrong together: a hold and instalments, say. The type implements
`ValidatesSettings`, and the base asks it on every save, from the panel and from an agent alike:

```php
public function validateSettings(array $settings): array
{
    return ($settings['hold'] ?? false) && ($settings['instalments'] ?? false)
        ? ['instalments' => 'webx-payment-gateway-b::settings.hold_only_cards']
        : [];
}
```

It gets the settings as they will be stored, what was sent over what is kept, and answers field and
words: nothing is written, and the words appear under the field. To spare the shop the refusal, the
field in `settings()` can also carry `'off_with' => 'hold'` and `'off_help' => '…'`: while that switch
is on, the form turns the field off, empties it and says why. That is only a convenience: the check
is `validateSettings()`.

The journal stars anything that looks like a card number, and a date in milliseconds or a long
payment id can pass that check by chance. If the gateway's answers carry such numbers, the **gateway**
class implements `DeclaresNotCardFields`:

```php
/** Fields that are never a card: the journal leaves their numbers alone. */
public function notCardFields(): array { return ['create_date', 'payment_id']; }
```

Name only the fields you are sure of. The exception covers the value of such a field in a JSON or
form body and nothing else: the same number in another field is still starred, and a key is wiped
from these fields like from any other.

## Every request through `GatewayHttp`

The base passes a `GatewayHttp` to every method of the contract. It is Laravel's `Http` with the
journal's middleware and the timeouts of `webx-payments.http`: each request, each retry, each
timeout is one event with its raw request and answer, and the keys masked in it.

- `$http->request()` — a `PendingRequest`, as `Http::` gives one.
- `$http->kind(ExchangeKind::Status)` — what the event is called in the journal.
- `$http->secret($token, 'access_token')` — a secret made at run time, masked from now on.
- `$http->psr()` — a PSR-18 client for a gateway's SDK, journaled the same way.

A request made with `Http::`, Guzzle or an SDK's own client never reaches the journal — the contract
test counts the requests against the events and fails.

**Keys.** `Credentials` holds the method's keys: those typed in the panel, or
`WEBX_PAYMENTS_<METHOD>_<FIELD>` in the site's environment, which is stronger. It prints as
`[hidden]` and refuses to be serialized. The satellite never reads `env()`.

## The contract test

`WebxUi\Payments\Testing\GatewayContract` is a trait for the satellite's PHPUnit test, on a
Testbench case with `module-payments` and the satellite installed. It runs on the gateway's answers
and notifications **recorded once from its test environment** — never on the live API.

```php
use WebxUi\Payments\Testing\GatewayContract;

final class GatewayContractTest extends TestCase
{
    use GatewayContract;

    protected function gateway(): Gateway { return $this->app->make(Gateways::class)->get('gateway-b'); }

    protected function methodTypes(): array { return [$this->app->make(CardType::class)]; }

    protected function credentials(ChargesOnline $type): Credentials { return Credentials::of(['secret_key' => 'test-key-0001']); }

    /** The gateway's statuses and what they are. */
    protected function statusTable(ChargesOnline $type): array
    {
        return ['new' => Reported::Pending, 'success' => Reported::Paid, 'failure' => Reported::Failed];
    }

    protected function answerStatus(ChargesOnline $type, Payment $payment, string $gatewayStatus): void
    {
        Http::fake(['*/v1/payments/*' => Http::response($this->recorded('status', $gatewayStatus, $payment))]);
    }

    /** `valid`, or spoiled: `signature`, `amount`, `status`; `unrelated`. Null — no such. */
    protected function notification(ChargesOnline $type, Payment $payment, string $variant): ?Request { /* … */ }

    protected function answerError(ChargesOnline $type, Payment $payment): string { /* a 5xx; a piece of its body */ }
}
```

What it checks — what the base trusts a satellite with:

- the keys of the gateway and its types, `<gateway>.<kind>`;
- every request through `GatewayHttp`: one event `out` per request, and not one key in the journal;
- a handoff without a card field, and `start()` with no more than `needs()` asked for;
- a trusted notification believed only signed — a bad signature, a changed amount, a changed status
  are refused; an untrusted one never believed;
- a repeat with the same key, and an event about something else `unrelated`;
- the statuses mapped by the table, an unknown one `Pending`; amounts without a float;
- a hold captured whole and in part, refused above itself, let go (`answerAuthorized()`); an
  approval completed once (`answerApproved()`) — for the types that can;
- an error of the gateway in words, without its body.

The base runs the same trait on its three types of gateway A (`tests/GatewayContractTest.php` of
`module-payments`) — the closest model to copy.

## Before it ships

On the gateway's test environment, each of these, and each in **Payments → Gateway exchange** with
its raw body and the keys masked: a payment, a decline, a cancel on the gateway's page, a
notification before the buyer comes back and after, a hold captured and released (if it can).

## Do not

- Do not call the gateway past `GatewayHttp` — no `Http::`, no Guzzle, no SDK's own client.
- Do not believe the body of a notification the gateway does not sign, or signs without the data.
- Do not put a card field in `Handoff::post()`.
- Do not put keys in `.env.example`, fixtures, logs or the recorded answers — record with test keys,
  and the journal masks them anyway.
- Do not write to `payments` or the order yourself: `Payments::settle()` is the only door.

## Read more

- [Payments](./payments.md) — the module for the shop: methods, the checkout, the journal.
- The payments spec — [`WEBX_UI_MODULE_PAYMENTS.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_PAYMENTS.md),
  §6 the contracts, §14 a satellite, §20 the journal, §21 gateway A and the contract test.
- The review of gateways — [`WEBX_UI_PAYMENT_GATEWAYS.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_PAYMENT_GATEWAYS.md).
