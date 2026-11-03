<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\DeliveryException;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Models\DeliverySyncRun;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\InvalidRecord;
use WebxUi\Delivery\Sync\LocalityPages;
use WebxUi\Delivery\Sync\PointPages;
use WebxUi\Delivery\Sync\PointRecord;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\StoredLeadingCarriers;
use WebxUi\Delivery\Sync\SyncAlreadyRunning;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;
use WebxUi\Delivery\Testing\FakeCarrier;
use WebxUi\Geography\Editor;
use WebxUi\Geography\Matching\LocalityRecord;
use WebxUi\Geography\Models\GeoLocality;

final class SyncTest extends SyncTestCase
{
    #[Test]
    public function the_leading_carrier_brings_its_localities_and_points_into_an_empty_site(): void
    {
        $localities = $this->sync(Directory::Localities);

        $this->assertSame(RunStatus::Succeeded, $localities->status);
        $this->assertSame([1, 22, 22, 0, 0, 0], [$localities->pages, $localities->seen, $localities->created, $localities->updated, $localities->removed, $localities->queued]);
        $this->assertSame(22, DeliveryCarrierLocality::query()->where('status', 'matched')->whereNotNull('locality_id')->count());
        // The namesakes are two villages of two regions, not one.
        $this->assertSame(2, GeoLocality::query()->where('kind', 'village')->count());

        $points = $this->sync(Directory::Points);

        $this->assertSame(RunStatus::Succeeded, $points->status);
        $this->assertSame([3, 60, 60], [$points->pages, $points->seen, $points->created]);
        $this->assertSame(60, $this->points());
        $this->assertSame(0, DeliveryPoint::query()->where('carrier', FakeCarrier::KEY)->whereNull('locality_id')->count());

        $point = DeliveryPoint::query()->where('external_id', 'P4')->firstOrFail();
        $this->assertSame('branch', $point->kind);
        $this->assertSame('L4', DeliveryCarrierLocality::query()->findOrFail($point->carrier_locality_id)->external_id);
        $this->assertSame(['en' => 'Branch 4'], $point->titles);
        $this->assertSame(['en' => '4 Example street'], $point->address);
        $this->assertSame('4 branch 4 4 example street', $point->search);
        $this->assertSame(['09:00', '18:00'], $point->schedule['week']['mon'][0] ?? null);
        $this->assertSame(['max_weight_g' => 30000, 'cod' => true], $point->limits);
        $this->assertSame('+800 1234 5678', $point->phone);
    }

    #[Test]
    public function a_town_made_by_hand_is_bound_to_and_not_duplicated(): void
    {
        $this->region('Region A');
        $town = $this->app->make(Editor::class)->createLocality([
            'country' => 'XX',
            'region_id' => $this->region('Region A')->id,
            'kind' => 'city',
            'names' => ['en' => 'Town 0'],
        ]);

        $this->night();

        $binding = DeliveryCarrierLocality::query()->where('external_id', 'L0')->firstOrFail();
        $this->assertSame($town->id, $binding->locality_id);
        $this->assertSame('auto_exact', $binding->method);
        $this->assertSame(1, GeoLocality::query()->get()->filter(fn (GeoLocality $locality): bool => $locality->name('en') === 'Town 0')->count());
        $this->assertSame('manual', $town->refresh()->created_by);
        // L0 has the points P0, P20, P40 — the checkout sees them in the hand-made town.
        $this->assertSame(3, DeliveryPoint::query()->where('locality_id', $town->id)->count());
    }

    #[Test]
    public function the_first_carrier_to_sync_a_country_leads_it_in_every_process(): void
    {
        $this->sync(Directory::Localities);

        $this->assertSame(FakeCarrier::KEY, (new StoredLeadingCarriers($this->app['config']))->leader('XX'));
        $this->assertSame(FakeCarrier::KEY, (new StoredLeadingCarriers($this->app['config']))->claim('XX', 'carrier-b'));
    }

