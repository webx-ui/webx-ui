<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Models\DeliverySyncRun;
use WebxUi\Delivery\NovaPoshta\Api\AccountKey;
use WebxUi\Delivery\NovaPoshta\Api\Client;
use WebxUi\Delivery\NovaPoshta\Api\Pace;
use WebxUi\Delivery\NovaPoshta\NovaPoshtaCarrier;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\StoredLeadingCarriers;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;
use WebxUi\Geography\Editor;
use WebxUi\Geography\LocalitySearch;
use WebxUi\Geography\Models\GeoLocality;
use WebxUi\Geography\Models\GeoRegion;
use WebxUi\Geography\Normalizer;
use WebxUi\Localization\Locales;

/**
 * The towns through the base's sync (§6.2, §6.5): the carrier leads Ukraine and makes its towns
 * and regions; a town or a region made by hand is bound to, never doubled or renamed; a field a
 * person changed stays as they left it. On the recorded pages, by pages of 20.
 */
final class SyncTest extends TestCase
{
    private const array PAGES = [
        '1' => 'Address.getCities.page-1',
        '2' => 'Address.getCities.page-2',
        '3' => 'Address.getCities.last-page',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(NovaPoshtaCarrier::class, new NovaPoshtaCarrier(
            $this->app->make(Client::class),
            new AccountKey,
            new Pace(0),
            $this->app->make(Locales::class),
            pageSize: 20,
        ));
        DeliveryCarrier::query()->create(['key' => NovaPoshtaCarrier::KEY, 'enabled' => true, 'credentials' => ['api_key' => self::KEY]]);

        Http::fake([self::URL => static function (Request $request) {
            $page = (string) ($request['methodProperties']['Page'] ?? '');

            return Http::response(self::fixture(self::PAGES[$page] ?? 'Address.getCities.past-the-end'));
        }]);
    }

    /**
     * A Ukrainian site: the main language is the one a region is matched by (its key, §4.3), so
     * a region typed by hand in Ukrainian is the carrier's region.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('webx-localization.locales', [['code' => 'uk', 'default' => true], ['code' => 'en']]);
    }

    private function sync(): DeliverySyncRun
    {
        $run = $this->app->make(Synchronizer::class)->run(NovaPoshtaCarrier::KEY, Directory::Localities, Trigger::Command);
        $this->assertSame(RunStatus::Succeeded, $run->status, (string) $run->error);

        return $run;
    }

    /** @return list<GeoLocality> */
    private function named(string $name): array
    {
        return GeoLocality::query()->get()
            ->filter(static fn (GeoLocality $locality): bool => in_array(Normalizer::key($name), array_map(Normalizer::key(...), $locality->names), true))
            ->values()->all();
    }

    #[Test]
    public function the_carrier_leads_ukraine_and_makes_its_towns_and_regions(): void
    {
        $run = $this->sync();

        $this->assertSame([4, 60, 60], [$run->pages, $run->seen, $run->created]);
        $this->assertSame(NovaPoshtaCarrier::KEY, $this->app->make(StoredLeadingCarriers::class)->leader('UA'));

        $town = $this->named('Абазівка')[0];
        $this->assertSame(GeoLocality::query()->whereKey($town->id)->value('created_by'), 'carrier:'.NovaPoshtaCarrier::KEY);
        $this->assertSame('village', $town->kind);
        $this->assertSame('Полтавська', GeoRegion::query()->findOrFail($town->region_id)->name('uk'));
        // One region a name: «Полтавська» came on all three pages.
        $this->assertSame(1, GeoRegion::query()->get()->filter(static fn (GeoRegion $region): bool => $region->name('uk') === 'Полтавська')->count());

        // Only reading went to the carrier.
        $this->assertSame(['getCities'], array_values(array_unique(array_column($this->journalEntries(), 'method'))));
    }

    #[Test]
    public function a_second_night_makes_nothing_new(): void
    {
        $this->sync();
        $towns = GeoLocality::query()->count();
        $regions = GeoRegion::query()->count();

        $again = $this->sync();

        $this->assertSame([60, 0, 60], [$again->seen, $again->created, $again->updated]);
        $this->assertSame([$towns, $regions], [GeoLocality::query()->count(), GeoRegion::query()->count()]);
    }

