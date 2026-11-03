<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Demo;

use Illuminate\Database\Eloquent\Model;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Admin\Demo\DemoWords;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Models\DeliverySyncRun;
use WebxUi\Delivery\Panel\MethodForm;
use WebxUi\Delivery\Panel\PointForm;
use WebxUi\Delivery\PopularLocalities;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Testing\CarrierABranch;
use WebxUi\Delivery\Testing\CarrierACourier;
use WebxUi\Delivery\Testing\CarrierALocker;
use WebxUi\Delivery\Testing\FakeCarrier;
use WebxUi\Delivery\Types\StoreCourier;
use WebxUi\Delivery\Types\StorePickup;
use WebxUi\Geography\Editor;
use WebxUi\Geography\Models\GeoCountry;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Models\GeoRegion;
use WebxUi\Localization\Locales;

/**
 * A shop that delivers without a single carrier (§18; decisions 40 and 42): the country `XX` — the
 * private-use code, no real country — with two regions and six towns made by hand, the shop's
 * points of two kinds (stores and a pick-up point), a method for each kind, the shop's courier in
 * two towns at one price, and three popular towns in the dialog «Where to deliver the order?».
 *
 * Everything goes through the doors of the panel — the geography's editor, the forms of a point
 * and of a method, the list of popular towns — so the demo is what an administrator would have
 * made by hand, duplicates refused alike.
 *
 * Where the fake carrier is switched on (`webx-delivery.fake_carrier`), carrier A comes too: its
 * row, a sync of its three directories right away, and a method for each of its types, switched
 * on. What the sync wrote — its towns and regions, bindings, points, runs; the streets go with
 * their bindings — is in the journal too, so `webx:demo --remove` leaves no carrier behind.
 */
final class DeliveryDemo
{
    /** The country of the demo: user-assigned in ISO 3166-1, no real country. */
    public const string COUNTRY = 'XX';

    /** Towns of the demo: name → [region, kind, weight, lat, lng]. */
    private const array TOWNS = [
        'Town A' => ['Region A', 'city', 900, 10.12, 20.31],
        'Town B' => ['Region A', 'town', 600, 10.25, 20.44],
        'Town C' => ['Region A', 'village', 200, 10.38, 20.12],
        'Town D' => ['Region B', 'city', 800, 11.04, 21.27],
        'Town E' => ['Region B', 'town', 400, 11.16, 21.53],
        'Town F' => ['Region B', 'village', 100, 11.29, 21.08],
    ];

    /** The towns of the dialog's buttons, in their order. */
    private const array POPULAR = ['Town A', 'Town D', 'Town B'];

    /** The shop's own points: [kind, title, town, address, lat, lng]. */
    private const array POINTS = [
        ['store', 'Store A', 'Town A', '1 Example street', 10.121, 20.312],
        ['store', 'Store B', 'Town D', '1 Example street', 11.041, 21.271],
        ['pickup', 'Pick-up point A', 'Town A', '7 Sample avenue', 10.124, 20.305],
        ['pickup', 'Pick-up point C', 'Town C', '3 Oak lane', 10.381, 20.122],
    ];

    private const string PHONE = '+800 1234 5678';

    /** @var array<string, mixed>|null */
    private ?array $document = null;

    public function __construct(
        private readonly Editor $editor,
        private readonly PointForm $points,
        private readonly MethodForm $methods,
        private readonly PopularLocalities $popular,
        private readonly Carriers $carriers,
        private readonly Synchronizer $sync,
        private readonly Locales $locales,
        private readonly DemoWords $words,
    ) {}

    public function seed(DemoLedger $ledger): void
    {
        // A shop that already delivers has its own towns and methods; made-up ones among them would
        // be offered to its buyers.
        if (DeliveryMethod::query()->exists() || GeoLocality::query()->where('country_code', self::COUNTRY)->exists()) {
            $ledger->note('The site already has delivery methods or towns in XX; the delivery demo left them alone.');

            return;
        }

        $towns = $this->geography($ledger);
        $this->shopPoints($ledger, $towns);
        $this->shopMethods($ledger, $towns);

        if ($this->popular->ids() === []) {
            $this->popular->set(array_map(static fn (string $town): int => $towns[$town]->id, self::POPULAR));
        }

        if ($this->carriers->has(FakeCarrier::KEY)) {
            $this->carrierA($ledger);
        }
    }

    /**
     * The country, its regions and towns, by the editor — marked made by hand.
     *
     * @return array<string, GeoLocality> by name
     */
    private function geography(DemoLedger $ledger): array
    {
        $country = GeoCountry::query()->find(self::COUNTRY);

        if ($country instanceof GeoCountry) {
            $ledger->changed($country, ['active', 'position']);
            $this->editor->enableCountry(self::COUNTRY);
        } else {
            $ledger->created($this->editor->enableCountry(self::COUNTRY, position: 0), 'Country XX');
        }

        $regions = [];

        foreach (array_unique(array_column(self::TOWNS, 0)) as $name) {
            $regions[$name] = GeoRegion::query()->where('country_code', self::COUNTRY)->get()
                ->first(static fn (GeoRegion $region): bool => in_array($name, (array) $region->names, true));

            if ($regions[$name] === null) {
                $regions[$name] = $this->editor->createRegion(self::COUNTRY, $this->same($name));
                $ledger->created($regions[$name], $name);
            }
        }

        $towns = [];

        foreach (self::TOWNS as $name => [$region, $kind, $weight, $lat, $lng]) {
            $towns[$name] = $this->editor->createLocality([
                'country' => self::COUNTRY,
                'region_id' => $regions[$region]->id,
                'names' => $this->same($name),
                'kind' => $kind,
                'weight' => $weight,
                'lat' => $lat,
                'lng' => $lng,
            ]);
            $ledger->created($towns[$name], $name);
        }

        return $towns;
    }