    #[Test]
    public function a_page_written_twice_makes_no_second_row(): void
    {
        $this->night();
        $first = DB::table('delivery_points')->orderBy('id')->pluck('id', 'external_id')->all();

        $again = $this->night();

        $this->assertSame([60, 0, 60], [$again->seen, $again->created, $again->updated]);
        $this->assertSame($first, DB::table('delivery_points')->orderBy('id')->pluck('id', 'external_id')->all());
        $this->assertSame(22, DeliveryCarrierLocality::query()->count());
        $this->assertSame(22, GeoLocality::query()->count());
    }

    #[Test]
    public function a_failed_page_is_tried_again_from_its_own_cursor(): void
    {
        $this->sync(Directory::Localities);
        $this->carrier->failOnPage(Directory::Points, 2, 2);
        $this->carrier->pulls = [];

        $run = $this->sync(Directory::Points);

        $this->assertSame(RunStatus::Succeeded, $run->status);
        $this->assertSame([null, '25', '25', '25', '50'], array_column($this->carrier->pulls, 'cursor'));
        $this->assertSame([3, 60, 60], [$run->pages, $run->seen, $this->points()]);
    }

    #[Test]
    public function an_empty_answer_is_held_and_removes_nothing_until_confirmed(): void
    {
        $this->night();
        $this->carrier->returnNothing(Directory::Points);

        $run = $this->sync(Directory::Points);

        $this->assertSame(RunStatus::Held, $run->status);
        $this->assertSame(60, $run->held());
        $this->assertSame(0, $run->removed);
        $this->assertSame(0, $this->removedPoints());

        $confirmed = $this->app->make(Synchronizer::class)->confirm($run);

        $this->assertSame(RunStatus::Succeeded, $confirmed->status);
        $this->assertSame(60, $confirmed->removed);
        $this->assertSame(60, $this->removedPoints());
    }

    #[Test]
    public function only_the_latest_held_run_can_be_confirmed(): void
    {
        $this->night();
        $this->carrier->returnNothing(Directory::Points);
        $held = $this->sync(Directory::Points);
        $this->carrier->returnNothing(Directory::Points, false);
        $this->sync(Directory::Points);

        $this->expectException(DeliveryException::class);
        $this->app->make(Synchronizer::class)->confirm($held->refresh());
    }

    #[Test]
    public function what_vanished_within_the_threshold_is_removed_and_comes_back(): void
    {
        $this->night();
        // 6 of 60 is the threshold itself, 10 %: removed.
        $this->carrier->drop(Directory::Points, [1, 2, 3, 4, 5, 6]);

        $run = $this->sync(Directory::Points);

        $this->assertSame([RunStatus::Succeeded, 6], [$run->status, $run->removed]);
        $this->assertSame(6, $this->removedPoints());
        $this->assertSame(60, $this->points(), 'removed, not deleted: carts and shipments name them');

        $this->carrier->drop(Directory::Points, []);
        $this->sync(Directory::Points);

        $this->assertSame(0, $this->removedPoints());
    }

    #[Test]
    public function past_the_threshold_the_run_is_held(): void
    {
        $this->night();
        $this->carrier->drop(Directory::Points, range(0, 6));

        $run = $this->sync(Directory::Points);

        $this->assertSame([RunStatus::Held, 7, 0], [$run->status, $run->held(), $this->removedPoints()]);
    }

    #[Test]
    public function the_threshold_is_a_setting(): void
    {
        $this->app['config']->set('webx-delivery.sync.max_removal_share', 0.5);
        $this->night();
        $this->carrier->drop(Directory::Points, range(0, 20));

        $this->assertSame(RunStatus::Succeeded, $this->sync(Directory::Points)->status);
        $this->assertSame(21, $this->removedPoints());
    }

