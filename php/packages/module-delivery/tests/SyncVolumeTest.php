<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\MethodOption;
use WebxUi\Delivery\Methods;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Streets;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;
use WebxUi\Delivery\Testing\FakeCarrier;
use WebxUi\Delivery\Tests\Fakes\FakeBranchType;
use WebxUi\Geography\Models\GeoLocality;

/** The size §17 measures by: 30 000 points of «Carrier A» in 1 000 localities, 300 000 streets in 5 000. */
final class SyncVolumeTest extends SyncTestCase
{
    #[Test]
    public function thirty_thousand_points_sync_page_by_page_within_the_budget(): void
    {
        $this->carrier->size(localities: 1000, points: 30000, pageSize: 500);
        $sync = $this->app->make(Synchronizer::class);

        $this->assertSame(RunStatus::Succeeded, $this->sync(Directory::Localities)->status);

        $run = $sync->start(FakeCarrier::KEY, Directory::Points, Trigger::Command);
        $costs = [];

        do {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $more = $sync->page($run);
            $costs[] = count(DB::getQueryLog());
            DB::disableQueryLog();
        } while ($more);

        $run->refresh();
        $this->assertSame(RunStatus::Succeeded, $run->status);
        $this->assertSame([60, 30000, 30000, 0], [$run->pages, $run->seen, $run->created, $run->removed]);
        $this->assertSame(30000, $this->points());

        // Every page but the last costs the same, whatever its place in the run: no query a record.
        $pages = array_slice($costs, 0, -1);
        $this->assertLessThanOrEqual(8, max($pages), 'a page of 500 points');
        $this->assertSame([max($pages)], array_values(array_unique($pages)));
        // The last page ends the pass: counts, removal, weights, the journal — a handful more, once.
        $this->assertLessThanOrEqual(max($pages) + 8, end($costs));

        // A town of 30 points weighs its type and its points, by one UPDATE.
        $town = GeoLocality::query()->findOrFail(DeliveryCarrierLocality::query()->where('external_id', 'L1')->value('locality_id'));
        $this->assertSame(50 + 30, $town->weight);
    }

    #[Test]
    public function the_checkout_reads_the_synced_points_without_calling_the_carrier(): void
    {
        $this->app->make(MethodTypes::class)->register(FakeBranchType::class);
        $this->method('carrier-a-branch', 'carrier-a.branch', ['point_kind' => 'branch']);
        $this->night();
        $this->carrier->lock();

        $town = GeoLocality::query()->findOrFail(DeliveryCarrierLocality::query()->where('external_id', 'L1')->value('locality_id'));

        $methods = $this->app->make(Methods::class)->forLocality($this->context($town));

        $this->assertSame(['carrier-a-branch'], array_map(static fn (MethodOption $option): string => $option->method->key, $methods));
    }

    #[Test]
    public function three_hundred_thousand_streets_sync_within_the_budget_and_are_suggested_by_the_index(): void
    {
        $this->carrier->size(localities: 5000, points: 0, pageSize: 500, streets: 300000, streetPageSize: 2000);
        $sync = $this->app->make(Synchronizer::class);

        $this->assertSame(RunStatus::Succeeded, $this->sync(Directory::Localities)->status);

        $run = $sync->start(FakeCarrier::KEY, Directory::Streets, Trigger::Command);
        $costs = [];

        do {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $more = $sync->page($run);
            $costs[] = count(DB::getQueryLog());
            DB::disableQueryLog();
        } while ($more);

        $run->refresh();
        $this->assertSame(RunStatus::Succeeded, $run->status);
        $this->assertSame([150, 300000, 300000, 0], [$run->pages, $run->seen, $run->created, $run->removed]);

        // A page of 2 000: the carrier's localities, the streets already there, one upsert, the journal.
        $pages = array_slice($costs, 0, -1);
        $this->assertLessThanOrEqual(4, max($pages), 'a page of 2 000 streets');
        $this->assertSame([max($pages)], array_values(array_unique($pages)));
        // The last page ends the pass: counts, removal, the streets of every binding by one UPDATE, the warning, the journal.
        $this->assertLessThanOrEqual(max($pages) + 8, end($costs));

        // 2 499 localities with a courier but L0 share the streets: 120 or 121 each.
        $binding = DeliveryCarrierLocality::query()->where('external_id', 'L2')->firstOrFail();
        $this->assertSame(121, $binding->streets);
        $this->assertSame(1, $run->warnings[Synchronizer::NO_STREETS]['count'] ?? null);

        $streets = $this->app->make(Streets::class);
        $town = (int) $binding->locality_id;
        $this->carrier->lock();

        foreach (['', 'exa', 'sample 2', 'o’n'] as $query) {
            $this->assertSame(2, $this->queries(static fn () => $streets->forCarrier(FakeCarrier::KEY, $town, $query)), "suggestions for [{$query}]");
            $this->assertLessThanOrEqual(2, $this->queries(static fn () => $streets->forStore($town, $query)));
        }

        $this->assertCount(10, $streets->forCarrier(FakeCarrier::KEY, $town, ''));
        $this->assertSame('S0', $streets->find(FakeCarrier::KEY, $town, 'S0')?->streetId);
    }
}
