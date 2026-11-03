<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\MethodOption;
use WebxUi\Delivery\Methods;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Selection;
use WebxUi\Delivery\SelectionInput;
use WebxUi\Delivery\StreetRef;
use WebxUi\Delivery\Streets;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\InvalidRecord;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\StreetPages;
use WebxUi\Delivery\Sync\StreetRecord;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;
use WebxUi\Delivery\Testing\FakeCarrier;
use WebxUi\Delivery\Tests\Fakes\FakeCourierType;
use WebxUi\Delivery\Types\StoreCourier;
use WebxUi\Geography\Models\GeoLocality;

/**
 * The third directory (§7, §8): the streets of «Carrier A» page by page, removed with the
 * threshold, counted on its bindings; its courier takes a street of the directory only, the
 * shop's courier any text with the carriers' streets as suggestions — and the step never pulls.
 *
 * 90 streets in pages of 40, ten in each locality with a courier but `L0`, which has a courier and
 * no street.
 */
final class StreetsTest extends SyncTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->carrier->size(streets: 90, streetPageSize: 40);
    }

    #[Test]
    public function streets_sync_page_by_page_and_each_binding_counts_its_own(): void
    {
        $this->sync(Directory::Localities);
        $run = $this->sync(Directory::Streets);

        $this->assertSame(RunStatus::Succeeded, $run->status);
        $this->assertSame([3, 90, 90, 0], [$run->pages, $run->seen, $run->created, $run->updated]);
        $this->assertSame(90, DB::table('delivery_streets')->count());
        $this->assertSame(10, $this->binding('L2')->streets);
        $this->assertSame(0, $this->binding('L1')->streets);

        // L0: the carrier goes to doors there and gave no street — its directory is wrong, the run says so.
        $this->assertSame(0, $this->binding('L0')->streets);
        $this->assertSame(['count' => 1, 'ids' => ['L0']], $run->warnings[Synchronizer::NO_STREETS] ?? null);

        $street = DB::table('delivery_streets')->where('external_id', 'S7')->first();
        $this->assertSame('o\'neil 7', $street->name_key, 'the typographic apostrophe is normalised');
        $this->assertSame('avenue', $street->kind);
    }

    #[Test]
    public function a_page_written_twice_makes_no_second_row(): void
    {
        $this->sync(Directory::Localities);
        $this->sync(Directory::Streets);
        $first = DB::table('delivery_streets')->orderBy('id')->pluck('id', 'external_id')->all();

        $again = $this->sync(Directory::Streets);

        $this->assertSame([90, 0, 90], [$again->seen, $again->created, $again->updated]);
        $this->assertSame($first, DB::table('delivery_streets')->orderBy('id')->pluck('id', 'external_id')->all());
        $this->assertSame(10, $this->binding('L2')->streets);
    }

    #[Test]
    public function a_failed_page_is_tried_again_from_its_own_cursor(): void
    {
        $this->sync(Directory::Localities);
        $this->carrier->failOnPage(Directory::Streets, 2, 2);
        $this->carrier->pulls = [];

        $run = $this->sync(Directory::Streets);

        $this->assertSame(RunStatus::Succeeded, $run->status);
        $this->assertSame([null, '40', '40', '40', '80'], array_column($this->carrier->pulls, 'cursor'));
        $this->assertSame(90, DB::table('delivery_streets')->count());
    }

    #[Test]
    public function vanished_streets_are_removed_and_leave_the_count_and_the_suggestions(): void
    {
        $this->sync(Directory::Localities);
        $this->sync(Directory::Streets);
        // S0 and S9 are streets of L2: 2 of 90 is under the threshold.
        $this->carrier->drop(Directory::Streets, [0, 9]);

        $run = $this->sync(Directory::Streets);

        $this->assertSame([RunStatus::Succeeded, 2], [$run->status, $run->removed]);
        $this->assertSame(8, $this->binding('L2')->streets);
        $this->assertSame(2, DB::table('delivery_streets')->whereNotNull('removed_at')->count());

        $town = $this->townOf('L2');
        $streets = $this->app->make(Streets::class);
        $this->assertSame([], $streets->forCarrier(FakeCarrier::KEY, $town, 'Example 0'));
        $this->assertNull($streets->find(FakeCarrier::KEY, $town, 'S0'));

        // Back the next time: in force again, counted again.
        $this->carrier->drop(Directory::Streets, []);
        $this->sync(Directory::Streets);
        $this->assertSame(10, $this->binding('L2')->streets);
        $this->assertNotNull($streets->find(FakeCarrier::KEY, $town, 'S0'));
    }

    #[Test]
    public function past_the_threshold_the_streets_are_held_and_points_and_localities_stay(): void
    {
        $this->night();
        $this->sync(Directory::Streets);
        $this->carrier->returnNothing(Directory::Streets);

        $run = $this->sync(Directory::Streets);

        $this->assertSame(RunStatus::Held, $run->status);
        $this->assertSame(90, $run->held());
        $this->assertSame(0, DB::table('delivery_streets')->whereNotNull('removed_at')->count());
        $this->assertSame(10, $this->binding('L2')->streets);
        $this->assertSame(0, $this->removedPoints());
        $this->assertSame(0, DeliveryCarrierLocality::query()->whereNotNull('removed_at')->count());

        $this->app->make(Synchronizer::class)->confirm($run);

        $this->assertSame(90, DB::table('delivery_streets')->whereNotNull('removed_at')->count());
        $this->assertSame(0, $this->binding('L2')->refresh()->streets);
        $this->assertSame(0, $this->removedPoints());
    }

    #[Test]
    public function a_failed_streets_sync_leaves_the_last_complete_directory(): void
    {
        $this->sync(Directory::Localities);
        $this->sync(Directory::Streets);
        $this->carrier->drop(Directory::Streets, [0]);
        $this->carrier->failOnPage(Directory::Streets, 3);

        $run = $this->sync(Directory::Streets);

        $this->assertSame(RunStatus::Failed, $run->status);
        $this->assertSame(0, DB::table('delivery_streets')->whereNotNull('removed_at')->count());
        $this->assertSame(10, $this->binding('L2')->streets);
        $this->assertSame(['S0', 'S72', 'S54', 'S45', 'S27', 'S63', 'S18', 'S36', 'S81', 'S9'], $this->ids($this->app->make(Streets::class)->forCarrier(FakeCarrier::KEY, $this->townOf('L2'), '')));
    }

    #[Test]
    public function a_removed_locality_takes_its_streets_and_their_count_along(): void
    {
        $this->sync(Directory::Localities);
        $this->sync(Directory::Streets);
        $this->carrier->drop(Directory::Localities, [2]);

        $this->sync(Directory::Localities);

        $this->assertNotNull($this->binding('L2')->removed_at);
        $this->assertSame(0, $this->binding('L2')->streets);
        $this->assertSame(10, DB::table('delivery_streets')->whereNotNull('removed_at')->count());
    }

    #[Test]
    public function a_street_of_an_unknown_locality_is_skipped_and_said(): void
    {
        $this->sync(Directory::Localities);
        $run = $this->app->make(Synchronizer::class)->start(FakeCarrier::KEY, Directory::Streets, Trigger::Command);

        $result = $this->app->make(StreetPages::class)->write(FakeCarrier::KEY, $run->id, [
            new StreetRecord('S-1', 'L-nowhere', 'Example'),
            new StreetRecord('S-2', 'L2', 'Example'),
            new StreetRecord('S-2', 'L2', 'Example'),
        ]);

        $this->assertSame([2, 1, [StreetPages::UNKNOWN_LOCALITY => ['S-1']]], [$result->seen, $result->created, $result->skipped]);

        $this->expectException(InvalidRecord::class);
        $this->app->make(StreetPages::class)->write(FakeCarrier::KEY, $run->id, [new StreetRecord('S-3', 'L2', ' ')]);
    }

    #[Test]
    public function the_streets_stand_on_the_localities_and_not_on_the_points(): void
    {
        $this->carrier->failOnPage(Directory::Points, 1);

        $this->artisan('webx:delivery:sync', ['carrier' => FakeCarrier::KEY])
            ->expectsOutputToContain('carrier-a points: failed')
            ->expectsOutputToContain('carrier-a streets: succeeded — 3 pages, 90 seen, 90 new')
            ->assertFailed();

        $this->carrier->failOnPage(Directory::Localities, 1);

        $this->artisan('webx:delivery:sync', ['carrier' => FakeCarrier::KEY])
            ->expectsOutputToContain('points, streets not synced — they follow a successful localities')
            ->assertFailed();
    }

    #[Test]
    public function suggestions_are_a_prefix_inside_the_locality_in_the_buyers_language(): void
    {
        $this->sync(Directory::Localities);
        $this->sync(Directory::Streets);
        $streets = $this->app->make(Streets::class);
        $town = $this->townOf('L2');

        $this->assertSame(['S0', 'S72', 'S54', 'S45', 'S27', 'S63', 'S18', 'S36', 'S81', 'S9'], $this->ids($streets->forCarrier(FakeCarrier::KEY, $town, '')));
        $this->assertSame(['S63'], $this->ids($streets->forCarrier(FakeCarrier::KEY, $town, 'o’ne')));
        $this->assertSame(['S81', 'S9'], $this->ids($streets->forCarrier(FakeCarrier::KEY, $town, 'SAMPLE')));
        $this->assertSame([], $streets->forCarrier(FakeCarrier::KEY, $town, 'ample'), 'a prefix, not a part');
        $this->assertSame([], $streets->forCarrier(FakeCarrier::KEY, $this->townOf('L4'), 'Sample 9'), 'another locality');

        $this->assertSame('Sample 9 square', $streets->find(FakeCarrier::KEY, $town, 'S9')?->name);
        $this->assertSame('pl. Sample 9', $streets->find(FakeCarrier::KEY, $town, 'S9', 'pl')?->name);
        $this->assertSame('Oak 18 lane', $streets->find(FakeCarrier::KEY, $town, 'S18')?->name);
    }

    #[Test]
    public function the_carriers_courier_takes_a_street_of_its_directory_in_the_locality_only(): void
    {
        [$type, $method] = $this->courier();
        $this->sync(Directory::Localities);
        $this->sync(Directory::Streets);
        $context = $this->context(GeoLocality::query()->findOrFail($this->townOf('L2')));

        $this->assertSame(
            ['street' => 'Choose the street from the list.', 'house' => 'Enter the house number.'],
            $type->validate($method, $context, new SelectionInput(street: 'Typed street')),
        );
        // A street of another locality of the carrier, or one that is not there at all.
        foreach (['S1', 'S-none'] as $id) {
            $this->assertSame(
                ['street' => 'Choose the street from the list.'],
                $type->validate($method, $context, new SelectionInput(street: 'Example 1', streetId: $id, house: '3')),
            );
        }

        $selection = $type->validate($method, $context, new SelectionInput(street: 'Whatever was typed', streetId: 'S9', house: '3'));

        $this->assertInstanceOf(Selection::class, $selection);
        $this->assertSame(['Sample 9 square', 'S9', '3'], [$selection->address?->street, $selection->address->streetId, $selection->address->house]);

        // Removed since the buyer chose it: caught again.
        $this->carrier->drop(Directory::Streets, [9]);
        $this->sync(Directory::Streets);
        $this->assertSame(
            ['street' => 'Choose the street from the list.'],
            $type->validate($method, $context, new SelectionInput(streetId: 'S9', house: '3')),
        );
    }

    #[Test]
    public function without_streets_the_carriers_courier_is_shown_disabled_and_refused(): void
    {
        [$type, $method] = $this->courier();
        $this->sync(Directory::Localities);
        $this->sync(Directory::Streets);
        $town = GeoLocality::query()->findOrFail($this->townOf('L0'));

        [$option] = $this->app->make(Methods::class)->forLocality($this->context($town));

        $this->assertSame(['carrier-a-courier', true], [$option->method->key, $option->disabled]);
        $this->assertSame('Courier delivery is not available here, choose another method', $option->hint);

        foreach ([new SelectionInput(street: 'Typed', house: '1'), new SelectionInput(streetId: 'S9', house: '1')] as $input) {
            $this->assertSame(
                ['method' => 'Courier delivery is not available here, choose another method'],
                $type->validate($method, $this->context($town), $input),
            );
        }

        // Where the streets are, the same courier is offered and can be chosen.
        [$offered] = $this->app->make(Methods::class)->forLocality($this->context(GeoLocality::query()->findOrFail($this->townOf('L2'))));
        $this->assertFalse($offered->disabled);
        $this->assertNotSame(MethodOption::NO_STREETS, $offered->unavailable);
    }

    #[Test]
    public function the_shops_courier_takes_any_text_and_suggests_the_carriers_streets_of_the_town(): void
    {
        $this->sync(Directory::Localities);
        $this->sync(Directory::Streets);
        $town = $this->townOf('L2');
        // A second carrier knows the same town and one street of it under the same name.
        $this->secondCarrierStreet($town, 'Sample 9', 'X-9', 'square');
        $this->secondCarrierStreet($town, 'Sample 99', 'X-99');

        $streets = $this->app->make(Streets::class);
        $suggested = $streets->forStore($town, 'sample');

        $this->assertSame(['Sample 81 street', 'Sample 9 square', 'Sample 99 street'], array_map(static fn (StreetRef $street): string => $street->name, $suggested));
        $this->assertSame([null, null, null], $this->ids($suggested), 'text only: another carrier\'s id means nothing to the shop');
        $this->assertSame([], $streets->forStore($town, ''));
        $this->assertSame([], $streets->forStore($this->townOf('L1'), 'sample'), 'no street of any carrier — just the field');

        $method = Method::of($this->method('our-courier', StoreCourier::KEY));
        $type = $this->app->make(MethodTypes::class)->get(StoreCourier::KEY);
        $before = [DB::table('delivery_streets')->count(), DB::table('geo_localities')->count(), DB::table('geo_locality_names')->count(), DB::table('geo_regions')->count()];

        $selection = $type->validate($method, $this->context(GeoLocality::query()->findOrFail($town)), new SelectionInput(street: 'A street no directory has', streetId: 'S9', house: '5'));

        $this->assertInstanceOf(Selection::class, $selection);
        $this->assertSame(['A street no directory has', null], [$selection->address?->street, $selection->address->streetId]);
        $this->assertSame($before, [DB::table('delivery_streets')->count(), DB::table('geo_localities')->count(), DB::table('geo_locality_names')->count(), DB::table('geo_regions')->count()]);
        $this->assertSame(0, DB::table('delivery_streets')->where('names', 'like', '%no directory%')->count());
    }

    #[Test]
    public function no_request_of_the_step_pulls_a_directory_or_queues_a_job(): void
    {
        [$type, $method] = $this->courier();
        $own = Method::of($this->method('our-courier', StoreCourier::KEY));
        $this->night();
        $this->sync(Directory::Streets);
        $town = GeoLocality::query()->findOrFail($this->townOf('L2'));

        $this->carrier->lock();
        $this->carrier->pulls = [];
        Bus::fake();
        Queue::fake();

        $streets = $this->app->make(Streets::class);
        $this->app->make(Methods::class)->forLocality($this->context($town));
        $streets->forCarrier(FakeCarrier::KEY, $town->id, 'exa');
        $streets->forStore($town->id, 'exa');
        $type->validate($method, $this->context($town), new SelectionInput(streetId: 'S0', house: '1'));
        $this->app->make(MethodTypes::class)->get(StoreCourier::KEY)->validate($own, $this->context($town), new SelectionInput(street: 'Any', house: '1'));

        $this->assertSame([], $this->carrier->pulls);
        Bus::assertNothingDispatched();
        Queue::assertNothingPushed();
    }

    #[Test]
    public function suggestions_and_the_check_cost_a_fixed_number_of_queries(): void
    {
        [$type, $method] = $this->courier();
        $this->sync(Directory::Localities);
        $this->sync(Directory::Streets);
        $town = $this->townOf('L2');
        $streets = $this->app->make(Streets::class);
        $context = $this->context(GeoLocality::query()->findOrFail($town));

        $this->assertSame(2, $this->queries(static fn () => $streets->forCarrier(FakeCarrier::KEY, $town, 'exa')));
        $this->assertSame(2, $this->queries(static fn () => $streets->forStore($town, 'exa')));
        $this->assertSame(1, $this->queries(static fn () => $type->validate($method, $context, new SelectionInput(streetId: 'S0', house: '1'))));
    }

    /** @return array{0: FakeCourierType, 1: Method} */
    private function courier(): array
    {
        $types = $this->app->make(MethodTypes::class);
        $types->register(FakeCourierType::class);

        /** @var FakeCourierType $type */
        $type = $types->get('carrier-a.courier');

        return [$type, Method::of($this->method('carrier-a-courier', 'carrier-a.courier'))];
    }

    private function binding(string $externalId): DeliveryCarrierLocality
    {
        return DeliveryCarrierLocality::query()->where('carrier', FakeCarrier::KEY)->where('external_id', $externalId)->firstOrFail();
    }

    /** The shared locality the carrier's `$externalId` is bound to. */
    private function townOf(string $externalId): int
    {
        return (int) $this->binding($externalId)->locality_id;
    }

    /**
     * @param  list<StreetRef>  $streets
     * @return list<string|null>
     */
    private function ids(array $streets): array
    {
        return array_map(static fn (StreetRef $street): ?string => $street->streetId, $streets);
    }

    private function secondCarrierStreet(int $town, string $name, string $id, string $kind = 'street'): void
    {
        $binding = DB::table('delivery_carrier_localities')->where('carrier', 'carrier-b')->where('locality_id', $town)->value('id')
            ?? DB::table('delivery_carrier_localities')->insertGetId([
                'carrier' => 'carrier-b', 'external_id' => 'B-'.$town, 'locality_id' => $town, 'status' => 'matched',
                'record' => '{}', 'features' => '["courier"]', 'streets' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);

        DB::table('delivery_streets')->insert([
            'carrier' => 'carrier-b', 'carrier_locality_id' => $binding, 'external_id' => $id, 'kind' => $kind,
            'names' => json_encode(['en' => $name]), 'name_key' => mb_strtolower($name), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
