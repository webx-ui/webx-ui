# webx-ui/delivery-novaposhta

Nova Poshta, the national carrier of Ukraine, for `webx-ui/module-delivery`. The package adds the
carrier `novaposhta`, a thin client of its JSON API, and three method types. The base owns
everything else: the tables, the sync of directories, the panel's screens, the checkout, waybills,
tracking and MCP. Read the base's `AGENTS.md` first. This package has no tables, routes, screens or
npm package of its own.

## What it owns

- **Carrier** `novaposhta` (`NovaPoshtaCarrier`) in the base's `Carriers`, country `UA`. It has one
  secret, `api_key`, and these settings: `sender` → `contact` from the account, `sender_city` →
  `sender_point` (a branch only) from the base's directories — the base's `ProvidesSettingOptions`
  (`Sending\SenderOptions`; reading methods only, a `Ref` typed as the query answers that one) —
  then `payment_method` (`Cash` / `NonCash`), `cargo_type` (`Parcel` / `Cargo` / `Documents`),
  `weight` (kg), `description` and a locker's seat `locker_width` / `_height` / `_length` (cm, up
  to 40 × 30 × 60). «Test the connection» reads the account's senders
  (`Counterparty.getCounterparties`). Its mark is `resources/icons/novaposhta.svg`, a `data:` URL.
- **Types**: `novaposhta.branch` (point, kind `branch`), `novaposhta.locker` (point, kind
  `locker`) and `novaposhta.courier` (address). Each is a `LabelledMethodType`. The price is «by
  the carrier's rates» and the API is never asked; a method given a price of its own is charged
  that price.
- **Waybill** (`CreatesWaybills`, `DescribesWaybill`, `Waybills\Waybills`): the dialog's fields are
  `weight`, `seats` (not a locker), `cargo_type` (a locker: `Parcel` / `Documents`), the locker's
  seat, `description`, `date` (empty — today, up to three months ahead); `supportsCod()` is false,
  `label()` is null. Checked before any request: names in Ukrainian letters
  (`Waybills\RecipientName`), a phone `380…` (`Waybills\Phone`), the sender's four settings, the
  carrier's town, the point or the street — each refusal names its field (`WaybillRefused::field()`).
  Then the contact's phone (read), `Counterparty.save` (a recipient, `ContactPerson.save` if no
  contact came), `Address.save` for a courier, `InternetDocument.save` — `WarehouseWarehouse` or
  `WarehouseDoors`, `PayerType` from the snapshot. `cancelWaybill()` is `InternetDocument.delete`
  by `meta.ref`. The carrier's refusal: its text and codes, frequent ones in the dictionary's words
  (`Waybills\CarrierErrors`). A read-only key refuses before a single request.
- **Tracking** (`TracksShipments`, `Tracking\Tracking`): one `getStatusDocuments` per batch of the
  base (up to 100 numbers), codes by `Tracking::STATUSES` (§9.2 of the spec). Code 3 and a number
  left out of the answer are «ask later»; a new code keeps the status, logs a line and puts the
  carrier's words in the events; `returned` is never set. The base stops asking at a final status
  and marks «received» once. Off on a read-only key unless `NOVAPOSHTA_TRACKING=true`.
- **Directories** (`SyncsDirectories`), nightly, pages of 500, paced by `Api\Pace`: towns from
  `getCities` (`Directories\Localities`; the carrier leads `UA`), then the whole network of
  points from `getWarehouses` (`Directories\Points`). `Branch` and `Store` are `branch`,
  `Postomat` is `locker`. `DropOff`, `Fulfillment` and `DenyToSelect=1` are left out and
  counted in the run's warnings, said in words on the carriers screen (`NamesSkipReasons`,
  `remarks.*` of the dictionary). A point's name carries its street, so lists put no address
  under it (`NamesPointLines`: none after a colon, else the town). A locker `ForResidentsOfEntrance` is kept with the base's
  restriction `residents`: said beside it, listed after the others. The base fills a town's
  missing coordinates from its points.
- **Streets**, weekly (`30 0 * * 0`), pages of 2 000: `getStreet` with an empty search for every
  town with a courier the base keeps (`CarrierLocalities::externalIds`, 200 at a time), page by
  page of 500 (`Directories\Streets`). The cursor is `<town>:<pages>`, `0` once a town is done.
  A pull stops at 2 000 streets, `max_requests_per_pull` requests or `pull_seconds`. Types the
  base has no kind for are `other` with the carrier's word in brackets: «Шевченка (бульвар)».
  A read-only key walks a sample of 20 towns and runs the streets by hand only.
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

| You want                                   | Do this                                                            |
| ------------------------------------------ | ------------------------------------------------------------------ |
| Never write to the account behind the key  | `NOVAPOSHTA_READ_ONLY=true`                                        |
| Track parcels on a read-only key, or never | `NOVAPOSHTA_TRACKING` (`true` / `false`)                           |
| A longer wait for the API                  | `NOVAPOSHTA_TIMEOUT` (seconds, 10 by default)                      |
| The journal elsewhere, or none             | `NOVAPOSHTA_JOURNAL` (a path; empty turns it off)                  |
| Another address (a proxy, a test server)   | `NOVAPOSHTA_API_URL`                                               |
| Streets on another day, or by hand only    | `NOVAPOSHTA_STREETS_CRON` (a cron or `manual`)                     |
| The streets of only the first N towns      | `NOVAPOSHTA_STREETS_SAMPLE` (`0` — every town)                     |
| A smaller or larger pull of streets        | `NOVAPOSHTA_MAX_REQUESTS_PER_PULL`, `NOVAPOSHTA_PULL_SECONDS`      |
| Other words                                | `php artisan vendor:publish --tag=webx-delivery-novaposhta-lang`   |
| The config in the site                     | `php artisan vendor:publish --tag=webx-delivery-novaposhta-config` |
| A method with a price the shop charges     | give the method a price in «Delivery → Methods»; the type keeps it |

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
- Do not run the full pass of the streets on a key of somebody else's account: ~10 500
  requests. Read-only keeps it to a sample; do not lift it there.
- Do not test a waybill on a real key. The writing answers are `*.documented-*.json`, written
  after the documentation with made-up data; keep them so.

## Check your work

- `vendor/bin/phpunit packages/delivery-novaposhta/tests` from `php/` of the monorepo.
- «Delivery → Carriers» in the panel shows Nova Poshta with its key, settings and «Test the
  connection». «Delivery → Methods» shows the three methods, switched off.
- The journal lists every call. With read only on it has no writing method with `ok`.
- `TrackingTest` follows the table of §9.2 and the base's milestone; the journal of a client's
  key has no `getStatusDocuments`.
- The playground's `/panel/orders/10` and `/11` make a waybill of a branch and a locker on fixtures.

## Read more

- The satellite's spec: `docs/architecture/WEBX_UI_DELIVERY_NOVAPOSHTA.md` in the monorepo.
- The base: `webx-ui/module-delivery`, its `AGENTS.md` and the guide «Delivery carriers»
  (`apps/docs/guide/delivery-carriers.md`).
