# webx-ui/module-delivery

Delivery for a WebX UI shop: the registry of carriers and method types, the shop's own pick-up
points and courier, the methods a locality has with their prices, and the popular towns of the
checkout.

```bash
composer require webx-ui/module-delivery
php artisan migrate
```

Needs `webx-ui/module-cart` and `webx-ui/geography`. Without the panel screens yet, methods and
points are set up from code.

## Methods

A **type** is code (`store.pickup`, `carrier-a.locker`); a **method** is a row the site turned on —
its own title, icon, price and order. Two pick-ups of the shop are two methods of one type with
another kind of points.

```php
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Types\StoreCourier;
use WebxUi\Delivery\Types\StorePickup;

DeliveryMethod::create([
    'key' => 'store-pickup',
    'type' => StorePickup::KEY,
    'point_kind' => 'store',
    'titles' => ['en' => 'Pick up at a store'],
    'enabled' => true,
]);

DeliveryPoint::create([
    'kind' => 'store',
    'locality_id' => $townA->id,
    'titles' => ['en' => 'Store A'],
    'address' => ['en' => '1 Example street'],
    'phone' => '+800 1234 5678',
]);

$courier = DeliveryMethod::create([
    'key' => 'our-courier',
    'type' => StoreCourier::KEY,
    'titles' => ['en' => 'Our courier'],
    'price' => 1500,             // minor units, one price in every locality
    'enabled' => true,
]);
$courier->syncLocalities([$townA->id, $townB->id]);
```

## What a buyer is offered

```php
use WebxUi\Delivery\Delivery;
use WebxUi\Delivery\Methods;

if (Delivery::needed($cartState)) {
    $context = app(Delivery::class)->context($cartState, $localityId);

    foreach (app(Methods::class)->forLocality($context) as $option) {
        $option->method->title('en');   // "Pick up at a store"
        $option->quote->kind;           // Fixed | Free | CarrierRates
        $option->points;                // points of the method in the locality, or null
        $option->disabled;              // a carrier's courier without streets there
    }
}
```

A method asking for a point is offered only where the locality has an active point of its carrier
and kind; the shop's courier only in its localities; a method of another country or of a carrier
that is off — never. `Methods::evaluate()` returns every method with the reason it is left out.

## A carrier

A satellite `delivery-<carrier>` registers its carrier and its types from its provider:

```php
$this->callAfterResolving(Carriers::class, fn (Carriers $c) => $c->register(CarrierA::class));
$this->callAfterResolving(MethodTypes::class, function (MethodTypes $types) {
    $types->register(BranchType::class);    // carrier-a.branch — Asks::Point, kind 'branch'
    $types->register(CourierType::class);   // carrier-a.courier — Asks::Address
});
```

A type extends `AbstractMethodType`: the method's price and the check of a point or an address
come with it.

## Directories of a carrier

A carrier that keeps its localities, points and streets on the site implements `SyncsDirectories`. It
returns records page by page; the base writes them, matches the localities with the shared
geography, keeps a journal and removes what the carrier no longer has.

```php
use WebxUi\Delivery\Contracts\SyncsDirectories;
use WebxUi\Delivery\Sync\{CarrierUnavailable, Directory, DirectorySchedule, Page, PointRecord, StreetRecord};
use WebxUi\Geography\Matching\LocalityRecord;

final class CarrierA implements Carrier, SyncsDirectories
{
    public function directories(): array
    {
        return [
            new DirectorySchedule(Directory::Localities, '30 3 * * *'),
            new DirectorySchedule(Directory::Points, '30 3 * * *'),
            // Only a carrier that goes to doors: weekly, pages of 2 000 narrow rows.
            new DirectorySchedule(Directory::Streets, '0 4 * * 0', pageSize: 2000),
        ];
    }

    public function pull(Directory $directory, ?string $cursor): Page
    {
        $answer = $this->api->page($directory->value, $cursor)
            ?? throw new CarrierUnavailable('HTTP 503 from the carrier');   // logged, never shown

        return new Page(array_map(fn (array $row) => match ($directory) {
            Directory::Localities => new LocalityRecord(externalId: $row['id'], country: 'XX', name: $row['name'], kind: 'town', region: $row['region']),
            Directory::Points => new PointRecord(externalId: $row['id'], kind: 'branch', localityExternalId: $row['town'], address: $row['address'], number: $row['no']),
            Directory::Streets => new StreetRecord(externalId: $row['id'], localityExternalId: $row['town'], name: $row['name'], kind: 'street'),
        }, $answer['items']), $answer['next']);
    }
}
```

- A page is written in one transaction with the run's cursor: a page tried again writes the same
  rows, and a run the queue picks up again goes on from where it stopped.
- At the end of a **complete** pass, what the carrier did not return is marked `removed_at` —
  unless it is more than `webx-delivery.sync.max_removal_share` (10 %) of what it had: then the
  run is `held`, nothing goes, and `app(Synchronizer::class)->confirm($run)` removes it. A run
  that fails part-way removes nothing.
- Points of a locality not bound yet wait with `locality_id = null` and follow the binding.
- Streets hang on the carrier's locality. After a streets pass every binding counts its streets
  in force (`streets`, one `UPDATE`); a locality with the carrier's courier and no street is
  `warnings.no_streets` of the run. Points and streets follow a successful pass of localities,
  not each other.
- `php artisan webx:delivery:sync [carrier] [--directory=points] [--queue]`; the schedule runs a
  carrier's directories by their cron, localities first; `webx-delivery.carriers.<key>.schedule.<directory>`
  overrides the cron or turns it to `manual`.
- `webx:doctor` warns when a directory's last success is older than three intervals of its
  schedule, or its last pass is held.

`WebxUi\Delivery\Testing\FakeCarrier` is «Carrier A» for tests: a directory of any size, a page
that fails, an empty answer, records that vanish; streets when asked for (`size(streets: 300000)`),
with `L0` a city where its courier goes and no street is given.

## Streets

What the step reads — the site's tables only, never the carrier (decision 38):

```php
use WebxUi\Delivery\Streets;

$streets = app(Streets::class);

// A carrier's courier: its streets in force in the locality, a prefix of the name, ≤ 10 — two queries.
$streets->forCarrier('carrier-a', $localityId, 'exa', 'en');   // list<StreetRef>: streetId, name "Example 1 street"
// The shop's courier: the streets every carrier bound to the town has there, as text (streetId null).
$streets->forStore($localityId, 'exa', 'en');
// The street a carrier's courier is sent to: in force, of this carrier, in this locality.
$streets->find('carrier-a', $localityId, 'S1');
```

- A carrier's courier takes a street of its directory only (decision 31): `validate()` of
  `AbstractMethodType` wants a `streetId` of it, and the name in the selection is the directory's.
  Where the carrier has no street, the courier is shown disabled with a hint, and `validate()`
  refuses it under `method` with the same words.
- The shop's courier takes any text, never an id; what the buyer typed goes into the selection
  only — not into `delivery_streets`, not into the geography.
- A street is shown with its kind's word where the language puts it:
  `webx-delivery::delivery.street_kinds.<kind>` («:name street», «ul. :name»).

## Popular towns

```php
use WebxUi\Delivery\PopularLocalities;

app(PopularLocalities::class)->set([$townC->id, $townA->id]);
app(PopularLocalities::class)->suggestions('XX', 'en');   // the buttons of «Where to deliver?»
```

A deleted locality leaves the list; a switched-off one stays and is not shown.

## Spec

[`WEBX_UI_MODULE_DELIVERY.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_DELIVERY.md).
