# webx-ui/module-payments

Payments for a WebX UI shop. This release holds the base the rest is built on: the tables of
methods, the buyer's choice and attempts, and the **journal of the exchange with gateways** — every
request a gateway satellite sends and every answer and notification it gets, raw bodies as they
went, the shop's keys masked, append-only. Payment methods, the checkout step, the pay page and the
panel section come in later releases of this package. Orders, their milestones and balance are
`webx-ui/module-orders`; the cart is `webx-ui/module-cart` — read their guides for those.

## What it owns

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
- **Command** `php artisan webx:payments:exchange-prune --days=N [--dry-run] [--force]` — the only
  removal of events; `N` at least 365, smaller is refused.
- **Doctor** `WebxUi\Payments\Doctor\ExchangeSizeCheck` (with `webx-ui/module-admin`): a note when
  the journal is bigger than `webx-payments.exchange.warn_size`.
- **Words** `webx-payments::module.*`, `webx-payments::exchange.*` (directions, kinds, signatures,
  outcomes) in ten languages.

## Change it without forking

| You want to                           | Do this                                                                                   |
| ------------------------------------- | ----------------------------------------------------------------------------------------- |
| write the journal to another database | `WEBX_PAYMENTS_EXCHANGE_CONNECTION=<connection>` (a connection of `config/database.php`)  |
| keep more or less of a body           | `webx-payments.exchange.body_limit` in bytes, up to 16 MB                                 |
| hear from the doctor at another size  | `webx-payments.exchange.warn_size` in bytes                                               |
| give gateways more time               | `webx-payments.http.timeout`, `webx-payments.http.connect_timeout` (seconds)              |
| change the words                      | `php artisan vendor:publish --tag=webx-payments-lang`, edit `lang/vendor/webx-payments`   |
| change the defaults above in one file | `php artisan vendor:publish --tag=webx-payments-config`                                   |
| remove events older than a year       | `php artisan webx:payments:exchange-prune --days=365 --dry-run`, then without `--dry-run` |

## Do not

- Do not call a gateway with `Http::`, Guzzle or an SDK's own client from a satellite: the request
  never reaches the journal. Take the `GatewayHttp` the base passes, or its `psr()` for an SDK.
- Do not update or delete rows of `payment_gateway_events` — the model refuses (`AppendOnly`), and a
  raw query would destroy the evidence of a payment dispute. Prune with the command, older than a
  year.
- Do not point `WEBX_PAYMENTS_EXCHANGE_CONNECTION` at the default connection on a site: the event
  would roll back with the status change it explains. Leave it empty for the copy.
- Do not put a gateway's keys in `.env.example`, seeders, fixtures or logs; they live encrypted in
  `payment_methods.credentials` and never leave the server.

## Check your work

- `php artisan migrate:status` lists `create_payments_tables` and
  `create_payment_gateway_events_table`.
- `php artisan webx:payments:exchange-prune --days=365 --dry-run` counts and removes nothing.
- `php artisan webx:doctor` — no note about the journal until it outgrows `warn_size`.
- After a gateway call, the newest row of `payment_gateway_events` holds the request and answer,
  and `[secret:<field>]` where a key was.

## Read more

- `README.md` next to this file.
- Spec: https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_PAYMENTS.md
  (§5 tables, §20 the exchange journal, §23 the plan of releases).
