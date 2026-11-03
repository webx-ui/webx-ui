<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Shipping;

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\DeliverySnapshot;
use WebxUi\Delivery\Events\ShipmentCreated;
use WebxUi\Delivery\LocalityRef;
use WebxUi\Delivery\Method;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Models\DeliveryShipment;
use WebxUi\Delivery\Quote;
use WebxUi\Delivery\Selection;
use WebxUi\Delivery\Shipping\Shipments;
use WebxUi\Delivery\Shipping\ShipmentStatus;
use WebxUi\Delivery\Shipping\WaybillRefused;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\Orders;

/**
 * The parcels of orders on «carrier A» (§11): the snapshot the order keeps, the waybill with the
 * payer of §9.3, the tracking the schedule asks for, «received» as the order's milestone — once —
 * and a refusal or a return as a line of its history.
 */
final class ShipmentsTest extends ShippingTestCase
{
    private function shipments(): Shipments
    {
        return $this->app->make(Shipments::class);
    }

    #[Test]
    public function the_snapshot_says_who_pays_the_carrier_as_the_table_of_9_3(): void
    {
        $method = Method::of(DeliveryMethod::query()->where('key', 'carrier-a-branch')->firstOrFail());
        $selection = new Selection($method, LocalityRef::of($this->townA->load('region')));
        $snapshot = static fn (Quote $quote): array => DeliverySnapshot::of($selection, $quote, 'EUR')->toArray();

        $fixed = $snapshot(Quote::fixed(900));
        $this->assertSame(['kind' => 'fixed', 'amount' => 900, 'was' => null, 'estimate' => null, 'currency' => 'EUR', 'in_total' => true], $fixed['price']);
        $this->assertSame('sender', $fixed['payer']);

        $byStore = $snapshot(Quote::fixed(900)->paidByStore());
        $this->assertSame(0, $byStore['price']['amount']);
        $this->assertSame(900, $byStore['price']['was']);
        $this->assertTrue($byStore['paid_by_store']);
        $this->assertSame('sender', $byStore['payer']);

        $free = $snapshot(Quote::free());
        $this->assertFalse($free['paid_by_store']);
        $this->assertSame('sender', $free['payer']);

        $rates = $snapshot(Quote::carrierRates(7000));
        $this->assertSame(['kind' => 'carrier_rates', 'amount' => null, 'was' => null, 'estimate' => 7000, 'currency' => 'EUR', 'in_total' => false], $rates['price']);
        $this->assertSame('recipient', $rates['payer']);
    }

    #[Test]
    public function the_order_keeps_its_snapshot_when_the_method_changes_afterwards(): void
    {
        $order = $this->branchOrder(900);
        $before = Order::query()->findOrFail($order->id)->delivery;

        $this->assertSame(['en' => 'Carrier A branch'], $before['title']);
        $this->assertSame(900, $before['price']['amount']);
        $this->assertSame(['en' => 'Branch 1'], $before['point']['title']);

        DeliveryMethod::query()->where('key', 'carrier-a-branch')->firstOrFail()
            ->forceFill(['titles' => ['en' => 'Renamed'], 'price' => 100])->save();
        DeliveryPoint::query()->where('external_id', 'p-1')->update(['titles' => json_encode(['en' => 'Moved'])]);

        $this->assertSame($before, Order::query()->findOrFail($order->id)->delivery);
        $this->assertSame("Carrier A branch, Town A · 9,00\u{a0}€", DeliverySnapshot::summary($before, 'en'));
    }

