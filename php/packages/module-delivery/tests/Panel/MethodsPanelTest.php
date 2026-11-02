<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\Types\StoreCourier;
use WebxUi\Delivery\Types\StorePickup;

/** The methods and the shop's points from the panel (§14.2). */
final class MethodsPanelTest extends PanelTestCase
{
    #[Test]
    public function a_method_is_made_from_its_type_and_its_price_is_edited_in_units(): void
    {
        $this->signIn();

        $made = $this->postJson($this->api('methods'), ['type' => StorePickup::KEY, 'values' => [
            'titles' => ['en' => 'Pick-up'],
            'price' => 7.5,
            'enabled' => true,
        ]])->assertCreated();

        $made->assertJsonPath('data.values.price', 7.5)
            ->assertJsonPath('data.values.point_kind', 'store')
            ->assertJsonPath('data.hidden', ['localities']);

        $method = DeliveryMethod::query()->sole();
        $this->assertSame('store-pickup', $method->key);
        $this->assertSame(750, $method->price);

        $this->postJson($this->api('methods'), ['type' => 'nobody.nothing', 'values' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    #[Test]
    public function the_courier_keeps_its_localities_and_the_pickup_does_not_take_them(): void
    {
        $this->signIn();
        $town = $this->town('Town A');

        $courier = $this->postJson($this->api('methods'), ['type' => StoreCourier::KEY, 'values' => [
            'titles' => ['en' => 'Courier'],
            'localities' => [$town->id],
        ]])->assertCreated();

        $courier->assertJsonPath('data.values.localities', [$town->id])
            ->assertJsonPath('data.hidden', ['point_kind']);

        $pickup = $this->method('pickup', StorePickup::KEY);
        $this->putJson($this->api('methods/'.$pickup->id), ['values' => ['localities' => [$town->id]]])->assertOk();
        $this->assertSame([], $pickup->localities()->pluck('geo_localities.id')->all());
    }

    #[Test]
    public function the_order_is_dragged_as_a_whole(): void
    {
        $this->signIn();
        $a = $this->method('a', StorePickup::KEY, ['position' => 0]);
        $b = $this->method('b', StorePickup::KEY, ['position' => 1]);
        $c = $this->method('c', StoreCourier::KEY, ['position' => 2]);

        $this->putJson($this->api('methods/order'), ['ids' => [$c->id, $a->id, $b->id]])->assertNoContent();

        $this->getJson($this->api('methods'))
            ->assertOk()
            ->assertJsonPath('data.methods.*.key', ['c', 'a', 'b']);

        // Not every method named: refused, nothing moved.
        $this->putJson($this->api('methods/order'), ['ids' => [$a->id, $b->id]])->assertUnprocessable();
        $this->assertSame(0, $c->refresh()->position);
    }

    #[Test]
    public function a_point_is_made_on_a_locality_made_on_the_spot(): void
    {
        $this->signIn();
        $region = $this->region();

        // «Add a locality» from the point's form: the short form, then the point names it.
        $town = $this->postJson($this->api('geography/localities'), ['values' => [
            'names' => ['en' => 'Town N'], 'kind' => 'town', 'country' => 'XX', 'region_id' => $region->id,
        ]])->assertCreated()->json('data.locality.id');

        $this->postJson($this->api('points'), ['values' => [
            'kind' => 'store',
            'titles' => ['en' => 'Store A'],
            'address' => ['en' => '1 Example street'],
            'locality_id' => $town,
            'phone' => '+800 1234 5678',
            'active' => true,
            'schedule' => ['week' => ['mon' => [['09:00', '18:00']], 'sun' => []]],
        ]])->assertCreated()->assertJsonPath('data.point.locality.label', 'Town N, Region A');

        $point = DeliveryPoint::query()->sole();
        $this->assertSame('store', $point->carrier);
        $this->assertSame([['09:00', '18:00']], $point->schedule['week']['mon']);

        $this->getJson($this->api('points?kind=store'))->assertOk()->assertJsonPath('total', 1);
    }

    #[Test]
    public function a_point_needs_its_locality_and_hours_that_read(): void
    {
        $this->signIn();

        $this->postJson($this->api('points'), ['values' => [
            'kind' => 'store',
            'address' => ['en' => '1 Example street'],
            'schedule' => ['week' => ['mon' => [['18:00', '09:00']]]],
        ]])->assertUnprocessable()->assertJsonValidationErrors('schedule');

        $this->postJson($this->api('points'), ['values' => [
            'kind' => 'store',
            'address' => ['en' => '1 Example street'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('locality_id');
    }

    #[Test]
    public function a_carriers_point_is_not_the_panels(): void
    {
        $this->signIn();
        $point = $this->point($this->town('Town A'), 'branch', ['carrier' => 'carrier-a']);

        $this->getJson($this->api('points/'.$point->id))->assertNotFound();
    }
}
