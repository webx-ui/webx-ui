# webx-ui/module-payments

Payments for a WebX UI shop: method types (on receipt, bank transfer), the methods offered by the
amount and the delivery, the checkout's step, the order's block `payment`, «Mark as paid», the
**journal of the exchange with gateways** (raw, keys masked, append-only), the contract of an online
gateway with the fake «gateway A» and its contract test, notifications, returns, holds, the poll and
the problems, the panel section «Payments» with the tab «Payment» of an order, the agent's tools and the letters.
Orders, their milestones and balance are `webx-ui/module-orders`; the cart is `webx-ui/module-cart`.

## What it owns

- **Types** `WebxUi\Payments\MethodTypes` (`register()`, `get()`, `all()`; contract
  `Contracts\MethodType`, key `<provider>.<kind>`; optional `Contracts\ValidatesSettings`: `validateSettings()` refuses a pair of settings on every save, a field's `off_with`, `off_help` take it away in the form): `store.on_receipt` (`Types\OnReceipt`, does not
  await payment, «Pay at the counter» at the shop's point) and `store.bank_transfer`
  (`Types\BankTransfer`, awaits payment; settings `recipient`, `account`, `purpose` with `{number}`,
  `note` by language). The migration adds the methods `on-receipt` (rule `on_hand`) and `bank-transfer` switched off.
- **Methods** `Methods::available(PaymentContext)` — enabled, known type, amount within
  `min_amount`/`max_amount`, currency, `delivery_rule` (`any`, `on_hand`, `methods`, `except` against
  the checkout's `ShippingChoice`), the type's own limits, keys of an online method;
  `explain()` gives the reason of each (`webx-payments::reasons`). Cache group `payments`.
- **Checkout** `Checkout\PaymentsCheckoutSlot` in the slot «payment»: view `webx-payments::step`,
  the choice in `payment_selections` through `GET /api/payments/methods` and
  `PUT /api/payments/selection` (`method`, or `group` and `method`: `Methods::picked()`); scripts `resources/js/step.js` and, for the list `<x-webx-payments::methods>`, `methods.js`. A choice the delivery took
  away is dropped with a word; methods waiting for the delivery say «Choose delivery first».
- **Groups** `MethodGroups` (`webx-payments.groups`, a manager's edits in `payment_groups`, `Models\PaymentGroup`; `Contracts\DeclaresGroup`), `Methods::grouped()`, `Panel\GroupForm`. **Marks** `PaymentMarks` (`Contracts\ProvidesPaymentMarks`, `resources/marks/`; no file — a plate), `SimilarMethods`; the method «only Apple Pay» is hidden by the browser.
- **Order block** `payment` (`PaymentSnapshot`): `method`, `type`, `kind`, `title` by language,
  `instructions` of a transfer. While the order awaits payment its page and «Thank you» show
  `<x-webx-payments::instructions>`; `<x-webx-payments::status :order>` is the line «Paid» / «Awaiting
  payment». `Instructions::of()` / `text()` read the details with the order's number.
- **Mark as paid** `Payments::markPaid(order, source, reference, paidAt, note)`: an attempt `paid` for
  the debt, the milestone «paid», an event `internal/manual` in the journal; `MarkRefused` if paid or
  cancelled. Bound as orders' `MarksPaidByHand` (`Orders\PaymentsMarkPaid`): the card's button is this mark.
- **Tables** `payment_methods` (`WebxUi\Payments\Models\PaymentMethod`, keys in the encrypted
  column `credentials`, hidden from arrays and JSON), `payment_selections` (`PaymentSelection`),
  `payments` (`Payment`, statuses `WebxUi\Payments\PaymentStatus`) and `payment_gateway_events` (`PaymentGatewayEvent`, read-only).
- **Journal** `Exchange\ExchangeJournal`: `record()` writes the raw half of an event (null and a log
  line `webx-payments: journal write failed` if it cannot), `recordOrFail()` throws
  `JournalUnavailable` (a notification gets 503), `decide()` adds the decision once. Its own
  connection (`ExchangeConnection`, `webx-payments-exchange`): a rollback never takes an event.
- **Masking** `ExchangeMasker`: key values in every encoding, `Authorization`, cookies, the gateway's secret
  headers and fields, run-time secrets, Luhn card numbers (except in a body's fields of `Contracts\DeclaresNotCardFields`, a gateway's option; secrets are wiped there too); signatures kept. Bodies over 1 MB are cut.
- **HTTP client of satellites** `WebxUi\Payments\GatewayHttp`: `request()` (Laravel `Http` with the journal's middleware
  and the timeouts of `webx-payments.http`), `kind()`, `secret()`, `psr()` (PSR-18). Every attempt of a request is one event.
- **Gateway contract** (spec §6.3–§6.4, §14.1): `Contracts\Gateway` (keys, settings, secret
  headers and fields, `testConnection()`), registry `WebxUi\Payments\Gateways`; online types
  implement `Contracts\ChargesOnline` (`start()` → `Handoff::redirect|post`, `fetchStatus()` →
  `GatewayStatus` with `Reported` (Pending only: `notStarted` — the buyer never began, «Payment not completed»; `declined` — the bank declined, another try is open), `needs()` → `StartNeeds`, `trustsNotifications()`,
  `readNotification()` → `Notification::status|poke|unrelated` or `InvalidNotification` (a bad signature may claim `uuid`/`gatewayRef`: linked, not believed), the
  answers to the gateway) and, as they can, `CancelsPayments`, `CompletesApproval`,
  `CapturesPayments` (`capturesPartially()`). Errors: `GatewayUnavailable`, `GatewayRefused` —
  words, never a body. Amounts: `GatewayAmount::toDecimal()` / `fromDecimal()`, no float. **Keys** `MethodCredentials::for(Method)`: the column, over it `WEBX_PAYMENTS_<METHOD>_<FIELD>`.
- **Online payment** `Payments::begin(order, method, source)` — an attempt `created` for the debt, earlier `pending` ones
  asked and closed first (`BeginRefused`); `GET /pay/go/{uuid}` (`Online\Handoffs`) calls `start()` once, journals
  `browser/handoff`; the signed page `GET /pay/{number}` (`PaymentLinks::page()`) — «Pay», «we are checking», «Top up»,
  «Pay online now» (`switchMethod()`), also on the page and «Thank you» of an order on receipt (`PayPage::offer()`, the block's `offer`). Checkout: `PaymentsCheckoutSlot::next()` opens the attempt of an online method
  after the order (no network, never throws) → `/pay/go`; `payAgainUrl()` — the page while the order awaits money.
- **Hold** `Payments::capture(payment, ?amount, source, dryRun)` / `void()` (`HoldRefused`, problem `capture_failed`); «Capture» / «Release the hold» on the order's tab with a `dry_run` line first. Milestone «paid» only if the capture covers the order; less — `recordPayment(part: true)`, the rest due. Events `internal/captured` / `released`. **Poll** `webx:payments:poll` every 5 minutes: unfinished attempts (backing off 10, 20, 40… min, `meta.poll`), `expired`, `hold_expiring`. **Problems**: events `PaymentSucceeded`, `PaymentFailed`, `PaymentProblem`; `Payments::resolveProblem()` (`ProblemRefused`). `Payments::cashOnDelivery(OrderData)` — for a waybill.
- **Settling** `Payments::settle(payment, GatewayStatus)` → `SettleResult`, the only door to the attempt and the order; `check()` asks `fetchStatus()` under the lock first. `POST /payments/notify/{method}` (no middleware) journals `in/notify` before parsing (503 if it cannot); `GET|POST /pay/return/{uuid}` asks the API.
- **Gateway A** `Testing\FakeGateway` (`gateway-a`, card: `instalments` a hold rules out; `GatewayANotCards` — with `DeclaresNotCardFields`): `GatewayACard`, `GatewayAForm`, `GatewayAUnsigned`;
  API `FakeGatewayServer` on `gateway-a.test` in the process, page `GET /pay/fake/{uuid}`; only with `WEBX_PAYMENTS_FAKE_GATEWAY=true`, never in production
  (`FakeGatewaySite::methods()` adds its methods). Contract test: trait `Testing\GatewayContract`.
- **Commands** `webx:payments:exchange-prune --days=N [--dry-run] [--force]` — the only removal of events, `N` ≥ 365; `webx:payments:selections-prune` — nightly, choices of carts that are gone; `Doctor\ExchangeSizeCheck` (with module-admin): a note over `exchange.warn_size`; `Doctor\OverriddenViewsCheck`: a theme's copy without `prompt` / `declined`.
- **Panel** (with module-admin; `routes/panel.php`, npm `@webx-ui/module-payments`): «Methods» — keys «set / replace / remove», never sent back, «Test the connection» journaled `out/connection` (`Panel\MethodForm`, screen `payments.method-form`); «Attempts»; «Gateway exchange» — keyset pages, no route writes an event; the tab «Payment» of `orders.show` (node `wx-payments-order`). The panel's lists and the tab are `Panel\AttemptQuery`, `Panel\ExchangeQuery`, `Panel\OrderPaymentCard` — the agent's tools read the same.
- **MCP** (with `webx-ui/mcp`, `Mcp\PaymentsModule`): `payments_methods_list`, `_methods_save`, `_methods_reorder`, `_groups_list`, `_groups_save`, `_groups_reorder`,
  `_available`, `_list`, `_get`, `_check`, `_capture`, `_void`, `_mark_paid`, `_switch_method`, `_pay_link`, `_problems`, `_resolve_problem`,
  `_exchange_list`, `_exchange_get` (read only, bodies ≤ 64 KB); resource `payments://catalog`. Keys never shown nor taken.
- **Letters** (with `webx-ui/module-notifications`, `Notifications\PaymentNotices`): `orders.*` get `payment_url`, `payment_instructions`, `payment_method`; event `payments.problem`, channels — setting `payments.notify.problem` (tab «Payments» of the site's settings), else `webx-payments.notices.problem`.
- **Words** `webx-payments::` `module`, `methods`, `buyer`, `reasons`, `errors`, `exchange`, `panel`, `statuses`, `notifications`.

### Permissions

| Permission          | Opens                                                                                                                         |
| ------------------- | ----------------------------------------------------------------------------------------------------------------------------- |
| `payments.view`     | methods without keys, groups, attempts, the exchange without bodies, the tab «Payment» of an order                            |
| `payments.manage`   | methods: on, order, words, rules, limits, settings, group, the gateway's keys and their test; groups: words, pictogram, order |
| `payments.exchange` | raw headers and bodies of the exchange, «Download»; only the super-admin by default                                           |

Actions on an order (mark paid, check, capture or release a hold, change the method, payment link, problem resolved) — `orders.manage`, in the
panel and as tools; `payments_exchange_get` — `payments.exchange`; the other tools read with `payments.view`, `payments_groups_save` and `_reorder` write with `payments.manage`.

### Classes

The theme's contract, in `resources/theme/payments.css`; restyle with the same class:
`webx-payments` (`is-live`, `is-loading`), `webx-payments__form`, `webx-payments__hint`, `webx-payments__notice`,
`webx-payments__error`, `webx-payments__groups` (`is-flat`, `is-single`), `webx-payments__methods-title`,
`webx-payments__group` (`is-chosen`), `webx-payments__group-head`, `webx-payments__group-methods`, `webx-payments__group-legend`, `webx-payments__option` (`is-chosen`), `webx-payments__radio`, `webx-payments__icon`, `webx-payments__pictogram`, `webx-payments__row-text`,
`webx-payments__row-title`, `webx-payments__row-note`, `webx-payments__marks`, `webx-payments__marks-item`, `webx-payments__mark` (`is-text`), `webx-payments__save`, `webx-payments__nojs`,
`webx-payments-instructions`, `webx-payments-instructions__title`, `webx-payments-instructions__list`,
`webx-payments-instructions__row` (+ `webx-payments-instructions__row--<field>`), `webx-payments-instructions__label`,
`webx-payments-instructions__value`, `webx-payments-status` (+ `webx-payments-status--<state>`), `webx-payments-status__pay`,
`webx-payments-pay`, `webx-payments-pay__title`, `webx-payments-pay__amount`, `webx-payments-pay__message` (+ `--error`),
`webx-payments-pay__form`, `webx-payments-pay__button`, `webx-payments-pay__link`, `webx-payments-handoff`, `webx-payments-prompt`, `webx-payments-prompt__form`, `webx-payments-prompt__button`, `webx-payments-prompt__message`, `webx-payments-others`, `webx-payments-others__toggle`, `webx-payments-others__list`, `webx-payments-others__continue`.

## Change it without forking

| You want to                               | Do this                                                                                    |
| ----------------------------------------- | ------------------------------------------------------------------------------------------ |
| offer a method only with some deliveries  | `delivery_rule` of the method: `{"mode": "methods", "methods": ["store-pickup"]}`          |
| change the step or the bank details       | `vendor:publish --tag=webx-payments-views`, edit `step.blade.php` or `components/*`        |
| have other groups, or none                | `webx-payments.groups` of the site replaces the list whole; empty — a flat list            |
| show an official file of a mark           | `PaymentMarks::register('visa', 'Visa', <path>)` through `callAfterResolving`, an SVG      |
| add a gateway                             | a satellite `payment-<gateway>`: `Gateways::register()`, its types, `GatewayContract` test |
| keep live keys out of the database        | `WEBX_PAYMENTS_<METHOD>_<FIELD>` in the site's environment, stronger than the column       |
| try online payment without a gateway      | `WEBX_PAYMENTS_FAKE_GATEWAY=true` (not in production), then `webx:demo --module=payments`  |
| add a type of your own                    | `callAfterResolving(MethodTypes::class, fn ($t) => $t->register(MyType::class))`           |
| write the journal to another database     | `WEBX_PAYMENTS_EXCHANGE_CONNECTION=<connection>` (a connection of `config/database.php`)   |
| keep more of a body, warn at another size | `webx-payments.exchange.body_limit` (≤ 16 MB), `webx-payments.exchange.warn_size`, bytes   |
| give gateways more time                   | `webx-payments.http.timeout`, `webx-payments.http.connect_timeout` (seconds)               |
| change the words, or the defaults above   | `vendor:publish --tag=webx-payments-lang`, or `--tag=webx-payments-config`                 |
| remove events older than a year           | `php artisan webx:payments:exchange-prune --days=365 --dry-run`, then without `--dry-run`  |

## Do not

- Do not call a gateway with `Http::`, Guzzle or an SDK's own client from a satellite: the request
  never reaches the journal. Take the `GatewayHttp` the base passes, or its `psr()` for an SDK.
- Do not believe the body of a notification of a type with `trustsNotifications() = false`:
  answer `Notification::poke()` and let the base ask `fetchStatus()`.
- Do not put a card field in `Handoff::post()` — it throws: the shop never takes a card.
- Do not update or delete rows of `payment_gateway_events` — the model refuses (`AppendOnly`), and a
  raw query would destroy the evidence of a payment dispute. Prune with the command, older than a year.
- Do not point `WEBX_PAYMENTS_EXCHANGE_CONNECTION` at the default connection on a site: the event
  would roll back with the status change it explains. Leave it empty for the copy.
- Do not put a gateway's keys in `.env.example`, seeders, fixtures, logs or a tool's arguments; they live encrypted in
  `payment_methods.credentials`, set in the panel, and never leave the server.

## Check your work

- `php artisan migrate:status` lists `create_payments_tables`, `create_payment_gateway_events_table`, `create_built_in_payment_methods`.
- Switched on, `bank-transfer` shows on the checkout; «Thank you» shows the details with «Payment for order <number>».
- `php artisan webx:payments:exchange-prune --days=365 --dry-run` counts and removes nothing.
- Gateway A on: «Pay» on `/pay/fake/{uuid}` gives `in/notify` `applied` and `browser/return` `same`.
- After a gateway call, the newest row of `payment_gateway_events` holds the request and answer,
  and `[secret:<field>]` where a key was; `payments_exchange_get` shows the same, `payments_mark_paid` with `dry_run` writes nothing.
- Budgets: `vendor/bin/phpunit packages/module-payments/tests/Panel/PanelPerformanceTest.php` and `tests/Mcp/McpPerformanceTest.php`.

## Read more

- Guides: `apps/docs/guide/payments.md` (the shop, groups and marks), `payment-gateways.md` (a satellite). `README.md` here. Groups: `WEBX_UI_PAYMENT_GROUPS.md`. Spec: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_PAYMENTS.md
  (§5 tables, §6 contracts, §7 methods, §8 online payment, §10 the step, §11.5 letters, §12 built-in methods,
  §14 a satellite, §16 MCP, §20 the journal, §21 gateway A, §23 the plan).
