# webx-ui/module-delivery

Delivery for a shop on WebX UI: which methods a buyer is offered in the town they picked, at what
price, and what each method asks for — a point or an address. The shop's own pick-ups and courier
are built in; a carrier comes as a satellite `delivery-<carrier>` that registers its carrier and
method types. Requires `webx-ui/module-cart` (the context of a method is the counted cart) and
`webx-ui/geography` (every locality, point and binding stands on the shared geography). The
checkout of `webx-ui/module-orders` asks it; orders work without it. Words — `webx-delivery`.

## What it owns

- **Tables** (migrations `2026_01_02_*`, after the geography):
  - `delivery_methods` — what the site turned on: `key` (`store-pickup`), `type` (`store.pickup`),
    `titles`, `descriptions`, `icon_id`, `point_kind`, `countries`, `price` (minor units; null —
    the type prices it), `honours_paid_by_store`, `settings`, `enabled`, `position`;
  - `delivery_method_localities` — where the shop's courier goes;
  - `delivery_carriers` — `key` (`store` is there from the migration), `enabled`, `settings`,
    `credentials` (`encrypted:array`, hidden), `meta`;
  - `delivery_carrier_localities` — a carrier's locality bound to a shared one or queued:
    `status`, `method`, `reason`, `record`, `features` (`["courier"]`), `streets`;
  - `delivery_points` — the points of every carrier and of the shop (`carrier = store`), with the
    normalised `search` string the model writes;
  - `delivery_sync_runs` — the journal of directory syncs;
  - `delivery_popular_localities` — the popular towns of the checkout dialog: `locality_id`,
    `position`; one list for the site (decision 40).
- **Contracts** in `WebxUi\Delivery\Contracts`: `MethodType` (key `<carrier>.<kind>`, `asks()`,
  `pointKinds()`, `countries()`, `available()`, `quote()`, `validate()`) and `Carrier`.
  `AbstractMethodType` gives the method's price and the default check of a point or an address.
- **Registries** (singletons): `Carriers` (`store` built in) and `MethodTypes` (`store.pickup`,
  `store.courier` built in). A class is made on first use; a repeated key, a malformed one or a
  type whose carrier is not registered is an exception.
- **Built-in types**: `Types\StorePickup` — a point of the method's `point_kind` (`store`, `pickup`
  or a kind the shop named, `PointKinds::define()`), offered where the locality has an active
  one; `Types\StoreCourier` — the address, offered only in the method's localities, at the
  method's one price.
- **`Methods::forLocality(DeliveryContext)`** — the offered `MethodOption`s in the panel's order
  (price, points, `disabled` + `hint` for a carrier's courier without streets); `evaluate()` —
  every enabled method with the reason it is not offered (`MethodOption::NO_POINTS`, …).
- **`Quote`** — `fixed()`, `free($was)`, `carrierRates($estimate)`, `unavailable($reason)`;
  `inTotal`, `payer`.
- **`Delivery`** — `Delivery::needed(CartState)`: false without a shippable line that can be
  bought; `context($cart, $locality)` builds the `DeliveryContext`.
- **`PopularLocalities`** — `ids()`, `suggestions($country, $locale)` (active ones only, two
  queries), `set($ids)` (the whole list in order), `add($id, $position)`, `remove($id)`.
- **`PointKinds`** — the built-in kinds with the dictionary's words and the shop's own.
- **Cache group** `delivery` — methods, carriers, the courier's localities, countries.
- **Locality references** — `delivery_points` and `delivery_carrier_localities` keep a locality
  from deletion: the geography's editor switches it off instead.

## Change it without forking

| You want                                    | Do this                                                                                                      |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| A carrier of your own                       | `callAfterResolving(Carriers::class, fn ($c) => $c->register(MyCarrier::class))` in your provider's `boot()` |
| Its methods                                 | `callAfterResolving(MethodTypes::class, fn ($t) => $t->register(MyBranchType::class))`                       |
| A type of your own                          | extend `AbstractMethodType`; override `quote()`, `available()`, add to `validate()` and call the parent      |
| The shop's courier priced another way       | register a type under your own key extending `AbstractMethodType` with `asks() = Address`                    |
| A new kind of the shop's points             | `app(PointKinds::class)->define('showroom', ['en' => 'Showroom'])`, then a `store.pickup` method of it       |
| The shop's courier in a town                | `$method->syncLocalities([$id, …])` — not the pivot by hand: it moves the cache group                        |
| The popular towns                           | `app(PopularLocalities::class)->set([$id, …])` — the order given is the order shown                          |
| What a buyer is offered, from your own code | `app(Methods::class)->forLocality(app(Delivery::class)->context($cartState, $localityId))`                   |
| Other words                                 | `php artisan vendor:publish --tag=webx-delivery-lang`                                                        |

## Do not

- Do not edit anything in `vendor/webx-ui/module-delivery`. The rows above are the supported ways;
  if none fits, the package is missing a seam — say so instead of working around it.
- Do not call a carrier's API from `available()` or from anything the checkout reads for
  directories: the step reads the site's own tables only (decision 38). `quote()` alone may ask a
  carrier, cached (§9.2 of the spec).
- Do not give a type a model or a query: it gets `Method`, `LocalityRef`, `PointRef` — copies.
- Do not write `delivery_method_localities` with the query builder without `CacheGroups::bump('delivery')`:
  the methods are cached and will not see it.
- Do not put a carrier's name into the base, its tests or its demo: «carrier A», `XX`, invented
  towns, the phone `+800 1234 5678`.
- Do not delete a locality to drop it from the popular towns: `remove()` it from the list.

## Check your work

- `vendor/bin/phpunit packages/module-delivery/tests` from `php/` of the monorepo — the registries,
  the methods of a locality on hand-made towns, the default checks, the quotes, the popular towns,
  the migrations rolling back, the dictionary in ten languages.
- `MethodsTest::a_warm_list_costs_…` — a warm list is two queries whatever the number of methods.
- A method you turned on shows in `app(Methods::class)->forLocality($context)`; `evaluate()` says
  why one does not.

## Read more

- [README.md](README.md) in this directory — the API with examples.
- The delivery spec, §5 (tables), §6 (contract and registry), §10 (built-in methods), decision 40
  (popular towns): https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_DELIVERY.md
