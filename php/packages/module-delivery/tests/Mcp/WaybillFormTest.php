<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Mcp;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\CarrierLocalities;
use WebxUi\Delivery\Models\DeliveryCarrier;
use WebxUi\Delivery\Models\DeliveryCarrierLocality;
use WebxUi\Delivery\Models\DeliveryShipment;
use WebxUi\Delivery\Tests\Fakes\FakeBranchType;
use WebxUi\Delivery\Tests\Shipping\ShippingTestCase;
use WebxUi\Mcp\McpServiceProvider;

/**
 * The waybill a type describes (§11.2, `DescribesWaybill`): its own fields drawn and sent in
 * `extra`, filled from the carrier's settings or the field's default; cash on delivery hidden and
 * refused where the type takes none — by the panel and by an agent alike; the names apart; the
 * carrier's own town. And `CarrierLocalities`, which a satellite reads the base's towns by.
 */
final class WaybillFormTest extends ShippingTestCase
{
    use CallsTools;

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), McpServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        FakeBranchType::$fields = [
            ['name' => 'weight', 'type' => 'number', 'required' => true, 'default' => 0.5, 'label' => ['en' => 'Weight, kg']],
            ['name' => 'description', 'type' => 'text', 'default' => 'Goods', 'label' => ['en' => 'What is inside']],
        ];
        FakeBranchType::$cod = false;
        DeliveryCarrier::query()->whereKey('carrier-a')->update(['settings' => json_encode(['weight' => 2])]);
    }

    #[Test]
    public function the_dialog_draws_the_types_fields_and_no_cash_on_delivery(): void
    {
        $order = $this->branchOrder();
        $this->signIn(['orders.view', 'delivery.view', 'delivery.ship']);

        $draft = $this->getJson("/api/cms/delivery/orders/{$order->id}")->assertOk()->json('data.draft');

        $this->assertFalse($draft['form']['cod']);
        $this->assertSame(['weight', 'description'], array_column($draft['form']['fields'], 'name'));
        // The carrier's setting of the same name is stronger than the field's default.
        $this->assertEquals(2, $draft['form']['fields'][0]['default']);
        $this->assertSame('Goods', $draft['form']['fields'][1]['default']);
        $this->assertSame(['weight' => 2, 'description' => 'Goods'], $draft['extra']);
        $this->assertSame(['first_name' => 'Ann', 'last_name' => 'Example'], array_intersect_key($draft['recipient'], ['first_name' => 1, 'last_name' => 1]));
        $this->assertSame('a-1', $draft['carrier_locality']);

        // Cash on delivery is refused, not dropped: the manager would believe it is collected.
        $this->postJson("/api/cms/delivery/orders/{$order->id}/waybill", ['cod' => 1200])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['waybill' => 'This carrier takes no cash on delivery for this method.']);
        $this->assertCount(0, $this->parcels->requests);

        $this->postJson("/api/cms/delivery/orders/{$order->id}/waybill", [
            'recipient_first_name' => 'Bo',
            'recipient_last_name' => 'Example',
            'extra' => ['weight' => '1.25', 'seats' => 9],
        ])->assertCreated();

        $request = $this->parcels->requests[0];
        $this->assertSame('Bo', $request->recipientFirstName);
        $this->assertSame('Example', $request->recipientLastName);
        $this->assertNull($request->cod);
        // A number is a number, and a field the type does not declare does not reach the carrier.
        $this->assertSame(['weight' => 1.25, 'description' => 'Goods'], $request->extra);
    }

    #[Test]
    public function a_required_field_left_empty_and_a_word_for_a_number_are_refused(): void
    {
        $order = $this->branchOrder();
        $this->signIn(['orders.view', 'delivery.view', 'delivery.ship']);

        $this->postJson("/api/cms/delivery/orders/{$order->id}/waybill", ['extra' => ['weight' => '']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['extra.weight' => 'Fill in: Weight, kg']);
        $this->postJson("/api/cms/delivery/orders/{$order->id}/waybill", ['extra' => ['weight' => 'heavy']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['extra.weight' => 'Weight, kg must be a number.']);
        // Blank in the dialog is «as the order says»; an order with no first name has nothing to send.
        $order->forceFill(['customer_first_name' => ''])->save();
        $this->postJson("/api/cms/delivery/orders/{$order->id}/waybill", ['recipient_first_name' => ' '])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipient_first_name' => 'The recipient\'s first name is empty.']);
        $this->assertSame(0, DeliveryShipment::query()->count());
    }

    #[Test]
    public function an_agent_gets_the_same_form_and_the_same_refusal(): void
    {
        $order = $this->branchOrder();
        $shipper = $this->signIn(['delivery.ship']);

        $this->agent('delivery_shipments_create', ['order' => $order->id, 'cod' => 5000, 'dry_run' => true], $shipper)
            ->assertHasErrors(['This carrier takes no cash on delivery for this method.']);

        $rehearsed = $this->content($this->agent('delivery_shipments_create', [
            'order' => $order->id,
            'recipient_first_name' => 'Bo',
            'recipient_last_name' => 'Example',
            'extra' => ['weight' => 3],
            'dry_run' => true,
        ], $shipper)->assertOk());

        $this->assertFalse($rehearsed['form']['cod']);
        $this->assertSame(['weight', 'description'], array_column($rehearsed['form']['fields'], 'name'));
        $this->assertSame(['weight' => 3, 'description' => 'Goods'], $rehearsed['request']['extra']);
        $this->assertSame('Bo', $rehearsed['request']['recipient']['first_name']);
        $this->assertSame([], $this->parcels->requests);
    }

    #[Test]
    public function a_type_without_a_description_keeps_the_bases_dialog(): void
    {
        FakeBranchType::reset();
        $order = $this->branchOrder();
        $this->signIn(['orders.view', 'delivery.view', 'delivery.ship']);

        $this->getJson("/api/cms/delivery/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.draft.form', ['fields' => [], 'cod' => true]);
        $this->postJson("/api/cms/delivery/orders/{$order->id}/waybill", ['cod' => 1200, 'extra' => ['weight' => 3]])->assertCreated();
        $this->assertSame(1200, $this->parcels->requests[0]->cod);
        $this->assertSame([], $this->parcels->requests[0]->extra);
    }

    #[Test]
    public function a_satellite_reads_the_ids_of_its_towns_page_by_page(): void
    {
        foreach (['a-3' => ['courier'], 'a-2' => ['branch'], 'a-4' => ['courier', 'branch'], 'a-5' => ['courier']] as $id => $features) {
            DeliveryCarrierLocality::query()->create([
                'carrier' => 'carrier-a', 'external_id' => $id, 'locality_id' => $this->townB->id, 'status' => 'matched',
                'method' => 'auto_exact', 'record' => ['name' => 'Town B'], 'features' => $features,
            ]);
        }

        DeliveryCarrierLocality::query()->where('external_id', 'a-5')->update(['removed_at' => now()]);
        DeliveryCarrierLocality::query()->create([
            'carrier' => 'carrier-b', 'external_id' => 'a-0', 'locality_id' => $this->townB->id, 'status' => 'matched',
            'method' => 'auto_exact', 'record' => ['name' => 'Town B'], 'features' => ['courier'],
        ]);

        $localities = $this->app->make(CarrierLocalities::class);

        $this->assertSame(['a-1', 'a-2', 'a-3', 'a-4'], $localities->externalIds('carrier-a'));
        $this->assertSame(['a-1', 'a-2'], $localities->externalIds('carrier-a', limit: 2));
        $this->assertSame(['a-3', 'a-4'], $localities->externalIds('carrier-a', after: 'a-2', limit: 2));
        $this->assertSame(['a-1', 'a-3', 'a-4'], $localities->externalIds('carrier-a', feature: 'courier'));
        $this->assertSame([], $localities->externalIds('carrier-a', after: 'a-4'));

        $this->assertSame('a-1', $localities->externalId('carrier-a', $this->townA->id));
        $this->assertNull($localities->externalId('carrier-b', $this->townA->id));
    }
}
