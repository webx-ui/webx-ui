<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Shipping;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Models\DeliveryShipment;
use WebxUi\Delivery\Shipping\ShipmentStatus;

/**
 * The tab «Delivery» of the order's card (§14.2): patched onto `orders.show` after «Items» for
 * whoever reads the delivery (`delivery.view`); the snapshot from the order's copy; «Create waybill» with a dry
 * run first, «Refresh tracking», the label and «Cancel waybill» behind `delivery.ship`.
 */
final class OrderTabTest extends ShippingTestCase
{
    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private static function find(array $node, string $id): ?array
    {
        if (($node['id'] ?? null) === $id) {
            return $node;
        }

        // The root is a list of nodes; a node keeps its own under `children`.
        foreach (array_is_list($node) ? $node : (array) ($node['children'] ?? []) as $child) {
            if (is_array($child) && ($found = self::find($child, $id)) !== null) {
                return $found;
            }
        }

        return null;
    }

    #[Test]
    public function the_tab_is_on_the_card_after_items_for_whoever_reads_the_delivery(): void
    {
        $this->signIn(['orders.view', 'delivery.view', 'delivery.ship']);
        $root = $this->getJson('/api/cms/screens/orders.show')->assertOk()->json('data.root');
        $tabs = self::find($root, 'tabs');

        $this->assertNotNull($tabs);
        $ids = array_column($tabs['children'], 'id');
        $this->assertSame('delivery', $ids[array_search('items', $ids, true) + 1]);
        $this->assertSame('Delivery', self::find($root, 'delivery')['label'] ?? null);
        $this->assertSame('wx-delivery-order', self::find($root, 'delivery-order')['type'] ?? null);

        // A manager of orders who does not read the delivery sees the card as it was.
        $this->signIn(['orders.view']);
        $root = $this->getJson('/api/cms/screens/orders.show')->assertOk()->json('data.root');
        $this->assertNull(self::find($root, 'delivery'));
    }

    #[Test]
    public function the_tab_reads_the_snapshot_and_offers_the_waybill_to_whoever_ships(): void
    {
        $order = $this->branchOrder();

        $this->signIn(['orders.view', 'delivery.view']);
        $card = $this->getJson("/api/cms/delivery/orders/{$order->id}")->assertOk()->json('data');

        $this->assertSame('Carrier A branch', $card['delivery']['method']);
        $this->assertSame('Town A, Region A', $card['delivery']['locality']);
        $this->assertSame('Branch 1', $card['delivery']['point']['title']);
        $this->assertSame('By carrier rates', $card['delivery']['price']);
        $this->assertSame('recipient', $card['delivery']['payer']);
        $this->assertTrue($card['waybills']);
        $this->assertFalse($card['can_ship']);
        $this->assertNull($card['draft']);
        $this->postJson("/api/cms/delivery/orders/{$order->id}/waybill")->assertForbidden();
        $this->postJson("/api/cms/delivery/orders/{$order->id}/track")->assertForbidden();

        $this->signIn(['orders.view', 'delivery.ship']);
        $card = $this->getJson("/api/cms/delivery/orders/{$order->id}")->assertOk()->json('data');
        $this->assertTrue($card['can_ship']);
        $this->assertSame('Ann Example', $card['draft']['recipient']['name']);
        $this->assertSame($order->total, $card['draft']['declared_value']);
        $this->assertSame('recipient', $card['draft']['payer']);
    }

    #[Test]
    public function a_dry_run_shows_the_request_and_sends_nothing(): void
    {
        $order = $this->branchOrder(900);
        $this->signIn(['orders.view', 'delivery.ship']);

        $request = $this->postJson("/api/cms/delivery/orders/{$order->id}/waybill", ['dry_run' => true, 'cod' => 1200, 'recipient_first_name' => 'Bo', 'recipient_last_name' => 'Example'])
            ->assertOk()
            ->json('data.request');

        $this->assertSame('sender', $request['payer']);
        $this->assertSame(1200, $request['cod']);
        $this->assertSame('Bo', $request['recipient']['first_name']);
        $this->assertSame('Example', $request['recipient']['last_name']);
        $this->assertSame('Bo Example', $request['recipient']['name']);
        $this->assertSame([], $this->parcels->requests);
        $this->assertSame(0, DeliveryShipment::query()->count());

        $this->postJson("/api/cms/delivery/orders/{$order->id}/waybill", ['cod' => -1])->assertUnprocessable()->assertJsonValidationErrors('cod');
    }

    #[Test]
    public function a_waybill_is_made_tracked_labelled_and_cancelled_from_the_tab(): void
    {
        $order = $this->branchOrder();
        $this->signIn(['orders.view', 'delivery.ship']);

        $card = $this->postJson("/api/cms/delivery/orders/{$order->id}/waybill", [])->assertCreated()->json('data');
        $this->assertSame('A-0001', $card['shipments'][0]['number']);
        $this->assertSame('created', $card['shipments'][0]['status']);
        $this->assertSame('Waybill created', $card['shipments'][0]['status_label']);
        $this->assertSame('45,00 €', str_replace("\u{a0}", ' ', (string) $card['shipments'][0]['cost_formatted']));
        $this->assertSame('A-0001', $card['delivery']['waybill']);
        $this->assertNull($card['draft']);
        $this->assertSame($card['shipments'][0]['id'], $card['active']);

        $this->postJson("/api/cms/delivery/orders/{$order->id}/waybill", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['waybill' => 'A waybill is made already: cancel it before making another.']);

        $this->parcels->move('A-0001', ShipmentStatus::InTransit, 'Left the hub');
        $card = $this->postJson("/api/cms/delivery/orders/{$order->id}/track")->assertOk()->json('data');
        $this->assertSame('in_transit', $card['shipments'][0]['status']);
        $this->assertSame('Left the hub', $card['shipments'][0]['events'][0]['text']);

        $id = $card['shipments'][0]['id'];
        $this->getJson("/api/cms/delivery/shipments/{$id}/label")->assertOk()->assertJsonPath('data.url', 'https://carrier-a.example.test/labels/A-0001.pdf');

        $card = $this->postJson("/api/cms/delivery/shipments/{$id}/cancel")->assertOk()->json('data');
        $this->assertSame('cancelled', $card['shipments'][0]['status']);
        $this->assertFalse($card['shipments'][0]['label']);
        $this->assertNull($card['active']);
        $this->assertNotNull($card['draft']);
    }

    #[Test]
    public function the_shops_own_method_has_no_waybill(): void
    {
        $point = DeliveryPoint::query()->where('carrier', 'store')->value('id');
        $order = $this->placeWith(['method_id' => $this->pickup->id, 'point_id' => $point]);
        $this->signIn(['orders.view', 'delivery.ship']);

        $card = $this->getJson("/api/cms/delivery/orders/{$order->id}")->assertOk()->json('data');

        $this->assertSame('Pick up at a store', $card['delivery']['method']);
        $this->assertSame('Store A', $card['delivery']['point']['title']);
        $this->assertSame('Free', $card['delivery']['price']);
        $this->assertFalse($card['waybills']);
        $this->assertNull($card['draft']);
        $this->postJson("/api/cms/delivery/orders/{$order->id}/waybill", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('waybill');
    }
}
