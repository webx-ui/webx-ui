<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\MethodOption;
use WebxUi\Delivery\Methods;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\RunStatus;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;
use WebxUi\Delivery\Testing\FakeCarrier;
use WebxUi\Delivery\Tests\Fakes\FakeBranchType;
use WebxUi\Geography\Models\GeoLocality;

/** The size §17 measures by: 30 000 points of «Carrier A» in 1 000 localities. */
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
}