    #[Test]
    public function a_failure_in_the_middle_removes_nothing_and_the_last_directory_stays(): void
    {
        $this->night();
        $this->carrier->drop(Directory::Points, [0, 1, 2]);
        $this->carrier->failOnPage(Directory::Points, 2);
        $this->carrier->pulls = [];

        $run = $this->sync(Directory::Points);

        // Three tries of page 2, none got through.
        $this->assertSame([null, '25', '25', '25'], array_column($this->carrier->pulls, 'cursor'));

        $this->assertSame(RunStatus::Failed, $run->status);
        $this->assertSame('The carrier did not answer', $run->error);
        $this->assertSame(1, $run->pages);
        $this->assertNotNull($run->finished_at);
        $this->assertSame(0, $this->removedPoints());
        $this->assertSame(60, DeliveryPoint::query()->where('carrier', FakeCarrier::KEY)->where('active', true)->whereNull('removed_at')->count());

    }

    #[Test]
    public function a_removed_locality_takes_its_points_along(): void
    {
        $this->night();
        $this->carrier->drop(Directory::Localities, [0]);

        $run = $this->sync(Directory::Localities);

        $this->assertSame([RunStatus::Succeeded, 1], [$run->status, $run->removed]);
        $this->assertNotNull(DeliveryCarrierLocality::query()->where('external_id', 'L0')->value('removed_at'));
        $this->assertSame(3, $this->removedPoints());
    }

    #[Test]
    public function the_shops_own_points_are_never_touched(): void
    {
        $store = $this->point($this->town('Town Z'));
        $this->night();
        $this->carrier->returnNothing(Directory::Points);
        $this->app->make(Synchronizer::class)->confirm($this->sync(Directory::Points));

        $this->assertNull($store->refresh()->removed_at);
        $this->expectException(DeliveryException::class);
        $this->app->make(Synchronizer::class)->start('store', Directory::Points, Trigger::Command);
    }

    #[Test]
    public function a_points_page_of_a_following_carrier_waits_for_the_binding_and_follows_it(): void
    {
        // Another carrier leads the country: «Carrier A» binds, it does not create.
        $this->app['config']->set('webx-geography.countries.XX.source', 'carrier-b');

        $localities = $this->sync(Directory::Localities);
        $this->assertSame(22, $localities->queued);
        $this->sync(Directory::Points);
        $this->assertSame(60, DeliveryPoint::query()->where('carrier', FakeCarrier::KEY)->whereNull('locality_id')->count());

        // A person makes the region and the town by hand; the next pass binds L0 and its points follow.
        $town = $this->app->make(Editor::class)->createLocality([
            'country' => 'XX',
            'region_id' => $this->region('Region A')->id,
            'kind' => 'city',
            'names' => ['en' => 'Town 0'],
        ]);
        $this->sync(Directory::Localities);

        $this->assertSame($town->id, DeliveryCarrierLocality::query()->where('external_id', 'L0')->value('locality_id'));
        $this->assertSame(3, DeliveryPoint::query()->where('locality_id', $town->id)->count());
    }

    #[Test]
    public function a_point_of_a_locality_the_carrier_never_gave_is_skipped_and_said(): void
    {
        $this->carrier->namesakes = false;
        $this->carrier->size(localities: 20);
        $this->sync(Directory::Localities);
        $this->carrier->size(localities: 30);

        $run = $this->sync(Directory::Points);

        // P20…P29 and P50…P59 stand in L20…L29, which the localities pass did not have.
        $this->assertSame(40, $this->points());
        $this->assertSame(20, $run->warnings['skipped']['unknown_locality']['count'] ?? null);
        $this->assertContains('P20', $run->warnings['skipped']['unknown_locality']['ids'] ?? []);
    }

    #[Test]
    public function the_weight_of_a_locality_counts_its_points_unless_set_by_hand(): void
    {
        $heavy = $this->app->make(Editor::class)->createLocality([
            'country' => 'XX',
            'region_id' => $this->region('Region B')->id,
            'kind' => 'town',
            'names' => ['en' => 'Town 1'],
            'weight' => 7,
        ]);

        $this->night();

        $city = GeoLocality::query()->findOrFail(DeliveryCarrierLocality::query()->where('external_id', 'L0')->value('locality_id'));
        // A city weighs 100, and L0 has three active points.
        $this->assertSame(103, $city->weight);
        $this->assertSame(7, $heavy->refresh()->weight);
    }

