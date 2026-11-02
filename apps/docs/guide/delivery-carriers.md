# Delivery carriers

A carrier comes to a WebX UI shop as a satellite package, `delivery-<carrier>`, next to
`webx-ui/module-delivery`. The base keeps everything the checkout reads — towns, points, streets,
prices of the shop's own methods — in the site's own tables; the satellite only answers: it gives
the carrier's directories page by page when the sync asks, prices a parcel, makes a waybill and
says where a parcel is. The checkout never waits for a carrier's API except for a price.

This guide writes one, «Carrier A», and walks it through on the fake carrier the base ships for
its tests — `WebxUi\Delivery\Testing\FakeCarrier`, the same «Carrier A» the code below describes.

## The package

```
delivery-carrier-a/
  src/CarrierA.php                 # Carrier + SyncsDirectories
  src/Types/{BranchType,LockerType,CourierType}.php
  src/CarrierAServiceProvider.php
  database/migrations/             # a method per type, switched off
  lang/                            # webx-delivery-carrier-a: the carrier's own words only
  tests/CarrierContractTest.php    # on recorded answers of the API
  tests/answers/                   # the recorded answers
  AGENTS.md, README.md
```

It requires `webx-ui/module-delivery`. The carrier's name lives in the satellite only: the base,
its tests and its demo say «carrier A», the country `XX` and the phone `+800 1234 5678`.

## The carrier

```php
use WebxUi\Delivery\ConnectionResult;
use WebxUi\Delivery\Contracts\Carrier;
use WebxUi\Delivery\Contracts\SyncsDirectories;

final class CarrierA implements Carrier, SyncsDirectories
{
    public function __construct(private readonly Api $api) {}

    public function key(): string { return 'carrier-a'; }

    public function title(string $locale): string { return 'Carrier A'; }

    public function icon(): ?string { return null; }

    /** ISO 3166-1 codes the carrier works in. */
    public function countries(): array { return ['XX']; }

    /** Fields of the panel: the sender for waybills, defaults. */
    public function settings(): array { return [['name' => 'sender_phone', 'type' => 'text']]; }

    /** Secret fields: stored encrypted, shown as «set», never read back, never given to an agent. */
    public function credentials(): array { return [['name' => 'api_key', 'type' => 'secret']]; }

    public function testConnection(array $credentials): ConnectionResult
    {
        return $this->api->ping($credentials['api_key'] ?? '')
            ? ConnectionResult::ok()
            : ConnectionResult::failed('The key was refused.');
    }

    // directories() and pull() — below
}
```

The address of the API is the satellite's config, not something a person types in the panel.

### The sender from the carrier's account

A carrier whose API knows the shop's senders lets the panel offer them instead of a text field:
it implements `ProvidesSettingOptions` and marks the fields `options: 'carrier'`. `depends` names
the fields the options wait for — a new sender empties its contact — and `search` asks as the
admin types, for lists of hundreds.

```php
use WebxUi\Delivery\Contracts\ProvidesSettingOptions;
use WebxUi\Delivery\Settings\SettingOption;

final class CarrierA implements Carrier, ProvidesSettingOptions, SyncsDirectories
{
    public function settings(): array
    {
        return [
            ['name' => 'sender', 'type' => 'select', 'options' => 'carrier'],
            ['name' => 'contact', 'type' => 'select', 'options' => 'carrier', 'depends' => ['sender']],
        ];
    }

    /** @return list<SettingOption> at most 50 */
    public function settingOptions(string $field, array $values, array $credentials, ?string $query = null): array
    {
        return match ($field) {
            'sender' => $this->api->senders($credentials['api_key']),
            'contact' => $this->api->contacts($credentials['api_key'], $values['sender']),
        };
    }
}
```

The base asks with the stored key — none set, it does not ask — keeps the options ten minutes,
asks again past the cache on «Refresh lists» and on every save, and takes only a value the list
has. The label of the chosen value is kept in `settings._labels`, so the dialog shows «Sender A»
when the carrier is down; a value gone from the account is said, never wiped. Throw
`CarrierUnavailable` when the API does not answer — within five seconds, a person is waiting. The
agent's door is the same: `delivery_carriers_options` and `delivery_carriers_update`.

## Directories

A carrier that keeps its towns, points and streets on the site implements `SyncsDirectories`:
which directories it has, on which cron, and one page of a directory from a cursor.

```php
use WebxUi\Delivery\Sync\{Directory, DirectorySchedule, Page, PointRecord, StreetRecord};
use WebxUi\Geography\Matching\LocalityRecord;

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
    $answer = $this->api->page($directory->value, $cursor);   // throws CarrierUnavailable on a 5xx

    return new Page(
        array_map(fn (array $row) => match ($directory) {
            Directory::Localities => new LocalityRecord(externalId: $row['id'], country: 'XX', name: $row['name'], kind: 'town', region: $row['region']),
            Directory::Points => new PointRecord(externalId: $row['id'], kind: 'branch', localityExternalId: $row['town'], address: $row['address']),
            Directory::Streets => new StreetRecord(externalId: $row['id'], localityExternalId: $row['town'], name: $row['name']),
        }, $answer['items']),
        $answer['next'],   // null on the last page
    );
}
```

What the base does with the pages:

- **Towns** are matched with the shared geography — by the country, the region, the type and the
  name key, whatever the apostrophes, dashes or letter case. A town an administrator made by hand
  is bound to, not made twice and not renamed; what no rule places goes to the binding queue of
  the panel.
