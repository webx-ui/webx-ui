# webx-ui/module-delivery

Delivery for a shop on WebX UI: which methods a buyer is offered in the town they picked, at what
price, and what each method asks for — a point or an address. The shop's own pick-ups and courier
are built in; a carrier is a satellite `delivery-<carrier>` registering its carrier and types.
Requires `webx-ui/module-cart` (the counted cart), `webx-ui/geography` (every locality, point and
binding) and `webx-ui/widgets`. `webx-ui/module-orders` gets the step as its checkout slot
«delivery» and the order its block and parcels; orders work without it. Words — `webx-delivery`.

## What it owns

- **Tables** (migrations `2026_01_02_*`, after the geography): `delivery_methods` (`key`, `type`,
  `titles`, `descriptions`, `icon_id`, `point_kind`, `countries`, `price` in minor units — null:
  the type prices it, `honours_paid_by_store`, `settings`, `enabled`, `position`);
  `delivery_method_localities` (where the shop's courier goes); `delivery_carriers` (`store` from
  the migration; `credentials` — `encrypted:array`, hidden); `delivery_carrier_localities` (a
  carrier's locality bound or queued: `status`, `method`, `candidates`, `features`, `streets`);
  `delivery_points` (every carrier's and the shop's, `carrier = store`, with the normalised
  `search`); `delivery_streets` (carriers' streets by binding, sync only); `delivery_sync_runs`
  (the journal, last 100 a directory); `delivery_popular_localities` (the dialog's towns, one list
  a site); `delivery_selections` (the buyer's choice of a cart, `cart_id` PK cascade — written only
  by `PUT /api/delivery/selection`); `delivery_shipments` (parcels of orders; FK only where `orders` is).
- **Contracts** `WebxUi\Delivery\Contracts\MethodType` (key `<carrier>.<kind>`, `asks()`,
  `pointKinds()`, `countries()`, `available()`, `quote()`, `validate()`), `Carrier`, optional
  `LabelledMethodType` (`title()`, `description()`; without it the panel names a type from its kind
  or `asks()` and the carrier, never by key); `AbstractMethodType` — the price and default checks. Optional `ProvidesSettingOptions` (a settings field `options: 'carrier'`, `depends`, `search` — `Settings\SettingOptions` asks, caches 600 s, checks on save, keeps `settings._labels`), `DescribesWaybill` (`waybillFields()` → `WaybillRequest::$extra`, `supportsCod()`), `NamesSkipReasons` (`skipReason()` — the words of its `Page::$skipped` reasons on the carriers screen; else «Skipped, for another reason: N») and `NamesPointLines` (`pointLine()` — the line under a point title in lists, `Points` row `line`; else the address).
- **Registries** (singletons): `Carriers` (`store` built in), `MethodTypes` (`store.pickup`, `store.courier` built in). A repeated or malformed key, a type of an unknown carrier — an exception.
- **`Methods::forLocality(DeliveryContext)`** — the offered `MethodOption`s in the panel's order
  (price, points, `disabled` + `hint` for a carrier's courier without streets); `evaluate()` — every
  enabled method with why it is not offered (`MethodOption::NO_POINTS`, …).
- **Money** — `Quote` (`fixed`, `free($was)`, `carrierRates`, `unavailable`); `Quotes` caches a
  satellite's price 600 s, `Purpose::Order` — no cache, no fallback (`QuoteFailed`).
  `DeliveryAdjustments` (priority 1000) puts `fixed(N > 0)` into the chain; `Delivery::cost()` — the
  line «Delivery cost». `shipping.paid_by_store` is in the cart's `BenefitKeys`.
- **`Delivery`** — `needed(CartState)`, `context($cart, $locality)`, `snapshot(Selection, Quote)` → `DeliverySnapshot` (§11.1: the order's block, every language copied, `payer` sender|recipient).
- **`PopularLocalities`** — `ids()`, `suggestions($country, $locale)`, `set($ids)`, `add()`, `remove()`. **`CarrierLocalities`** — a satellite reads its towns: `externalIds()`, `externalId()`, `localityId()`, `forLocalities()`; **`Points`** — `refOf()`, `externalIds()` by the carrier's ids.
- **Parcels** (`Shipping\Shipments`, only with `module-orders`): a type implements
  `Contracts\CreatesWaybills` / `TracksShipments`; `request()` (first and last name apart, `carrierLocality`), `create()` (`WaybillRefused::field()` marks a field of the dialog; number into the block,
  `Events\ShipmentCreated`), `cancel()`, `track()` — «received» → the order's milestone, once.
- **Directory sync** in `WebxUi\Delivery\Sync`: a carrier implements `Contracts\SyncsDirectories`
  (`directories()`, `pull($directory, $cursor)` → a `Page` of locality, point or street records);
  `Synchronizer` writes a page in one transaction, matches localities with the geography's
  `Matcher`, removes what a complete pass did not see — or holds the run over
  `webx-delivery.sync.max_removal_share` (10 %) until `confirm()`. `SyncDirectory` — the queued job;
  `ManualSync` — «Run». **`Streets`** — `forCarrier()`, `forStore()`, `find()`, `count()`: the site's tables only.
- **The step** (§12): `<x-webx-delivery::step />` (class `View\Components\Step`; without props —
  the visitor's cart) built of `country-picker`, `locality-picker` (the card «Town D, Region B ·
  Change» and the dialog «Where to deliver the order?»), `methods`, `point-picker`
  (`<x-webx-select searchable>`), `address-form`, `summary-line` — views under
  `resources/views/components`, the data from `Checkout\StepView::build()`. `dist/delivery.js`
  (served at `webx/delivery/delivery.js`) writes each change and redraws the step; without
  JavaScript the step is one form. It is the runtime widget `delivery`: a step an owner redraws starts on `webx.mount`. Events: `webx:delivery:changed` on the step, `data-checkout-event` on `document`.
- **API** `api/delivery/…` (`webx-delivery.path`): `countries`, `localities?country&q`,
  `localities/popular`, `points?method&locality&q&page` (20 a page), `points/{id}`,
  `streets?method&locality&q` — public, `max-age=300`, 60 a minute per IP; `methods?locality`,
  `GET|PUT selection` — the visitor's cart (404 without one), `web` group, CSRF. `Selections::put()`
  checks and writes; a refusal (422) writes nothing.
- **Checkout slot** `Checkout\DeliveryCheckoutSlot` (in `CheckoutSlots` with `module-orders`): `needed`, `render`,
  `summary`, `check` (`delivery.locality|method|point|address|quote`), `snapshot`, `totalsLine`, `choice`.
- **Commands** `webx:delivery:sync {carrier?} {--directory=*} {--trigger=} {--queue}` (by cron), `webx:delivery:track` (every 30 min); `webx:doctor` — `Doctor\SyncCheck`.
- **Testing**: `FakeCarrier` — «Carrier A», a directory in `XX`; `FakeParcels` — its waybills; `CarrierContract` — a satellite's
  contract test; `FakeCarrierSite` — carrier A on a dev site. **Demo** (`delivery`): `XX`, towns by hand, points, couriers, popular towns. **Cache groups** `delivery`, `delivery-points`.
- **Panel** (§14, with `module-admin`): six sections of the group `delivery` (`Panel\DeliverySection`),
  JSON under `api/cms/delivery/…` (`routes/panel.php`): methods (order by drag), own points, carriers
  (directories, «Run», «Confirm removal», keys — only «set»), geography by hand (through
  `WebxUi\Geography\Editor`; a duplicate answers 409 with `similar`), popular towns, binding queue
  (`Panel\BindingQueue`). Screens `delivery.method-form`, `.point-form`, `.locality-form`; field
  types `wx-delivery-locality`, `-localities`, `-region`, `-country`, `-schedule`, `-point-kind`; the
  tab «Delivery» of `orders.show` (`routes/orders.php`; «Change delivery» — `Shipping\OrderDeliveryChange`, behind
  `orders.manage`, not while a waybill stands). The npm half — `@webx-ui/module-delivery`.
- **MCP** (§15, with `webx-ui/mcp`; `Mcp\DeliveryModule` is the first section): `delivery_*` — methods,
  `quote` (each method priced, or why not), points, geography by hand (a duplicate answers
  `similar`), popular towns, bindings, carriers (`carriers_options` — `delivery.manage`), sync, parcels (`delivery.ship`, with orders), `order_change` (`orders.manage`) behind
  `delivery:read|write`, writes with `dry_run`; `delivery://catalog`. A key never passes: `api_key: set`.

### Permissions

| Permission        | Opens                                                                                                 |
| ----------------- | ----------------------------------------------------------------------------------------------------- |
| `delivery.view`   | every screen of the section, read-only, and the tab «Delivery» of an order                            |
| `delivery.manage` | the writes: methods and their order, points, carriers and keys, sync, geography, popular towns, queue |
| `delivery.ship`   | create and cancel a waybill, refresh the tracking — without the settings                              |

### Classes

The theme's contract, in `resources/theme/delivery.css`; restyle with the same class:
`webx-delivery` (`is-live`, `is-loading`), `webx-delivery__form`, `webx-delivery__nojs`, `webx-delivery__label`,
`webx-delivery__control`, `webx-delivery__error`, `webx-delivery__country`, `webx-delivery__locality`,
`webx-delivery__card` (`is-empty`), `webx-delivery__card-label`, `webx-delivery__card-value`, `webx-delivery__change`,
`webx-delivery__locality-form`, `webx-delivery__show`, `webx-delivery__save`, `webx-delivery__methods`,
`webx-delivery__methods-title`, `webx-delivery__method` (`is-chosen`, `is-disabled`), `webx-delivery__method-label`,
`webx-delivery__radio`, `webx-delivery__icon`, `webx-delivery__method-text`, `webx-delivery__method-title`,
`webx-delivery__method-description`, `webx-delivery__method-hint`, `webx-delivery__price` (+ `webx-delivery__price--<kind>`),
`webx-delivery__price-was`, `webx-delivery__price-estimate`, `webx-delivery__method-body`, `webx-delivery__empty`,
`webx-delivery__point`, `webx-delivery__point-card`, `webx-delivery__point-title`, `webx-delivery__point-address`,
`webx-delivery__point-today`, `webx-delivery__point-restricted`, `webx-delivery__point-phone`, `webx-delivery__address`, `webx-delivery__field`
(+ `webx-delivery__field--<name>`), `webx-delivery__summary`, `webx-delivery__summary-label`,
`webx-delivery__summary-value`, `webx-delivery__dialog`, `webx-delivery__search`, `webx-delivery__popular`,
`webx-delivery__popular-title`, `webx-delivery__popular-list`, `webx-delivery__popular-item`, `webx-delivery__town`.
Tokens: `--webx-delivery-gap`, `--webx-delivery-icon-size` on `.webx-delivery`, `--webx-delivery-dialog-width` on the dialog.

## Change it without forking

| You want                                    | Do this                                                                                                                   |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| A carrier of your own                       | `callAfterResolving(Carriers::class, fn ($c) => $c->register(MyCarrier::class))` in your provider's `boot()`              |
| Its methods                                 | `callAfterResolving(MethodTypes::class, fn ($t) => $t->register(MyBranchType::class))`                                    |
| A type of your own                          | extend `AbstractMethodType`; override `quote()`, `available()`, add to `validate()` and call the parent                   |
| Waybills and tracking of your carrier       | the type implements `CreatesWaybills` and `TracksShipments`; statuses → `ShipmentStatus`; own fields — `DescribesWaybill` |
| A new kind of the shop's points             | `app(PointKinds::class)->define('showroom', ['en' => 'Showroom'])`, then a `store.pickup` method of it                    |
| The shop's courier in a town                | `$method->syncLocalities([$id, …])` — not the pivot by hand: it moves the cache group                                     |
| The popular towns                           | `app(PopularLocalities::class)->set([$id, …])` — the order given is the order shown                                       |
| What a buyer is offered, from your own code | `app(Methods::class)->forLocality(app(Delivery::class)->context($cartState, $localityId))`                                |
| The step's markup                           | `vendor:publish --tag=webx-delivery-views`, or one file in `<layer>/views/vendor/webx-delivery/components/`               |
| The step on a page of your own              | `<x-webx-delivery::step />`; inside your form — `name="delivery"` prefixes its fields                                     |
| A directory on another schedule, or by hand | `webx-delivery.carriers.<key>.schedule.<directory>` — a cron or `manual`                                                  |
| Sync now                                    | `php artisan webx:delivery:sync carrier-a --directory=points` (`--queue` to hand it to the queue)                         |
| Carrier A on a development site             | `WEBX_DELIVERY_FAKE_CARRIER=true` — never in production; then `webx:demo --module=delivery` or a sync                     |
| A field on a form of the section            | a patch on `delivery.method-form` / `delivery.point-form` / `delivery.locality-form`                                      |
| Other words                                 | `php artisan vendor:publish --tag=webx-delivery-lang`                                                                     |

## Do not

- Do not edit anything in `vendor/webx-ui/module-delivery`: if no row above fits, say so.
- Do not call a carrier's API from `available()` or from anything the step reads: the site's own
  tables only (decision 38). `quote()` alone may ask a carrier, cached.
- Do not give a type a model or a query: it gets `Method`, `LocalityRef`, `PointRef` — copies.
- Do not write `delivery_method_localities` or `delivery_points` by the query builder without
  `CacheGroups::bump()` of `delivery` / `delivery-points`.
- Do not put a carrier's name into the base, its tests or its demo: «carrier A», `XX`, the phone `+800 1234 5678`.
- Do not write a carrier's points, nor a parcel's status, from a satellite: it returns, the base writes.
- Do not write `delivery_selections` but through `Selections::put()` (the API): it checks the point,
  the street and the method; nor take a cart's id from a request — `CartHandle` knows the visitor's;
  nor a street the buyer typed anywhere but the selection (decision 31).

## Check your work

- `vendor/bin/phpunit packages/module-delivery/tests` from `php/`; `ChainTest` — §9.3 on a real cart.
- `ApiTest` — every door and refusal; `StepTest` — the step, both couriers, no JavaScript; `SlotTest`
  — the slot; `PerformanceTest` — §17 on 30 000 points and 300 000 streets, no `pull()` from the step.
- `SyncVolumeTest`, `SyncTest`, `StreetsTest` — pages, the threshold, held runs; `tests/Shipping` — parcels; `CarrierContractTest` — carrier A.
- `npx vitest run php/packages/module-delivery` — `delivery.js`; `DistTest` — `dist/` built from it; `Panel/DemoTest` — the demo and its removal.
- `tests/Panel` — the section: `AccessCoverageTest`, geography by hand, methods and points, carriers, queue;
  `tests/Mcp` — the tools: keys never leave, dry runs write nothing, duplicates, no repeated query.

## Read more

- [README.md](README.md) in this directory — the API with examples.
- The delivery spec, §5 (tables), §6 (contract), §7 (sync), §8 (streets), §9 (money), §12 (the
  step), §15 (MCP): https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_DELIVERY.md