    #[Test]
    public function a_locality_without_coordinates_lies_where_its_points_are_unless_a_person_said_otherwise(): void
    {
        $this->night();
        $town = static fn (string $id): GeoLocality => GeoLocality::query()->findOrFail(DeliveryCarrierLocality::query()->where('external_id', $id)->value('locality_id'));
        // Towns of a carrier that gives no coordinates; L1 was cleared by hand, L2 is a town the
        // site made itself.
        GeoLocality::query()->whereKey([$town('L0')->id, $town('L1')->id, $town('L2')->id])->update(['lat' => null, 'lng' => null]);
        $town('L1')->forceFill(['manual_fields' => ['lat', 'lng']])->save();
        $town('L2')->forceFill(['created_by' => GeoLocality::MANUAL])->save();
        $kept = [$town('L3')->lat, $town('L3')->lng];

        $this->sync(Directory::Points);

        // L0 has P0, P20 and P40: 20.00, 20.20 and 20.40 east, all at 10 north.
        $this->assertSame([10.0, 20.2], [$town('L0')->lat, $town('L0')->lng]);
        $this->assertSame([null, null], [$town('L1')->lat, $town('L1')->lng]);
        $this->assertSame([null, null], [$town('L2')->lat, $town('L2')->lng]);
        // A town that has coordinates keeps its own.
        $this->assertSame($kept, [$town('L3')->lat, $town('L3')->lng]);
    }

    #[Test]
    public function a_point_for_some_people_only_keeps_its_restriction_and_a_wrong_word_breaks_the_contract(): void
    {
        $run = $this->night();
        $pages = $this->app->make(PointPages::class);

        $pages->write(FakeCarrier::KEY, $run->id, [new PointRecord(externalId: 'P0', kind: 'locker', localityExternalId: 'L0', address: '0 Example street', restriction: 'residents')]);
        $this->assertSame('residents', DB::table('delivery_points')->where('external_id', 'P0')->value('restriction'));

        $pages->write(FakeCarrier::KEY, $run->id, [new PointRecord(externalId: 'P0', kind: 'locker', localityExternalId: 'L0', address: '0 Example street')]);
        $this->assertNull(DB::table('delivery_points')->where('external_id', 'P0')->value('restriction'));

        $this->expectException(InvalidRecord::class);
        $pages->write(FakeCarrier::KEY, $run->id, [new PointRecord(externalId: 'P0', kind: 'locker', localityExternalId: 'L0', address: '0 Example street', restriction: 'For residents')]);
    }

    #[Test]
    public function a_second_run_of_a_directory_does_not_start(): void
    {
        $sync = $this->app->make(Synchronizer::class);
        $running = $sync->start(FakeCarrier::KEY, Directory::Points, Trigger::Panel);

        try {
            $sync->start(FakeCarrier::KEY, Directory::Points, Trigger::Mcp);
            $this->fail('A second run started.');
        } catch (SyncAlreadyRunning $refusal) {
            $this->assertSame('Already running, page 1', $refusal->getMessage());
        }

        // A run silent for hours is dead: it is closed, and the new one starts.
        $this->travel(7)->hours();
        $next = $sync->start(FakeCarrier::KEY, Directory::Points, Trigger::Mcp);

        $this->assertSame(RunStatus::Failed, $running->refresh()->status);
        $this->assertSame('The sync was interrupted', $running->error);
        $this->assertTrue($next->isRunning());
    }

    #[Test]
    public function a_carrier_that_is_off_does_not_sync(): void
    {
        DeliveryCarrier::query()->whereKey(FakeCarrier::KEY)->update(['enabled' => false]);

        $this->expectException(DeliveryException::class);
        $this->sync(Directory::Localities);
    }