- **Every page is written in one transaction**, and a page that failed is pulled again from the
  same cursor: `pull()` must give the same records for the same cursor.
- **What a complete pass did not see is removed** — unless it would be more than 10 % of the
  carrier's records (`webx-delivery.sync.max_removal_share`): then the run is held until a person
  confirms it in the panel. An empty or cut-off answer of an API never takes a network away.
- A run that fails in the middle removes nothing; the last complete directory stays in force.

Throw `WebxUi\Delivery\Sync\CarrierUnavailable` for an API that did not answer: the run tries the
page again after a pause, the panel shows the failure in words.

## Method types

A type is what a shop switches on as a method: «Carrier A branch», «Carrier A courier». It extends
`AbstractMethodType`, which brings the method's price and the check of a point or an address.

```php
use WebxUi\Delivery\{AbstractMethodType, Asks, DeliveryContext, Method, Quote};

final class BranchType extends AbstractMethodType
{
    public function key(): string { return 'carrier-a.branch'; }   // <carrier>.<kind>

    public function asks(): Asks { return Asks::Point; }

    public function pointKinds(): array { return ['branch']; }

    public function quote(Method $method, DeliveryContext $context): Quote
    {
        // A price set on the method wins; otherwise the buyer pays the carrier on receipt.
        return $method->price === null ? Quote::carrierRates() : Quote::fixed($method->price);
    }
}
```

`quote()` is the only place a type may call the carrier's API — the base caches the answer for
ten minutes and gives it two seconds; late or failed, the method shows «By carrier rates». A
courier type asks `Asks::Address`: its street comes only from the carrier's streets directory, and
a town the carrier gives no streets for shows the method switched off with a hint.

A type that implements `CreatesWaybills` makes waybills from the order's card; one that implements
`TracksShipments` is asked where parcels are every 30 minutes, its states translated into the
base's `ShipmentStatus` — `received` sets the order's milestone «received», once.

The waybill gets the recipient's first and last name apart, as the order keeps them, and
`carrierLocality` — the carrier's own id of the town. What the carrier asks beyond that — weight,
seats, what is inside — the type declares with `DescribesWaybill`: `waybillFields()` in the form
of the settings (filled from the carrier's setting of the same name, or `default`) reach it in
`WaybillRequest::$extra`; `supportsCod(): false` hides the cash on delivery in the dialog and
refuses it from an agent.

## Registering

```php
use WebxUi\Delivery\{Carriers, MethodTypes};

public function boot(): void
{
    $this->callAfterResolving(Carriers::class, fn (Carriers $carriers) => $carriers->register(CarrierA::class));
    $this->callAfterResolving(MethodTypes::class, function (MethodTypes $types): void {
        $types->register(BranchType::class);
        $types->register(LockerType::class);
        $types->register(CourierType::class);
    });
}
```

The satellite's migration makes a method for each type with `enabled = false`: the shop switches
on the ones it wants, in the panel's «Methods».

## The contract test

The base ships the contract every satellite passes, `WebxUi\Delivery\Testing\CarrierContract`. Run
it on answers of the API recorded once — never on the live API:

```php
use PHPUnit\Framework\TestCase;
use WebxUi\Delivery\Contracts\Carrier;
use WebxUi\Delivery\Testing\CarrierContract;

final class CarrierContractTest extends TestCase
{
    use CarrierContract;

    protected function carrier(): Carrier
    {
        return new CarrierA(new RecordedApi(__DIR__.'/answers'));
    }

    protected function methodTypes(): array
    {
        return [new BranchType, new LockerType, new CourierType];
    }
}
```

It checks the key and the countries; walks every directory from the first page to a `null`
cursor, pulling each page twice and asking for the same records; the record classes, ids unique in
a pass, names the normalizer keeps something of, a type of locality the geography knows; points and
streets that point at the carrier's own towns; and types named after the carrier, a point type with
its kinds. The base runs the same contract on «Carrier A» —
`php/packages/module-delivery/tests/CarrierContractTest.php`.

Next to it a satellite keeps a `PerformanceTest`: a page of 500 records (2 000 streets) writes in a
constant number of queries.

## Walk it through on carrier A

The fake carrier can run on a development site, so the whole road of a carrier is there to click
through before a line of a satellite is written:

1. In the site's `.env`: `WEBX_DELIVERY_FAKE_CARRIER=true`. Never in production — its waybills go
   nowhere.
2. `php artisan webx:demo --module=delivery` — the shop's own towns, points and methods, and carrier
   A synced with its three methods. Or by hand: switch carrier A on in «Carriers» and press «Run»,
   or `php artisan webx:delivery:sync carrier-a`.
3. In «Carriers» — each directory with its last success and counters; in «Geography» — its towns,
   «Region A» and «Region B» the same as the ones made by hand; in «Binding queue» — what no rule
   placed.
4. In the checkout — a town of carrier A, «Carrier A branch» with its list of branches and a search,
   «Carrier A courier» with a street from its directory only; Town 0 shows the courier switched off —
   the carrier gives no streets there.
5. With `webx-ui/module-orders` — an order with a branch, then «Create waybill» on the order's tab
   «Delivery»: a number `A-0001` and a label.

## Read more

- [Delivery](./delivery.md) — the shop's own methods, the panel, the agents' tools.
- The delivery spec, §7 (directories), §8 (streets), §11 (waybills), §13 (the satellite) —
  [`WEBX_UI_MODULE_DELIVERY.md`](https://github.com/webx-ui/webx-ui/blob/main/docs/architecture/WEBX_UI_MODULE_DELIVERY.md).
