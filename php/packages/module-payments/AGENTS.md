# webx-ui/module-payments

Payments for a WebX UI shop: the registry of method types with the shop's own two — payment on
receipt and bank transfer — the methods offered by the amount and the chosen delivery, the
checkout's step «payment», the order's block `payment` with the bank details, a manager's «Mark as
paid», and the **journal of the exchange with gateways** — raw bodies as they went, the shop's keys
masked, append-only — and the contract of an online gateway with the fake «gateway A» and the
contract test a satellite runs, the gateway's notifications and the buyer's return. The hold
actions, the poll and the panel section come later.
Orders, their milestones and balance are `webx-ui/module-orders`; the cart is `webx-ui/module-cart`.

## What it owns

- **Types** `WebxUi\Payments\MethodTypes` (`register()`, `get()`, `all()`; contract
  `Contracts\MethodType`, key `<provider>.<kind>`): `store.on_receipt` (`Types\OnReceipt`, does not
  await payment, «Pay at the counter» at the shop's point) and `store.bank_transfer`
  (`Types\BankTransfer`, awaits payment; settings `recipient`, `account`, `purpose` with `{number}`,
  `note` by language). The migration adds the methods `on-receipt` (rule `on_hand`) and
  `bank-transfer` switched off.
- **Methods** `Methods::available(PaymentContext)` — enabled, known type, amount within
  `min_amount`/`max_amount`, currency, `delivery_rule` (`any`, `on_hand`, `methods`, `except` against
  the checkout's `ShippingChoice`), the type's own limits, keys of an online method;
  `explain()` gives the reason of each (`webx-payments::reasons`). Cache group `payments`.
- **Checkout** `Checkout\PaymentsCheckoutSlot` in the slot «payment»: view `webx-payments::step`,
  the choice in `payment_selections` through `GET /api/payments/methods` and
  `PUT /api/payments/selection` (`method`); script `resources/js/step.js`. A choice the delivery took
  away is dropped with a word; methods waiting for the delivery say «Choose delivery first».
- **Order block** `payment` (`PaymentSnapshot`): `method`, `type`, `kind`, `title` by language,
  `instructions` of a transfer. While the order awaits payment its page and «Thank you» show
  `<x-webx-payments::instructions>`; `<x-webx-payments::status :order>` is the line «Paid» / «Awaiting
  payment». `Instructions::of()` / `text()` read the details with the order's number.
- **Mark as paid** `Payments::markPaid(order, source, reference, paidAt, note)`: an attempt `paid` for
  the debt, the milestone «paid», an event `internal/manual` in the journal; `MarkRefused` if paid or
  cancelled.
- **Tables** `payment_methods` (`WebxUi\Payments\Models\PaymentMethod`, keys in the encrypted
  column `credentials`, hidden from arrays and JSON), `payment_selections` (`PaymentSelection`),
  `payments` (`Payment`, statuses `WebxUi\Payments\PaymentStatus`) and `payment_gateway_events`
  (`PaymentGatewayEvent`, read-only).
- **Journal** `WebxUi\Payments\Exchange\ExchangeJournal`: `record()` writes the raw half of an
  event (null if the write failed — logged as `webx-payments: journal write failed`),
  `recordOrFail()` throws `JournalUnavailable` (a notification is then answered 503), `decide()`
  adds the decision once. It writes through its own connection (`ExchangeConnection`, a copy of the
  default one named `webx-payments-exchange`), so a rolled-back transaction never takes an event.
- **Masking** `ExchangeMasker`: the values of `WebxUi\Payments\Credentials` in every encoding (as
  is, url-encoded, JSON-escaped, inside base64), the headers `Authorization`, `Proxy-Authorization`,
  `Cookie`, `Set-Cookie` and those a gateway declares, secret fields of a body and run-time secrets
  (`ExchangeSecrets`), card numbers passing Luhn. Signatures are kept. Bodies above
  `webx-payments.exchange.body_limit` (1 MB) are cut; size and sha256 stay whole.
- **HTTP client of satellites** `WebxUi\Payments\GatewayHttp`: `request()` (Laravel `Http` with
  the journal's middleware and the timeouts of `webx-payments.http`), `kind()`, `secret()`,
  `psr()` (PSR-18 for an SDK). Every attempt of a request is one event.
- **Gateway contract** (spec §6.3–§6.4, §14.1): `Contracts\Gateway` (keys, settings, secret
  headers and fields, `testConnection()`), registry `WebxUi\Payments\Gateways`; online types
  implement `Contracts\ChargesOnline` (`start()` → `Handoff::redirect|post`, `fetchStatus()` →
  `GatewayStatus` with `Reported`, `needs()` → `StartNeeds`, `trustsNotifications()`,
  `readNotification()` → `Notification::status|poke|unrelated` or `InvalidNotification`, the
  answers to the gateway) and, as they can, `CancelsPayments`, `CompletesApproval`,
  `CapturesPayments` (`capturesPartially()`). Errors: `GatewayUnavailable`, `GatewayRefused` —
  words, never a body. Amounts: `GatewayAmount::toDecimal()` / `fromDecimal()`, no float.
- **Keys** `MethodCredentials::for(Method)`: the encrypted column, and over it the environment
  `WEBX_PAYMENTS_<METHOD>_<FIELD>` (`MethodCredentials::variable()`).
- **Online payment** `Payments::begin(order, method, source)` — an attempt `created` for
  `balance()->due`, no network (`BeginRefused`); `GET /pay/go/{uuid}` (`Online\Handoffs`) calls
  `start()` once under the attempt's lock, journals `browser/handoff`, redirects or posts the form
  (`webx-payments::pay.handoff`); a failure makes the attempt `failed`. The page of payment
  `GET /pay/{number}` (signed, `Online\PaymentLinks::page()`, view `webx-payments::pay.page`) with
  «Pay» (`POST /pay/{number}/attempts`), «we are checking» for two minutes, the reason of a failure.
- **Settling** `Payments::settle(payment, GatewayStatus)` → `SettleResult` — the only door from the
  gateway to the attempt and the order (§9.1); `Payments::check()` asks `fetchStatus()` under the
  lock `webx-payments:{uuid}` first. `POST /payments/notify/{method}` (`Online\Notifications`, no
  middleware at all) journals `in/notify` before parsing (503 if it cannot), then `rejected`,
  `unmatched`, `ignored`, `duplicate`, `applied`/`same` or `error` (500). The return
  `GET|POST /pay/return/{uuid}` (`Online\Returns`) journals `browser/return`, asks the API, not the URL.
- **Gateway A** `WebxUi\Payments\Testing\FakeGateway` (`gateway-a`) with `GatewayACard`,
  `GatewayAForm`, `GatewayAUnsigned`; its API `FakeGatewayServer` answers `gateway-a.test` inside
  the process (`Exchange\InProcessHosts`), its page is `GET /pay/fake/{uuid}`. Only with
  `WEBX_PAYMENTS_FAKE_GATEWAY=true`, never in production (`FakeGatewaySite`; `::methods()` adds
  its three methods with the test key). Contract test: trait `Testing\GatewayContract`.
- **Command** `php artisan webx:payments:exchange-prune --days=N [--dry-run] [--force]` — the only
  removal of events; `N` at least 365, smaller is refused.
- **Command** `webx:payments:selections-prune` — nightly: the choices of carts that are gone.
- **Doctor** `WebxUi\Payments\Doctor\ExchangeSizeCheck` (with `webx-ui/module-admin`): a note when
  the journal is bigger than `webx-payments.exchange.warn_size`.
- **Words** `webx-payments::` `module`, `methods`, `buyer`, `reasons`, `errors`, `exchange` in ten languages.

### Classes

The theme's contract, in `resources/theme/payments.css`; restyle with the same class:
`webx-payments` (`is-live`, `is-loading`), `webx-payments__form`, `webx-payments__hint`, `webx-payments__notice`,
`webx-payments__error`, `webx-payments__methods` (`is-single`), `webx-payments__methods-title`,
`webx-payments__method` (`is-chosen`), `webx-payments__radio`, `webx-payments__icon`, `webx-payments__method-text`,
`webx-payments__method-title`, `webx-payments__method-note`, `webx-payments__save`, `webx-payments__nojs`,
`webx-payments-instructions`, `webx-payments-instructions__title`, `webx-payments-instructions__list`,
`webx-payments-instructions__row` (+ `webx-payments-instructions__row--<field>`), `webx-payments-instructions__label`,
`webx-payments-instructions__value`, `webx-payments-status` (+ `webx-payments-status--<state>`), `webx-payments-status__pay`,
`webx-payments-pay`, `webx-payments-pay__title`, `webx-payments-pay__amount`, `webx-payments-pay__message` (+ `--error`),
`webx-payments-pay__form`, `webx-payments-pay__button`, `webx-payments-pay__link`, `webx-payments-handoff`.

## Change it without forking

| You want to                              | Do this                                                                                    |
| ---------------------------------------- | ------------------------------------------------------------------------------------------ |
| offer a method only with some deliveries | `delivery_rule` of the method: `{"mode": "methods", "methods": ["store-pickup"]}`          |
| change the step or the bank details      | `vendor:publish --tag=webx-payments-views`, edit `step.blade.php` or `components/*`        |
| add a gateway                            | a satellite `payment-<gateway>`: `Gateways::register()`, its types, `GatewayContract` test |
| keep live keys out of the database       | `WEBX_PAYMENTS_<METHOD>_<FIELD>` in the site's environment, stronger than the column       |
| try online payment without a gateway     | `WEBX_PAYMENTS_FAKE_GATEWAY=true` (not in production), `FakeGatewaySite::methods()`        |
| add a type of your own                   | `callAfterResolving(MethodTypes::class, fn ($t) => $t->register(MyType::class))`           |
| write the journal to another database    | `WEBX_PAYMENTS_EXCHANGE_CONNECTION=<connection>` (a connection of `config/database.php`)   |
| keep more or less of a body              | `webx-payments.exchange.body_limit` in bytes, up to 16 MB                                  |
| hear from the doctor at another size     | `webx-payments.exchange.warn_size` in bytes                                                |
| give gateways more time                  | `webx-payments.http.timeout`, `webx-payments.http.connect_timeout` (seconds)               |
| change the words                         | `php artisan vendor:publish --tag=webx-payments-lang`, edit `lang/vendor/webx-payments`    |
| change the defaults above in one file    | `php artisan vendor:publish --tag=webx-payments-config`                                    |
| remove events older than a year          | `php artisan webx:payments:exchange-prune --days=365 --dry-run`, then without `--dry-run`  |

## Do not

- Do not call a gateway with `Http::`, Guzzle or an SDK's own client from a satellite: the request
  never reaches the journal. Take the `GatewayHttp` the base passes, or its `psr()` for an SDK.
- Do not believe the body of a notification of a type with `trustsNotifications() = false`:
  answer `Notification::poke()` and let the base ask `fetchStatus()`.
- Do not put a card field in `Handoff::post()` — it throws: the shop never takes a card.
- Do not update or delete rows of `payment_gateway_events` — the model refuses (`AppendOnly`), and a
  raw query would destroy the evidence of a payment dispute. Prune with the command, older than a
  year.
- Do not point `WEBX_PAYMENTS_EXCHANGE_CONNECTION` at the default connection on a site: the event
  would roll back with the status change it explains. Leave it empty for the copy.
- Do not put a gateway's keys in `.env.example`, seeders, fixtures or logs; they live encrypted in
  `payment_methods.credentials` and never leave the server.

## Check your work

- `php artisan migrate:status` lists `create_payments_tables`, `create_payment_gateway_events_table`
  and `create_built_in_payment_methods`.
- Switched on, `bank-transfer` shows on the checkout; after an order, «Thank you» shows the details
  with «Payment for order <number>».
- `php artisan webx:payments:exchange-prune --days=365 --dry-run` counts and removes nothing.
- `php artisan webx:doctor` — no note about the journal until it outgrows `warn_size`.
- Gateway A on: «Pay» on `/pay/fake/{uuid}` gives `in/notify` `applied` and `browser/return` `same`.
- After a gateway call, the newest row of `payment_gateway_events` holds the request and answer,
  and `[secret:<field>]` where a key was.

## Read more

- `README.md` next to this file.
- Spec: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_PAYMENTS.md
  (§5 tables, §6 contracts, §7 methods, §8 online payment, §10 the step, §12 built-in methods,
  §14 a gateway's satellite, §20 the journal, §21 gateway A, §23 the plan).
