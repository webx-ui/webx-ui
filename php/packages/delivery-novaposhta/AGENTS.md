# webx-ui/delivery-novaposhta

Nova Poshta, the national carrier of Ukraine, for `webx-ui/module-delivery`. The package adds the
carrier `novaposhta`, a thin client of its JSON API, and three method types. The base owns
everything else: the tables, the sync of directories, the panel's screens, the checkout, waybills,
tracking and MCP. Read the base's `AGENTS.md` first. This package has no tables, routes, screens or
npm package of its own.

## What it owns

- **Carrier** `novaposhta` (`NovaPoshtaCarrier`) in the base's `Carriers`, country `UA`. It has one
  secret, `api_key`, and these settings: `payment_method` (`Cash` / `NonCash`), `cargo_type`
  (`Parcel` / `Cargo` / `Documents`), `weight` (kg), `description`. «Test the connection» reads
  the account's senders (`Counterparty.getCounterparties`). Its mark is
  `resources/icons/novaposhta.svg`, served as a `data:` URL.
- **Types**: `novaposhta.branch` (point, kind `branch`), `novaposhta.locker` (point, kind
  `locker`) and `novaposhta.courier` (address). Each is a `LabelledMethodType`. The price is «by
  the carrier's rates» and the API is never asked; a method given a price of its own is charged
  that price.
- **Migration**: three methods, `novaposhta-branch`, `novaposhta-locker` and `novaposhta-courier`.
  They start switched off, without a price, for `UA` only. A second run adds nothing.
- **Client** `Api\Client::call($key, $model, $method, $properties)`. It posts to the one address
  and reads the answer by `success`, not by the HTTP code. It follows a 303 to a cached file.
  Failures come as the base's `CarrierUnavailable`: `NoAnswer`, `KeyRejected` or `BadResponse`.
  On `success: false` the previous exception is an `Api\ApiError` holding the carrier's words.
- **Read-only guard**: `Api\Methods` lists every reading and every writing method. With
  `read_only` on, a writing method throws `Api\WriteRefused` and no request is made. A method on
  neither list is refused always.
- **Journal** `Api\Journal`: one JSON line per call (time, model, method, page, outcome, ms) in
  `storage/logs/novaposhta-calls.log`. It never holds the key or any data.
- **Numbers as strings**: the client sends every number of `methodProperties` as a string. The
  carrier refuses `"Page": 1` with «Page is invalid format».
- **Recorded answers** in `tests/Fixtures/api/<Model>.<method>.<case>.json`, made by
  `php artisan webx:novaposhta:record` (not in production). It records reading methods only and
  only with `read_only` on. The key comes from `NOVAPOSHTA_KEY` and goes to no file or output.
  `Recording\Scrubber` replaces phones and e-mails everywhere, and every string of the account's
  answers. `FixturesAreScrubbedTest` checks every file.
- **Words**: `webx-delivery-novaposhta::novaposhta.*` in 10 languages. The name is «Nova Poshta»
  in Latin script and Cyrillic on `uk` and `ru`.

### Permissions

None of its own. The carrier's settings, key and «Test the connection» are behind the base's
`delivery.manage`.

## Change it without forking

| You want                                  | Do this                                                            |
| ----------------------------------------- | ------------------------------------------------------------------ |
| Never write to the account behind the key | `NOVAPOSHTA_READ_ONLY=true`                                        |
| A longer wait for the API                 | `NOVAPOSHTA_TIMEOUT` (seconds, 10 by default)                      |
| The journal elsewhere, or none            | `NOVAPOSHTA_JOURNAL` (a path; empty turns it off)                  |
| Another address (a proxy, a test server)  | `NOVAPOSHTA_API_URL`                                               |
| Other words                               | `php artisan vendor:publish --tag=webx-delivery-novaposhta-lang`   |
| The config in the site                    | `php artisan vendor:publish --tag=webx-delivery-novaposhta-config` |
| A method with a price the shop charges    | give the method a price in «Delivery → Methods»; the type keeps it |

## Do not

- Do not put the API key in config, a fixture, a log or a test. It lives in
  `delivery_carriers.credentials`, which the base encrypts. The only exception is `NOVAPOSHTA_KEY`
  in the `.env` of a development copy, for the recording command. Tests use `test-key` on
  `Http::fake()`.
- Do not record a writing method or tracking of numbers nobody gave you. Do not commit an answer
  of the account that skipped `Recording\Scrubber`.
- Do not call the API with `Http::` directly. Go through `Api\Client`, which keeps the read-only
  guard, the journal, and the key out of logs.
- Do not add a method to `Api\Client` calls without listing it in `Api\Methods::READ` or
  `WRITE`. A method that changes anything at the carrier belongs in `WRITE`.
- Do not run with a key of somebody else's account without `NOVAPOSHTA_READ_ONLY=true`. A
  development copy with a client's key must never create a waybill, a recipient or an address.
- Do not log a request or an answer body. They carry the key, names and phones.
- Do not switch the methods on before the carrier is set up and its directories are synced.

## Check your work

- `vendor/bin/phpunit packages/delivery-novaposhta/tests` from `php/` of the monorepo.
- «Delivery → Carriers» in the panel shows Nova Poshta with its key, settings and «Test the
  connection». «Delivery → Methods» shows the three methods, switched off.
- The journal lists every call. With read only on it has no writing method with `ok`.

## Read more

- The satellite's spec: `docs/architecture/WEBX_UI_DELIVERY_NOVAPOSHTA.md` in the monorepo.
- The base: `webx-ui/module-delivery`, its `AGENTS.md` and the guide «Delivery carriers»
  (`apps/docs/guide/delivery-carriers.md`).