    #[Test]
    public function a_town_and_a_region_made_by_hand_are_bound_to_never_doubled_or_renamed(): void
    {
        $editor = $this->app->make(Editor::class);
        $region = $editor->createRegion('UA', ['uk' => 'Полтавська', 'en' => 'Poltava']);
        $town = $editor->createLocality(['country' => 'UA', 'region_id' => $region->id, 'kind' => 'village', 'names' => ['en' => 'Abazivka', 'uk' => 'Абазівка']]);

        $this->sync();

        $binding = DeliveryCarrierLocality::query()->where('carrier', NovaPoshtaCarrier::KEY)->where('external_id', 'fc5f1e3c-928e-11e9-898c-005056b24375')->firstOrFail();
        $this->assertSame($town->id, $binding->locality_id);
        $this->assertSame('auto_exact', $binding->method);
        $this->assertCount(1, $this->named('Абазівка'));

        $town->refresh();
        $this->assertSame('manual', $town->created_by);
        $this->assertEquals(['uk' => 'Абазівка', 'en' => 'Abazivka'], $town->names);
        $this->assertEquals(['uk' => 'Полтавська', 'en' => 'Poltava'], $region->refresh()->names);
        $this->assertSame(1, GeoRegion::query()->get()->filter(static fn (GeoRegion $one): bool => $one->name('uk') === 'Полтавська')->count());
    }

    #[Test]
    public function a_name_changed_by_hand_survives_the_next_night(): void
    {
        $this->sync();
        $town = $this->named('Абранка')[0];
        $this->app->make(Editor::class)->updateLocality($town, ['names' => ['uk' => 'Абранка', 'en' => 'Abranka']]);

        $this->sync();

        $this->assertSame(['uk' => 'Абранка', 'en' => 'Abranka'], $town->refresh()->names);
        $this->assertTrue($town->isManual('names'));
    }

    #[Test]
    public function namesakes_the_carrier_tells_apart_only_by_brackets_are_two_towns_with_their_districts(): void
    {
        $this->sync();

        // «Щасливе (Бориспільський р-н)» and «Щасливе (Згурівський р-н)»: two villages of one area,
        // without coordinates. «Білин (Волинська обл.)» and «Білин (Володимир-Волинський р-н)»: one
        // of them has no district to tell it by. A carrier never lists one town twice, so each
        // pair is two towns (decision of 02.11.2026).
        foreach (['Щасливе' => 'Київська', 'Білин' => 'Волинська'] as $name => $area) {
            $bound = $this->bindingsOf($name, $area)->pluck('locality_id')->all();

            $this->assertCount(2, $bound, $name);
            $this->assertNotContains(null, $bound, $name);
            $this->assertCount(2, array_unique($bound), "Two villages {$name} bound to one town.");
        }

        $labels = array_map(
            static fn (GeoLocality $town): string => $town->label('uk'),
            GeoLocality::query()->whereKey($this->bindingsOf('Щасливе', 'Київська')->pluck('locality_id'))->orderBy('id')->get()->all(),
        );
        $this->assertSame(['Щасливе, Бориспільський р-н, Київська', 'Щасливе, Згурівський р-н, Київська'], $labels);

        // The buyer's suggestions tell them apart by the district.
        $suggested = array_column(array_map(
            static fn ($suggestion): array => $suggestion->toArray(),
            $this->app->make(LocalitySearch::class)->suggest('UA', 'Щасл', 'uk', 10),
        ), 'label');
        $this->assertContains('Щасливе, Бориспільський р-н, Київська', $suggested);
        $this->assertContains('Щасливе, Згурівський р-н, Київська', $suggested);

        // The next night binds each record to its own town again.
        $before = $this->bindingsOf('Щасливе', 'Київська')->pluck('locality_id', 'external_id')->all();
        $this->sync();
        $this->assertSame($before, $this->bindingsOf('Щасливе', 'Київська')->pluck('locality_id', 'external_id')->all());
    }

    #[Test]
    public function a_hand_made_namesake_takes_one_record_and_the_other_makes_a_town(): void
    {
        $editor = $this->app->make(Editor::class);
        $region = $editor->createRegion('UA', ['uk' => 'Київська']);
        $town = $editor->createLocality(['country' => 'UA', 'region_id' => $region->id, 'kind' => 'village', 'names' => ['uk' => 'Щасливе']]);

        $this->sync();

        $bound = $this->bindingsOf('Щасливе', 'Київська')->pluck('locality_id')->all();
        $this->assertContains($town->id, $bound);
        $this->assertCount(2, array_unique($bound), 'The hand-made village took both records.');
    }

    /** @return Collection<int, DeliveryCarrierLocality> */
    private function bindingsOf(string $name, string $area): Collection
    {
        return DeliveryCarrierLocality::query()
            ->where('carrier', NovaPoshtaCarrier::KEY)
            ->orderBy('id')
            ->get()
            ->filter(static fn (DeliveryCarrierLocality $binding): bool => ($binding->record['name'] ?? null) === $name && ($binding->record['region'] ?? null) === $area)
            ->values();
    }
}