    #[Test]
    public function a_waybill_by_carrier_rates_is_paid_by_the_recipient(): void
    {
        Event::fake([ShipmentCreated::class]);
        $order = $this->branchOrder();

        $shipment = $this->shipments()->create($order, [], 'panel');

        $request = $this->parcels->requests[0];
        $this->assertSame('recipient', $request->payer);
        $this->assertSame('recipient', $shipment->payer);
        // The names as the order keeps them, apart — never split by spaces.
        $this->assertSame('Ann', $request->recipientFirstName);
        $this->assertSame('Example', $request->recipientLastName);
        $this->assertSame('Ann Example', $request->recipientName());
        // The waybill names the carrier's own town of the snapshot.
        $this->assertSame('a-1', $request->carrierLocality);
        $this->assertSame([], $request->extra);
        $this->assertSame('+800 1234 5678', $request->recipientPhone);
        $this->assertSame($order->total, $request->declaredValue);
        $this->assertNull($request->cod);
        $this->assertSame('carrier-a', $request->carrier());
        $this->assertSame('p-1', $request->delivery['point']['external_id']);
        $this->assertCount(1, $request->lines);

        $this->assertSame('A-0001', $shipment->number);
        $this->assertSame(ShipmentStatus::Created, $shipment->status);
        $this->assertSame(4500, $shipment->cost);
        $this->assertFalse($shipment->final);
        $this->assertNotNull($shipment->next_check_at);

        // The number in the order's block and a line of its history, through the order's own API.
        $fresh = Order::query()->findOrFail($order->id);
        $this->assertSame(['number' => 'A-0001', 'shipment_id' => $shipment->id, 'payer' => 'recipient'], $fresh->delivery['waybill']);
        $this->assertSame(1, StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::BLOCK)->count());
        Event::assertDispatched(ShipmentCreated::class, static fn (ShipmentCreated $event): bool => $event->shipment->number === 'A-0001' && $event->source === 'panel');
    }

    #[Test]
    public function a_waybill_of_a_fixed_price_is_paid_by_the_sender_and_takes_the_managers_changes(): void
    {
        $order = $this->branchOrder(900);

        $shipment = $this->shipments()->create($order, ['recipient_first_name' => 'Bo', 'recipient_last_name' => 'Example-Two', 'recipient_phone' => '', 'declared_value' => 1500, 'cod' => 2500], 'panel');

        $request = $this->parcels->requests[0];
        $this->assertSame('sender', $request->payer);
        $this->assertSame('sender', $shipment->payer);
        $this->assertSame('Bo', $request->recipientFirstName);
        $this->assertSame('Example-Two', $request->recipientLastName);
        $this->assertNull($request->recipientPhone);
        $this->assertSame(1500, $request->declaredValue);
        $this->assertSame(2500, $request->cod);
    }

    #[Test]
    public function one_waybill_at_a_time_and_none_for_the_shops_own_methods(): void
    {
        $order = $this->branchOrder();
        $first = $this->shipments()->create($order, [], 'panel');

        try {
            $this->shipments()->create($order, [], 'panel');
            $this->fail('A second waybill was made over the first.');
        } catch (WaybillRefused $refused) {
            $this->assertSame('A waybill is made already: cancel it before making another.', $refused->getMessage());
        }

        $this->shipments()->cancel($first, 'panel');
        $this->assertSame(['A-0001'], $this->parcels->cancelled);
        $this->assertSame(ShipmentStatus::Cancelled, $first->status);
        $this->assertTrue($first->final);
        $this->assertSame('A-0002', $this->shipments()->create($order, [], 'panel')->number);

        $own = $this->placeWith(['method_id' => $this->pickup->id, 'point_id' => DeliveryPoint::query()->where('carrier', 'store')->value('id')]);
        $this->assertNull($this->shipments()->waybills($own));
        $this->expectException(WaybillRefused::class);
        $this->shipments()->request($own);
    }

    #[Test]
    public function a_refusal_of_the_carrier_writes_nothing(): void
    {
        $order = $this->branchOrder();
        $this->parcels->refuse('Too heavy.');

        try {
            $this->shipments()->create($order, [], 'panel');
            $this->fail('The refusal was not passed on.');
        } catch (WaybillRefused $refused) {
            $this->assertSame('Too heavy.', $refused->getMessage());
        }

        $this->parcels->refuse(null)->down();

        try {
            $this->shipments()->create($order, [], 'panel');
            $this->fail('A carrier that did not answer made a waybill.');
        } catch (WaybillRefused $refused) {
            $this->assertSame('The carrier did not answer', $refused->getMessage());
        }

        $this->assertSame(0, DeliveryShipment::query()->count());
        $this->assertArrayNotHasKey('waybill', Order::query()->findOrFail($order->id)->delivery);
    }

    #[Test]
    public function received_sets_the_milestone_once(): void
    {
        $order = $this->branchOrder();
        $shipment = $this->shipments()->create($order, [], 'panel');
        $later = CarbonImmutable::now()->addHours(3);

        // Not due yet: the tracking asks nobody.
        $this->assertSame(0, $this->shipments()->track());
        $this->assertSame([], $this->parcels->tracked);

        $this->parcels->move('A-0001', ShipmentStatus::AtPoint, 'Arrived at branch 1');
        $this->assertSame(1, $this->shipments()->track(now: $later));
        $shipment->refresh();
        $this->assertSame(ShipmentStatus::AtPoint, $shipment->status);
        // At the point the buyer is about to come: asked again in half an hour, not two.
        $this->assertEqualsWithDelta($later->addMinutes(30)->getTimestamp(), $shipment->next_check_at?->getTimestamp(), 2);
        $this->assertNull(Order::query()->findOrFail($order->id)->received_at);

        $this->parcels->move('A-0001', ShipmentStatus::Received, 'Handed over');
        $this->assertSame(1, $this->shipments()->track(now: $later->addHour()));
        $shipment->refresh();
        $this->assertTrue($shipment->final);
        $this->assertNull($shipment->next_check_at);
        $this->assertSame(['received', 'at_point', 'created'], array_column($shipment->events, 'status'));

        $milestones = OrderMilestone::query()->where('order_id', $order->id)->where('milestone', Milestone::Received->value)->get();
        $this->assertCount(1, $milestones);
        $this->assertSame('delivery:carrier-a', $milestones[0]->source);
        $this->assertNotNull(Order::query()->findOrFail($order->id)->received_at);

        // Final: nobody asks about it again, so nothing is marked twice.
        $this->assertSame(0, $this->shipments()->track(now: $later->addDays(1)));
        $this->assertSame(0, $this->shipments()->track([$shipment]));
        $this->assertSame(1, OrderMilestone::query()->where('order_id', $order->id)->where('milestone', Milestone::Received->value)->count());
    }

    #[Test]
    public function a_manager_who_marked_it_first_keeps_the_mark(): void
    {
        $order = $this->branchOrder();
        $this->shipments()->create($order, [], 'panel');
        $this->app->make(Orders::class)->mark($order, Milestone::Received, 'panel');

        $this->parcels->move('A-0001', ShipmentStatus::Received);
        $this->assertSame(1, $this->shipments()->track(now: CarbonImmutable::now()->addHours(3)));

        $milestones = OrderMilestone::query()->where('order_id', $order->id)->where('milestone', Milestone::Received->value)->get();
        $this->assertCount(1, $milestones);
        $this->assertSame('panel', $milestones[0]->source);
    }

    #[Test]
    public function a_refusal_and_a_return_are_lines_of_the_history_and_no_milestone(): void
    {
        $order = $this->branchOrder();
        $shipment = $this->shipments()->create($order, [], 'panel');

        $this->parcels->move('A-0001', ShipmentStatus::Refused, 'The buyer did not come');
        $this->shipments()->track([$shipment]);
        $this->parcels->move('A-0001', ShipmentStatus::Returned);
        $this->shipments()->track([$shipment]);

        $notes = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::NOTE)->orderBy('id')->pluck('note')->all();
        $this->assertSame([
            'Parcel A-0001: Refused by the buyer. The buyer did not come',
            'Parcel A-0001: Returned to the shop.',
        ], $notes);
        $this->assertTrue($shipment->refresh()->final);
        $this->assertSame(0, OrderMilestone::query()->where('order_id', $order->id)->where('milestone', Milestone::Received->value)->count());
    }

    #[Test]
    public function a_carrier_that_does_not_answer_is_asked_again_and_a_still_parcel_stops_being_asked(): void
    {
        $order = $this->branchOrder();
        $shipment = $this->shipments()->create($order, [], 'panel');
        $later = CarbonImmutable::now()->addHours(3);

        $this->parcels->down();
        $this->assertSame(0, $this->shipments()->track(now: $later));
        $shipment->refresh();
        $this->assertFalse($shipment->final);
        $this->assertEqualsWithDelta($later->addHours(2)->getTimestamp(), $shipment->next_check_at?->getTimestamp(), 2);

        // Sixty days with no movement: final, never asked again.
        $this->parcels->down(false);
        $this->assertSame(0, $this->shipments()->track(now: CarbonImmutable::now()->addDays(61)));
        $shipment->refresh();
        $this->assertTrue($shipment->final);
        $this->assertNull($shipment->next_check_at);
    }

    #[Test]
    public function the_command_tracks_what_is_due_and_the_schedule_runs_it(): void
    {
        $order = $this->branchOrder();
        $shipment = $this->shipments()->create($order, [], 'panel');
        $shipment->forceFill(['next_check_at' => now()->subMinute()])->save();
        $this->parcels->move('A-0001', ShipmentStatus::InTransit);

        $this->artisan('webx:delivery:track')->expectsOutputToContain('Parcels that moved: 1.')->assertSuccessful();
        $this->assertSame(ShipmentStatus::InTransit, $shipment->refresh()->status);

        $events = collect($this->app->make(Schedule::class)->events())
            ->filter(static fn ($event): bool => str_contains((string) $event->command, 'webx:delivery:track'));
        $this->assertCount(1, $events);
        $this->assertSame('*/30 * * * *', $events->first()->expression);
    }
}
