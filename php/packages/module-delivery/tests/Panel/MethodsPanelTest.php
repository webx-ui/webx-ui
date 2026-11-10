<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\AbstractMethodType;
use WebxUi\Delivery\Asks;
use WebxUi\Delivery\Carriers;
use WebxUi\Delivery\Contracts\LabelledMethodType;
use WebxUi\Delivery\MethodTypes;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliveryPoint;
use WebxUi\Delivery\PointKinds;
use WebxUi\Delivery\Testing\FakeCarrier;
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

    #[Test]
    public function a_carriers_type_is_named_in_words_and_says_its_kind_itself(): void
    {
        $this->signIn();
        $this->app->make(Carriers::class)->register(new FakeCarrier);
        $types = $this->app->make(MethodTypes::class);
        $types->register(new class extends AbstractMethodType
        {
            public function key(): string
            {
                return 'carrier-a.branch';
            }

            public function asks(): Asks
            {
                return Asks::Point;
            }

            public function pointKinds(): array
            {
                return ['branch'];
            }
        });
        $types->register(new class extends AbstractMethodType implements LabelledMethodType
        {
            public function key(): string
            {
                return 'carrier-a.express';
            }

            public function asks(): Asks
            {
                return Asks::Address;
            }

            public function title(string $locale): string
            {
                return 'Express to the door';
            }

            public function description(string $locale): string
            {
                return 'Within two hours.';
            }
        });

        $answer = $this->getJson($this->api('methods'))->assertOk();
        $named = array_column($answer->json('data.types'), null, 'key');

        // Never the key: the kind after the dot with the carrier, or what the type says itself.
        $this->assertSame('Carrier A branch', $named['carrier-a.branch']['title']);
        $this->assertSame("The buyer chooses one of the carrier's branches or lockers.", $named['carrier-a.branch']['description']);
        $this->assertSame('Express to the door', $named['carrier-a.express']['title']);
        $this->assertSame('Within two hours.', $named['carrier-a.express']['description']);
        $this->assertNotSame('store.pickup', $named['store.pickup']['title']);

        // One kind: the form does not ask, and a kind of the shop's own sent anyway is not taken.
        $this->postJson($this->api('methods'), ['type' => 'carrier-a.branch', 'values' => [
            'titles' => ['en' => 'Branch'],
            'point_kind' => 'store',
        ]])->assertCreated()
            ->assertJsonPath('data.method.type_title', 'Carrier A branch')
            ->assertJsonPath('data.values.point_kind', 'branch')
            ->assertJsonPath('data.hidden', ['point_kind', 'localities']);
    }

    #[Test]
    public function the_points_are_sorted_by_the_column_asked(): void
    {
        $this->signIn();
        $b = $this->town('Town B');
        $a = $this->town('Town A');

        foreach ([['Store C', $a], ['Store A', $b], ['Store B', $a]] as [$title, $town]) {
            $this->postJson($this->api('points'), ['values' => [
                'kind' => 'store',
                'titles' => ['en' => $title],
                'address' => ['en' => '1 Example street'],
                'locality_id' => $town->id,
                'active' => true,
            ]])->assertCreated();
        }

        $this->getJson($this->api('points?sort=title'))->assertOk()
            ->assertJsonPath('data.*.title', ['Store A', 'Store B', 'Store C']);
        $this->getJson($this->api('points?sort=-title'))->assertOk()
            ->assertJsonPath('data.*.title', ['Store C', 'Store B', 'Store A']);
        $this->getJson($this->api('points?sort=locality'))->assertOk()
            ->assertJsonPath('data.2.title', 'Store A');
    }

    #[Test]
    public function a_kind_of_points_named_by_hand_gets_its_code_brought_not_refused(): void
    {
        $this->signIn();

        // #125: what is typed becomes a code; only nothing left of it, or a built-in kind, is refused.
        $options = $this->postJson($this->api('methods/point-kinds'), ['kind' => 'Тормозные Колодки', 'names' => ['en' => 'Brakes']])
            ->assertCreated()
            ->json('data');
        $this->assertContains('tormoznye-kolodki', array_column($options, 'value'));
        $this->assertTrue($this->app->make(PointKinds::class)->has('tormoznye-kolodki'));

        $this->postJson($this->api('methods/point-kinds'), ['kind' => '!!!', 'names' => ['en' => 'Nothing']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kind');
        $this->postJson($this->api('methods/point-kinds'), ['kind' => 'Store', 'names' => ['en' => 'Shop']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kind');
    }
}