    #[Test]
    public function a_record_that_breaks_the_contract_fails_the_run_as_a_bad_response(): void
    {
        $this->expectException(InvalidRecord::class);

        $this->app->make(PointPages::class)->write(FakeCarrier::KEY, 1, [
            new PointRecord(externalId: 'P1', kind: 'Branch Office', localityExternalId: 'L1', address: '1 Example street'),
        ]);
    }

    #[Test]
    public function the_journal_keeps_the_last_hundred_runs_of_a_directory(): void
    {
        $this->sync(Directory::Localities);
        DeliverySyncRun::query()->insert(array_fill(0, 120, [
            'carrier' => FakeCarrier::KEY, 'directory' => 'points', 'status' => 'succeeded', 'trigger' => 'schedule',
            'started_at' => now(), 'finished_at' => now(),
        ]));

        $this->sync(Directory::Points);

        $this->assertSame(Synchronizer::KEEP_RUNS, DeliverySyncRun::query()->where('directory', 'points')->count());
        $this->assertSame(1, DeliverySyncRun::query()->where('directory', 'localities')->count());
    }

    #[Test]
    public function a_page_costs_a_fixed_number_of_queries(): void
    {
        $this->carrier->size(localities: 1000, points: 1000, pageSize: 500);
        $sync = $this->app->make(Synchronizer::class);

        // The first pass over towns the site has not seen: the leader makes them, which is
        // writing, not reading — measured is the pass after it, every town known.
        $this->night();
        $localities = $sync->start(FakeCarrier::KEY, Directory::Localities, Trigger::Command);
        $points = $sync->start(FakeCarrier::KEY, Directory::Points, Trigger::Command);

        // A page of 500 localities (§17: ≤ 8): the bindings, the matcher, the upsert, the run.
        $this->assertLessThanOrEqual(8, $this->queries(static fn (): bool => $sync->page($localities)));
        $this->assertSame(500, $localities->refresh()->seen);
        // A page of 500 points: the carrier's localities, the points there, the upsert, the run.
        $this->assertLessThanOrEqual(8, $this->queries(static fn (): bool => $sync->page($points)));
        $this->assertSame(500, $points->refresh()->seen);
    }

    public function test_a_town_a_stored_binding_holds_is_no_candidate_for_a_namesake_on_a_later_page(): void
    {
        // A carrier never lists one town twice (§4.3, decision of 02.11.2026): the second village
        // of a name, a page later, is a town of its own for the leading carrier, and the queue for
        // the others — never the first village again.
        $pages = $this->app->make(LocalityPages::class);
        $run = static fn (string $carrier): int => (int) DB::table('delivery_sync_runs')->insertGetId([
            'carrier' => $carrier, 'directory' => 'localities', 'status' => 'running', 'trigger' => 'command',
            'started_at' => now(),
        ]);
        $village = static fn (string $id): LocalityRecord => new LocalityRecord($id, 'XX', 'Village N', 'village', 'Region A');

        $pages->write(FakeCarrier::KEY, $run(FakeCarrier::KEY), [$village('1')]);
        $pages->write(FakeCarrier::KEY, $run(FakeCarrier::KEY), [$village('2')]);
        $bound = DB::table('delivery_carrier_localities')->where('carrier', FakeCarrier::KEY)->orderBy('external_id')->pluck('locality_id', 'external_id')->all();

        $this->assertCount(2, array_unique($bound));
        $this->assertNotContains(null, $bound);

        // Another carrier: record 7 takes one village, record 8 cannot tell the other from it.
        $pages->write('carrier-x', $run('carrier-x'), [new LocalityRecord('7', 'XX', 'Village N', 'village', 'Region A')]);
        $other = DB::table('delivery_carrier_localities')->where('carrier', 'carrier-x')->first();
        $this->assertSame('pending', $other->status);
        $this->assertSame('ambiguous', $other->reason);
    }
}
