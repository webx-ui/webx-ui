<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliverySyncRun;
use WebxUi\Delivery\Sync\Directory;
use WebxUi\Delivery\Sync\Synchronizer;
use WebxUi\Delivery\Sync\Trigger;
use WebxUi\Delivery\Testing\FakeCarrier;

/** «Carrier A» on and registered, its pauses between tries at zero. */
abstract class SyncTestCase extends TestCase
{
    protected FakeCarrier $carrier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carrier = new FakeCarrier(localities: 20, points: 60, pageSize: 25);
        $this->app->make(Carriers::class)->register($this->carrier);
        DeliveryCarrier::query()->create(['key' => FakeCarrier::KEY, 'enabled' => true]);
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('webx-delivery.sync.backoff', [0, 0, 0]);
    }

    protected function sync(Directory $directory): DeliverySyncRun
    {
        return $this->app->make(Synchronizer::class)->run(FakeCarrier::KEY, $directory, Trigger::Command);
    }

    /** Localities, then points — a night of the carrier. */
    protected function night(): DeliverySyncRun
    {
        $localities = $this->sync(Directory::Localities);
        $this->assertSame('succeeded', $localities->status->value, (string) $localities->error);

        return $this->sync(Directory::Points);
    }

    protected function points(): int
    {
        return DB::table('delivery_points')->where('carrier', FakeCarrier::KEY)->count();
    }

    protected function removedPoints(): int
    {
        return DB::table('delivery_points')->where('carrier', FakeCarrier::KEY)->whereNotNull('removed_at')->count();
    }

    /**
     * The statements `$work` sends, transactions aside.
     *
     * @param  callable(): mixed  $work
     */
    protected function queries(callable $work): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $work();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
