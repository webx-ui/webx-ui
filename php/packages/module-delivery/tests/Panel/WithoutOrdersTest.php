<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Panel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Blocks\OrderBlocks;

/**
 * A shop without `module-orders` (§11.4, §19): no parcels at all — no tab on an order's card, no
 * routes of it, no tracking command or schedule, no order block — and the section still works.
 */
final class WithoutOrdersTest extends PanelTestCase
{
    #[Test]
    public function there_is_no_tab_and_no_tracking_without_the_orders(): void
    {
        foreach (['orders.show', 'orders.waybill', 'orders.track', 'shipments.cancel', 'shipments.label'] as $name) {
            $this->assertFalse(Route::has('webx.delivery.panel.'.$name), "[{$name}] is there without orders.");
        }

        $this->assertArrayNotHasKey('webx:delivery:track', $this->app->make(Kernel::class)->all());
        $tracking = array_filter(
            $this->app->make(Schedule::class)->events(),
            static fn ($event): bool => str_contains((string) $event->command, 'webx:delivery:track'),
        );
        $this->assertSame([], $tracking);
        $this->assertFalse($this->app->make(OrderBlocks::class)->has('delivery'));

        $this->signIn();
        $this->getJson($this->api('methods'))->assertOk();
    }
}
