<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Shipping;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Shipping\Shipments;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderAdjustment;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\Orders;

/**
 * «Change delivery» of the order's card (decision of 02.11.2026): a manager puts the point, the
 * address or the method right; the type checks it as at the checkout, a new snapshot is written,
 * the price takes the old one's place in the total and the balance says what to take or give
 * back; not while a waybill stands; a line in the history says what it was and who changed it.
 */
final class OrderChangeTest extends ShippingTestCase
{
    private const array MANAGER = ['orders.view', 'orders.manage', 'delivery.view'];

    private function store(string $title): DeliveryPoint
    {
        return DeliveryPoint::query()->where('carrier', 'store')->where('titles->en', $title)->firstOrFail();
    }

    private function pickupOrder(string $store = 'Store A'): Order
    {
        return $this->placeWith(['method_id' => $this->pickup->id, 'point_id' => $this->store($store)->id]);
    }

    /** @return array<string, mixed> */
    private function snapshotOf(Order $order): array
    {
        return [
            'order' => (array) DB::table('orders')->where('id', $order->id)->first(),
            'adjustments' => DB::table('order_adjustments')->where('order_id', $order->id)->orderBy('id')->get()->all(),
            'history' => DB::table('order_status_changes')->where('order_id', $order->id)->count(),
        ];
    }

