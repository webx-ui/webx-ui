<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Mcp;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Models\DeliveryShipment;
use WebxUi\Delivery\Tests\Shipping\ShippingTestCase;
use WebxUi\Mcp\McpServiceProvider;
use WebxUi\Mcp\Registry\ToolRegistry;

/**
 * The parcels by the other door (§15) — there where `module-orders` is — and the lists an agent
 * pages through, warm, with no statement run twice (§17): points, the binding queue, parcels.
 */
final class ShippingMcpTest extends ShippingTestCase
{
    use CallsTools;
    use CountsQueries;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), McpServiceProvider::class];
    }

    #[Test]
    public function the_parcels_tools_are_there_with_the_orders_behind_the_right_to_ship(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(['delivery.ship'], $registry->tool('delivery_shipments_create')->permissions());
        $this->assertSame(['delivery.ship'], $registry->tool('delivery_shipments_track')->permissions());
        $this->assertContains('delivery.ship', $registry->tool('delivery_shipments_list')->permissions());

        $order = $this->branchOrder();
        $settings = $this->signIn(['delivery.view', 'delivery.manage']);
        $this->agent('delivery_shipments_create', ['order' => $order->id], $settings)->assertHasErrors(['[delivery.ship]']);
    }

    #[Test]
    public function a_waybill_is_rehearsed_without_the_carrier_then_made_listed_and_tracked(): void
    {
        $order = $this->branchOrder();
        $shipper = $this->signIn(['delivery.ship']);

        $rehearsed = $this->content($this->agent('delivery_shipments_create', ['order' => $order->id, 'cod' => 5000, 'dry_run' => true], $shipper)->assertOk());
        $this->assertSame($order->id, $rehearsed['request']['order_id']);
        $this->assertSame([], $this->parcels->requests);
        $this->assertSame(0, DeliveryShipment::query()->count());

        $made = $this->content($this->agent('delivery_shipments_create', ['order' => $order->id], $shipper)->assertOk());
        $this->assertTrue($made['created']);
        $this->assertCount(1, $this->parcels->requests);

        $listed = $this->use('delivery_shipments_list', ['order' => $order->id]);
        $this->assertSame([$made['shipment']['id']], array_column($listed['shipments'], 'id'));

        $this->assertSame([$made['shipment']['id']], $this->content($this->agent('delivery_shipments_track', ['order' => $order->id, 'dry_run' => true], $shipper)->assertOk())['would_ask']);
        $tracked = $this->content($this->agent('delivery_shipments_track', ['order' => $order->id], $shipper)->assertOk());
        $this->assertSame([$made['shipment']['id']], $tracked['asked']);
    }

    #[Test]
    public function the_lists_of_an_agent_run_no_statement_twice(): void
    {
        foreach (range(1, 3) as $i) {
            $this->branchOrder();
            DeliveryShipment::query()->create([
                'order_id' => $this->branchOrder()->id, 'carrier' => 'carrier-a', 'type' => 'carrier-a.branch',
                'method_key' => 'carrier-a-branch', 'number' => 'N-'.$i, 'payer' => 'recipient', 'status' => 'in_transit',
                'events' => [], 'meta' => [],
            ]);
            DeliveryCarrierLocality::query()->create([
                'carrier' => 'carrier-a', 'external_id' => 'q-'.$i, 'status' => 'pending', 'reason' => 'not_found',
                'record' => ['name' => 'Town Q'.$i, 'country' => 'XX'], 'candidates' => [['locality_id' => $this->townA->id, 'distance_km' => 1.5]],
            ]);
        }

        $user = $this->signIn(['delivery.view', 'delivery.manage', 'delivery.ship']);
        $registry = $this->app->make(ToolRegistry::class);

        foreach ([
            'delivery_points_search' => ['locality' => $this->townA->id, 'q' => 'branch'],
            'delivery_bindings_list' => [],
            'delivery_shipments_list' => ['in_transit_days' => 0],
            'delivery_localities_search' => ['q' => 'town'],
        ] as $tool => $arguments) {
            $handler = $registry->tool($tool)->tool->handler;
            // Warm: the caches of the groups are filled by a first call.
            $handler($arguments, $user);
            $answer = $this->assertNoRepeatedQueries(static fn (): mixed => $handler($arguments, $user), $tool);
            $this->assertNotSame([], $answer, $tool);
        }

        $bindings = $this->use('delivery_bindings_list');
        $this->assertSame(3, $bindings['total']);
        $this->assertSame($this->townA->id, $bindings['bindings'][0]['candidates'][0]['id']);

        // A decision for the whole filter: a dry run counts, the real one binds.
        $this->assertSame(3, $this->use('delivery_bindings_resolve', ['action' => 'bind', 'all' => true, 'locality' => $this->townA->id, 'dry_run' => true])['matching']);
        $this->assertSame(3, DeliveryCarrierLocality::query()->where('status', 'pending')->count());
        $this->assertSame(0, $this->use('delivery_bindings_resolve', ['action' => 'ignore', 'all' => true, 'reason' => 'not_found'])['left']);
    }

    #[Test]
    public function an_agent_changes_the_delivery_of_an_order_after_a_dry_run(): void
    {
        $this->assertSame(['orders.manage'], $this->app->make(ToolRegistry::class)->tool('delivery_order_change')->permissions());

        $point = DeliveryPoint::query()->where('carrier', 'store')->orderBy('id')->firstOrFail();
        $order = $this->placeWith(['method_id' => $this->pickup->id, 'point_id' => $point->id]);
        $total = $order->total;
        $manager = $this->signIn(['orders.view', 'orders.manage']);
        $arguments = ['order' => $order->id, 'locality' => $this->townA->id, 'method' => 'our-courier', 'address' => ['street' => 'Elm street', 'house' => '5'], 'reason' => 'Wrong door'];

        // The dry run answers the new block and the difference of the sum, and writes nothing.
        $dry = $this->content($this->agent('delivery_order_change', [...$arguments, 'dry_run' => true], $manager)->assertOk());
        $this->assertTrue($dry['dry_run']);
        $this->assertSame('our-courier', $dry['delivery']['method']);
        $this->assertSame(500, $dry['total_difference']);
        $this->assertSame('store-pickup', $order->refresh()->delivery['method']);
        $this->assertSame($total, $order->total);

        $done = $this->content($this->agent('delivery_order_change', $arguments, $manager)->assertOk());
        $this->assertTrue($done['changed']);
        $this->assertSame('our-courier', $order->refresh()->delivery['method']);
        $this->assertSame($total + 500, $order->total);

        // Who only ships does not edit the order.
        $this->agent('delivery_order_change', $arguments, $this->signIn(['delivery.ship']))->assertHasErrors(['[orders.manage]']);
    }
}