    /** @param  array<string, GeoLocality>  $towns */
    private function shopPoints(DemoLedger $ledger, array $towns): void
    {
        $week = ['mon' => [['09:00', '19:00']], 'tue' => [['09:00', '19:00']], 'wed' => [['09:00', '19:00']], 'thu' => [['09:00', '19:00']], 'fri' => [['09:00', '19:00']], 'sat' => [['10:00', '16:00']], 'sun' => []];

        foreach (self::POINTS as [$kind, $title, $town, $address, $lat, $lng]) {
            $point = $this->points->save(new DeliveryPoint, [
                'kind' => $kind,
                'titles' => $this->words->of($title),
                'locality_id' => $towns[$town]->id,
                'address' => $this->words->of($address),
                'lat' => $lat,
                'lng' => $lng,
                'phone' => self::PHONE,
                'active' => true,
                'schedule' => ['week' => $week],
            ]);
            $ledger->created($point, $title);
        }
    }

    /** @param  array<string, GeoLocality>  $towns */
    private function shopMethods(DemoLedger $ledger, array $towns): void
    {
        $this->method($ledger, StorePickup::KEY, 'store', ['point_kind' => 'store']);
        $this->method($ledger, StorePickup::KEY, 'pickup', ['point_kind' => 'pickup', 'price' => 2]);
        $this->method($ledger, StoreCourier::KEY, 'courier', ['price' => 15, 'localities' => [$towns['Town A']->id, $towns['Town B']->id]]);
    }

    /**
     * Carrier A: switched on, synced now — its towns bind to the regions made by hand — and a
     * method for each of its types.
     */
    private function carrierA(DemoLedger $ledger): void
    {
        $row = DeliveryCarrier::query()->find(FakeCarrier::KEY);

        if ($row instanceof DeliveryCarrier) {
            $ledger->changed($row, ['enabled']);
            $row->update(['enabled' => true]);
        } else {
            $ledger->created(DeliveryCarrier::query()->create(['key' => FakeCarrier::KEY, 'enabled' => true]), 'Carrier A');
        }

        $before = $this->lastIds();

        foreach ([Directory::Localities, Directory::Points, Directory::Streets] as $directory) {
            $run = $this->sync->run(FakeCarrier::KEY, $directory);

            if ($run->status !== RunStatus::Succeeded) {
                $ledger->note("Carrier A: the sync of {$directory->value} ended {$run->status->value} — see the panel's carriers.");
            }
        }

        // Parents first: the journal is played backwards, so a town goes after its bindings and
        // points, and a region after its towns.
        foreach ($before as $class => $last) {
            $class::query()->where('id', '>', $last)->orderBy('id')->each(static function (Model $model) use ($ledger): void {
                $ledger->created($model, class_basename($model).' '.$model->getKey());
            });
        }

        foreach ([CarrierABranch::KEY, CarrierALocker::KEY, CarrierACourier::KEY] as $type) {
            $this->method($ledger, $type, $type);
        }
    }

    /**
     * The last id of every table a sync writes into, in the order the journal wants them.
     *
     * @return array<class-string<Model>, int>
     */
    private function lastIds(): array
    {
        $ids = [];

        foreach ([GeoRegion::class, GeoLocality::class, DeliveryCarrierLocality::class, DeliveryPoint::class, DeliverySyncRun::class] as $class) {
            $ids[$class] = (int) $class::query()->max('id');
        }

        return $ids;
    }

    /**
     * A method of a type, its words from `resources/demo/delivery.json` under `$words`.
     *
     * @param  array<string, mixed>  $values
     */
    private function method(DemoLedger $ledger, string $type, string $words, array $values = []): void
    {
        $text = $this->document()['methods'][$words] ?? [];
        $values['titles'] = $this->words->of($text['title'] ?? $words);

        if (isset($text['description'])) {
            $values['descriptions'] = $this->words->of($text['description']);
        }

        $method = $this->methods->save(new DeliveryMethod(['type' => $type]), [...$values, 'enabled' => true]);
        $ledger->created($method, $method->titles[$this->locales->defaultCode()] ?? $method->key);
    }

    /**
     * The words of the demo, in the languages they are written in (`Setup\Catalogue` of the panel names them).
     *
     * @return array<string, mixed>
     */
    private function document(): array
    {
        return $this->document ??= (array) json_decode((string) file_get_contents(__DIR__.'/../../resources/demo/delivery.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * A proper name, the same in every language of the site.
     *
     * @return array<string, string>
     */
    private function same(string $name): array
    {
        return array_fill_keys($this->locales->codes(), $name);
    }
}
