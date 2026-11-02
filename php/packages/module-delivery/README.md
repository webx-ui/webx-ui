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

## Popular towns

```php
use WebxUi\Delivery\PopularLocalities;

app(PopularLocalities::class)->set([$townC->id, $townA->id]);
app(PopularLocalities::class)->suggestions('XX', 'en');   // the buttons of «Where to deliver?»
```

A deleted locality leaves the list; a switched-off one stays and is not shown.

## Spec

[`WEBX_UI_MODULE_DELIVERY.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_DELIVERY.md).