    #[Test]
    public function another_point_of_the_same_price_leaves_the_total_alone(): void
    {
        $order = $this->pickupOrder();
        $total = $order->total;
        $user = $this->signIn(self::MANAGER);

        $answer = $this->postJson("/api/cms/delivery/orders/{$order->id}/change", [
            'locality' => $this->townA->id,
            'method' => $this->pickup->id,
            'point' => $this->store('Store B')->id,
            'reason' => 'The buyer asked for the other store',
        ])->assertOk()->json('data');

        $this->assertFalse($answer['change']['total_changes']);
        $this->assertSame('Store B', $answer['delivery']['delivery']['point']['title']);

        $order->refresh();
        $this->assertSame($this->store('Store B')->id, $order->delivery['point']['id']);
        $this->assertSame($total, $order->total);
        $this->assertSame(0, OrderAdjustment::query()->where('order_id', $order->id)->where('source', 'delivery')->count());

        // The history says what it was, what it is, why and who.
        $line = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::BLOCK_CHANGED)->sole();
        $this->assertSame($user->id, $line->admin_id);
        $card = $this->getJson("/api/cms/orders/{$order->id}")->assertOk()->json('data.order');
        /** @var list<array<string, mixed>> $lines */
        $lines = $card['timeline'];
        $timeline = array_values(array_filter($lines, static fn (array $line): bool => $line['kind'] === 'block_changed'))[0];
        $this->assertSame('delivery', $timeline['block']);
        $this->assertSame('Pick up at a store, Town A · Free', $timeline['from']);
        $this->assertSame('Pick up at a store, Town A · Free', $timeline['to']);
        $this->assertSame('The buyer asked for the other store', $timeline['note']);
        $this->assertNull($timeline['total_from']);
        $this->assertSame($user->name, $timeline['admin']);
    }

    #[Test]
    public function a_courier_address_is_checked_by_its_type_and_a_dearer_method_leaves_a_top_up(): void
    {
        $order = $this->pickupOrder();
        $this->app->make(Orders::class)->mark($order, Milestone::Paid, 'test');
        $order->refresh();
        $before = $order->total;
        $this->signIn(self::MANAGER);

        // The courier wants a house: the type's own check, under the field it is about.
        $this->postJson("/api/cms/delivery/orders/{$order->id}/change", [
            'locality' => $this->townA->id,
            'method' => $this->courier->id,
            'address' => ['street' => 'Elm street'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['address.house']);

        // The dry run shows «was → will be» and writes nothing.
        $kept = $this->snapshotOf($order);
        $dry = $this->postJson("/api/cms/delivery/orders/{$order->id}/change", [
            'locality' => $this->townA->id,
            'method' => $this->courier->id,
            'address' => ['street' => 'Elm street', 'house' => '5', 'apartment' => '12'],
            'dry_run' => true,
        ])->assertOk()->json('data.change');
        $this->assertTrue($dry['total_changes']);
        $this->assertSame('topup', $dry['balance']['kind']);
        $this->assertSame($kept, $this->snapshotOf($order));

        $this->postJson("/api/cms/delivery/orders/{$order->id}/change", [
            'locality' => $this->townA->id,
            'method' => $this->courier->id,
            'address' => ['street' => 'Elm street', 'house' => '5', 'apartment' => '12'],
        ])->assertOk();

        $order->refresh();
        $this->assertSame($before + 500, $order->total);
        $this->assertSame(500, $order->balance);
        $this->assertSame('Elm street', $order->delivery['address']['street']);
        $this->assertSame([500], OrderAdjustment::query()->where('order_id', $order->id)->where('source', 'delivery')->pluck('amount')->all());

        // «Summary» has the delivery as a row of its own; «Items» does not repeat it.
        $card = $this->getJson("/api/cms/orders/{$order->id}")->assertOk()->json('data.order');
        $this->assertSame([['key' => 'delivery', 'amount' => "5,00\u{00A0}€", 'amount_minor' => 500]], $card['totals']['charges']);
        $this->assertNull($card['totals']['adjustments']);
        $this->assertSame([], array_values(array_filter($card['adjustments'], static fn (array $one): bool => $one['source'] === 'delivery')));
        $this->assertTrue($card['blocks'][0]['own']);
        $this->assertNull($card['blocks'][0]['value']);
        $this->assertSame('topup', $card['totals']['balance']['kind']);

        // Back to the store: the line goes, the shop owes the money back.
        $this->postJson("/api/cms/delivery/orders/{$order->id}/change", [
            'locality' => $this->townA->id,
            'method' => 'store-pickup',
            'point' => $this->store('Store A')->id,
        ])->assertOk();
        $order->refresh();
        $this->assertSame($before, $order->total);
        $this->assertSame(0, OrderAdjustment::query()->where('order_id', $order->id)->where('source', 'delivery')->count());
        $this->assertSame([['key' => 'delivery', 'amount' => 'Free', 'amount_minor' => 0]], $this->getJson("/api/cms/orders/{$order->id}")->json('data.order.totals.charges'));
    }

    #[Test]
    public function a_carrier_courier_takes_a_street_of_its_directory_only(): void
    {
        $order = $this->pickupOrder();
        $this->signIn(self::MANAGER);
        $courier = DeliveryMethod::query()->where('key', 'carrier-a-courier')->firstOrFail();

        $this->postJson("/api/cms/delivery/orders/{$order->id}/change", [
            'locality' => $this->townA->id,
            'method' => $courier->id,
            'address' => ['street' => 'Anything', 'house' => '1'],
        ])->assertUnprocessable();

        $streets = $this->getJson("/api/cms/delivery/orders/{$order->id}/change/streets?locality={$this->townA->id}&method={$courier->id}&q=Elm")->assertOk()->json('data.options');
        $this->assertNotEmpty($streets);

        $this->postJson("/api/cms/delivery/orders/{$order->id}/change", [
            'locality' => $this->townA->id,
            'method' => $courier->id,
            'address' => ['street' => $streets[0]['label'], 'street_id' => $streets[0]['value'], 'house' => '1'],
        ])->assertOk();
        $this->assertSame('carrier-a-courier', $order->refresh()->delivery['method']);
    }

    #[Test]
    public function a_standing_waybill_holds_the_delivery_until_it_is_cancelled(): void
    {
        $order = $this->branchOrder();
        $this->signIn([...self::MANAGER, 'delivery.ship']);
        $shipment = $this->app->make(Shipments::class)->create($order, [], 'test');
        $other = DeliveryPoint::query()->where('carrier', 'carrier-a')->orderByDesc('id')->firstOrFail();
        $body = ['locality' => $this->townA->id, 'method' => 'carrier-a-branch', 'point' => $other->id];

        $tab = $this->getJson("/api/cms/delivery/orders/{$order->id}")->assertOk()->json('data');
        $this->assertSame('Cancel the waybill first: the carrier has the old delivery.', $tab['change']['refusal']);
        $this->postJson("/api/cms/delivery/orders/{$order->id}/change", $body)->assertUnprocessable()->assertJsonValidationErrors(['delivery']);

        $this->app->make(Shipments::class)->cancel($shipment, 'test');
        $this->assertNull($this->getJson("/api/cms/delivery/orders/{$order->id}")->json('data.change.refusal'));
        $this->postJson("/api/cms/delivery/orders/{$order->id}/change", $body)->assertOk();
        $this->assertSame($other->id, $order->refresh()->delivery['point']['id']);
    }

    #[Test]
    public function only_whoever_edits_the_order_changes_its_delivery(): void
    {
        $order = $this->pickupOrder();
        $this->signIn(['orders.view', 'delivery.view', 'delivery.ship']);

        $this->assertNull($this->getJson("/api/cms/delivery/orders/{$order->id}")->assertOk()->json('data.change'));
        $this->postJson("/api/cms/delivery/orders/{$order->id}/change", [
            'locality' => $this->townA->id, 'method' => $this->pickup->id, 'point' => $this->store('Store B')->id,
        ])->assertForbidden();
        $this->getJson("/api/cms/delivery/orders/{$order->id}/change/methods?locality={$this->townA->id}")->assertForbidden();

        // Whoever edits orders reads the checkout's search and methods for the order's lines.
        $this->signIn(['orders.view', 'orders.manage']);
        $towns = $this->getJson("/api/cms/delivery/orders/{$order->id}/change/localities?q=Town")->assertOk()->json('data');
        $this->assertContains($this->townA->id, array_column($towns['localities'], 'id'));
        $this->assertSame([$this->townB->id, $this->townA->id], array_column($towns['popular'], 'id'));
        $methods = $this->getJson("/api/cms/delivery/orders/{$order->id}/change/methods?locality={$this->townA->id}")->assertOk()->json('data.methods');
        $this->assertContains('our-courier', array_column($methods, 'key'));
        $points = $this->getJson("/api/cms/delivery/orders/{$order->id}/change/points?locality={$this->townA->id}&method={$this->pickup->id}")->assertOk()->json('data.options');
        $this->assertCount(2, $points);
    }

    #[Test]
    public function the_same_choice_again_is_refused(): void
    {
        $order = $this->pickupOrder();
        $this->signIn(self::MANAGER);

        $this->postJson("/api/cms/delivery/orders/{$order->id}/change", [
            'locality' => $this->townA->id, 'method' => $this->pickup->id, 'point' => $this->store('Store A')->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['delivery']);
    }
}
