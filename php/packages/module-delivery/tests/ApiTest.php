<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Delivery\Models\DeliverySelection;
use WebxUi\Delivery\Points;

/**
 * The JSON API of the step (§12.2): the suggestions are everybody's and cached by the browser, the
 * methods and the choice are the visitor's cart's — written only after the checks, by field.
 */
final class ApiTest extends StepTestCase
{
    #[Test]
    public function the_countries_towns_and_popular_towns_are_everybodys_and_cached_by_the_browser(): void
    {
        $this->getJson('/api/delivery/countries')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public')
            ->assertJsonPath('countries.0.code', 'XX');

        $this->getJson('/api/delivery/localities?country=XX&q=town b')
            ->assertOk()
            ->assertJsonPath('options.0.value', (string) $this->townB->id)
            ->assertJsonPath('options.0.label', 'Town B')
            ->assertJsonPath('options.0.hint', 'Region B')
            ->assertJsonPath('localities.0.label', 'Town B, Region B');

        // The administrator's order, not the weight (decision 40).
        $this->getJson('/api/delivery/localities/popular?country=XX')
            ->assertOk()
            ->assertJsonPath('localities.0.id', $this->townB->id)
            ->assertJsonPath('localities.1.id', $this->townA->id);
    }

    #[Test]
    public function the_points_of_a_method_are_a_page_searched_inside_the_town(): void
    {
        $this->carrierA(branches: 25);

        $first = $this->getJson("/api/delivery/points?method=carrier-a-branch&locality={$this->townA->id}")->assertOk();
        $this->assertCount(20, $first->json('options'));
        $this->assertSame(25, $first->json('total'));
        $this->assertTrue($first->json('more'));
        // Short numbers first: 2 before 10.
        $this->assertSame(['Branch 1', 'Branch 2'], array_slice(array_column($first->json('options'), 'label'), 0, 2));

        $this->getJson("/api/delivery/points?method=carrier-a-branch&locality={$this->townA->id}&page=2")
            ->assertJsonCount(5, 'options')
            ->assertJsonPath('more', false);

        // A number typed in full comes first; a word of the address finds the rest.
        $found = $this->getJson("/api/delivery/points?method=carrier-a-branch&locality={$this->townA->id}&q=12")->assertOk();
        $this->assertSame('Branch 12', $found->json('options.0.label'));
        $this->assertStringContainsString('12 Example street', (string) $found->json('options.0.hint'));

        // The shop's own stores are not the carrier's branches, and another town has none.
        $this->getJson("/api/delivery/points?method=carrier-a-branch&locality={$this->townB->id}")->assertJsonPath('total', 0);
        $this->getJson("/api/delivery/points?method=store-pickup&locality={$this->townA->id}")->assertJsonPath('total', 2);
        $this->getJson("/api/delivery/points?method=our-courier&locality={$this->townA->id}")->assertNotFound();
        $this->getJson('/api/delivery/points?method=nobody&locality=1')->assertNotFound();

        $id = $first->json('points.0.id');
        $this->getJson("/api/delivery/points/{$id}")->assertOk()->assertJsonPath('point.week.mon.0', ['09:00', '18:00']);
    }

    #[Test]
    public function a_point_for_some_people_only_is_said_and_comes_after_the_others(): void
    {
        $this->carrierA(branches: 4);
        // Branch 1 is a locker in a block of flats, for those who live there; Branch 3 has a word
        // of a carrier the base does not know.
        DB::table('delivery_points')->where('external_id', 'p-1')->update(['restriction' => 'residents']);
        DB::table('delivery_points')->where('external_id', 'p-3')->update(['restriction' => 'staff-only']);

        $list = $this->getJson("/api/delivery/points?method=carrier-a-branch&locality={$this->townA->id}")->assertOk();

        $this->assertSame(['Branch 2', 'Branch 4', 'Branch 1', 'Branch 3'], array_column($list->json('options'), 'label'));
        $this->assertSame([null, null, 'residents', 'staff-only'], array_column($list->json('points'), 'restriction'));
        $this->assertStringEndsWith('Only for residents of the building', (string) $list->json('options.2.hint'));
        $this->assertStringEndsWith('Limited access', (string) $list->json('options.3.hint'));
        $this->assertStringNotContainsString('residents', (string) $list->json('options.0.hint'));

        // A number typed in full still comes first.
        $this->getJson("/api/delivery/points?method=carrier-a-branch&locality={$this->townA->id}&q=1")
            ->assertJsonPath('options.0.label', 'Branch 1');

        $id = $list->json('points.2.id');
        $this->getJson("/api/delivery/points/{$id}")->assertJsonPath('point.restricted', 'Only for residents of the building');
        $this->assertSame('Лише для мешканців будинку', Points::restriction('residents', 'uk'));
    }

    #[Test]
    public function the_streets_of_a_carriers_courier_are_its_ids_and_of_the_shops_courier_text(): void
    {
        $this->carrierA();

        $this->getJson("/api/delivery/streets?method=carrier-a-courier&locality={$this->townA->id}&q=elm")
            ->assertOk()
            ->assertJsonPath('options.0.value', 's-1')
            ->assertJsonPath('options.0.label', 'Elm 1 street');

        $this->getJson("/api/delivery/streets?method=our-courier&locality={$this->townA->id}&q=elm")
            ->assertOk()
            ->assertJsonPath('options.0.value', 'Elm 1 street')
            ->assertJsonPath('streets.0.id', null);
    }

    #[Test]
    public function the_methods_and_the_choice_need_the_visitors_cart(): void
    {
        $this->getJson("/api/delivery/methods?locality={$this->townA->id}")->assertNotFound();
        $this->putJson('/api/delivery/selection', ['locality' => $this->townA->id])->assertNotFound();

        $methods = $this->withCart()->getJson("/api/delivery/methods?locality={$this->townA->id}")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame(['store-pickup', 'our-courier'], array_column($methods->json('methods'), 'key'));
        $this->assertSame('Free', $methods->json('methods.0.price.text'));
        $this->assertSame("5,00\u{a0}€", $methods->json('methods.1.price.text'));
        $this->assertSame(2, $methods->json('methods.0.points'));
    }

    #[Test]
    public function a_town_then_a_method_then_a_point_are_written_and_priced(): void
    {
        $this->withCart()->putJson('/api/delivery/selection', ['country' => 'XX', 'locality' => $this->townA->id])
            ->assertOk()
            ->assertJsonPath('selection.locality', $this->townA->id)
            ->assertJsonPath('selection.method', null)
            ->assertJsonPath('cost', null);

        $this->withCart()->putJson('/api/delivery/selection', ['method' => 'our-courier'])
            ->assertOk()
            ->assertJsonPath('selection.method', $this->courier->id)
            ->assertJsonPath('cost.text', "5,00\u{a0}€")
            ->assertJsonPath('cost.in_total', true);

        $point = $this->point($this->townA, 'store', ['titles' => ['en' => 'Store C']]);
        $this->withCart()->putJson('/api/delivery/selection', ['method' => $this->pickup->id, 'point' => $point->id])
            ->assertOk()
            ->assertJsonPath('selection.point', $point->id)
            ->assertJsonPath('cost.text', 'Free');
    }

    #[Test]
    public function a_refusal_says_why_by_field_and_writes_nothing(): void
    {
        $this->withCart()->putJson('/api/delivery/selection', ['locality' => 999999])
            ->assertStatus(422)
            ->assertJsonPath('errors.locality.0', 'Choose a city or town from the list.');
        $this->assertSame(0, DeliverySelection::query()->count());

        // The courier does not go to Town B.
        $this->withCart()->putJson('/api/delivery/selection', ['locality' => $this->townB->id, 'method' => $this->courier->id])
            ->assertStatus(422)
            ->assertJsonPath('errors.method.0', 'This delivery method is not available here any more, choose another one.');

        $this->withCart()->putJson('/api/delivery/selection', ['locality' => $this->townA->id, 'method' => $this->courier->id, 'address' => ['street' => '', 'house' => '']])
            ->assertStatus(422)
            ->assertJsonPath('errors', [
                'address.street' => ['Enter the street.'],
                'address.house' => ['Enter the house number.'],
            ]);

        $this->withCart()->putJson('/api/delivery/selection', ['locality' => $this->townA->id, 'method' => $this->pickup->id, 'point' => 424242])
            ->assertStatus(422)
            ->assertJsonPath('errors.point.0', 'Pick-up point is no longer available, choose another one.');
        $this->assertDatabaseCount('delivery_selections', 0);
    }

    #[Test]
    public function a_carriers_courier_takes_a_street_of_its_directory_only_and_none_without_streets(): void
    {
        $binding = $this->carrierA();
        $courier = 'carrier-a-courier';

        $refused = $this->withCart()->putJson('/api/delivery/selection', ['locality' => $this->townA->id, 'method' => $courier, 'address' => ['street' => 'Typed by hand', 'house' => '5']])
            ->assertStatus(422);
        $this->assertSame(['Choose the street from the list.'], $refused->json('errors')['address.street']);

        $this->withCart()->putJson('/api/delivery/selection', ['locality' => $this->townA->id, 'method' => $courier, 'address' => ['street_id' => 's-2', 'street' => 'whatever', 'house' => '5']])
            ->assertOk()
            ->assertJsonPath('selection.address.street', 'Elm 2 street')
            ->assertJsonPath('selection.address.street_id', 's-2');

        // No streets of the carrier in the town: the courier is shown and cannot be chosen (§8.2).
        $binding->forceFill(['streets' => 0])->save();
        $this->withCart()->putJson('/api/delivery/selection', ['method' => $this->pickup->id])->assertOk();
        $this->withCart()->putJson('/api/delivery/selection', ['method' => $courier])
            ->assertStatus(422)
            ->assertJsonPath('errors.method.0', 'Courier delivery is not available here, choose another method');
    }

    #[Test]
    public function a_new_town_drops_the_point_and_keeps_a_method_it_offers_too(): void
    {
        $this->courier->syncLocalities([$this->townA->id, $this->townB->id]);
        $this->withCart()->putJson('/api/delivery/selection', ['locality' => $this->townA->id, 'method' => $this->courier->id, 'address' => ['street' => 'Elm', 'house' => '1', 'apartment' => '2']])->assertOk();

        $this->withCart()->putJson('/api/delivery/selection', ['locality' => $this->townB->id])
            ->assertOk()
            ->assertJsonPath('selection.method', $this->courier->id)
            ->assertJsonPath('selection.address', null);

        $point = $this->point($this->townA);
        $this->withCart()->putJson('/api/delivery/selection', ['locality' => $this->townA->id, 'method' => $this->pickup->id, 'point' => $point->id])->assertOk();
        // Town B has no store: the pick-up goes with the point.
        $this->withCart()->putJson('/api/delivery/selection', ['locality' => $this->townB->id])
            ->assertJsonPath('selection.method', null)
            ->assertJsonPath('selection.point', null);
    }

    #[Test]
    public function the_step_drawn_again_comes_with_the_answer_when_the_script_asks(): void
    {
        $answer = $this->withCart()
            ->withHeaders(['X-Webx-Delivery-View' => 'step', 'X-Webx-Delivery-Event' => 'webx:checkout:slot-changed'])
            ->putJson('/api/delivery/selection', ['locality' => $this->townA->id, 'method' => $this->pickup->id])
            ->assertOk();

        $html = (string) $answer->json('html');
        $this->assertStringContainsString('data-webx-delivery', $html);
        $this->assertStringContainsString('data-checkout-event="webx:checkout:slot-changed"', $html);
        $this->assertStringContainsString('Store A', $html);
        $this->assertStringContainsString('Town A, Region A', $html);
    }

    #[Test]
    public function a_form_without_javascript_goes_back_with_the_refusal_or_the_town_it_typed(): void
    {
        $this->withCart()->from('/checkout')
            ->put('/api/delivery/selection', ['_form' => 'step', 'country' => 'XX', 'locality_name' => 'town a'])
            ->assertRedirect('/checkout#webx-delivery');
        $this->assertSame($this->townA->id, DeliverySelection::query()->value('locality_id'));

        $this->withCart()->from('/checkout')
            ->put('/api/delivery/selection', ['_form' => 'step', 'locality_name' => 'Nowhere'])
            ->assertRedirect('/checkout#webx-delivery')
            ->assertSessionHasErrors(['locality']);

        // The radio of the old town comes along with a new town: dropped, not refused.
        $this->withCart()->from('/checkout')
            ->put('/api/delivery/selection', ['_form' => 'step', 'locality_name' => 'Town B', 'method' => (string) $this->courier->id, 'point' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->townB->id, DeliverySelection::query()->value('locality_id'));
        $this->assertNull(DeliverySelection::query()->value('method_id'));
    }

    #[Test]
    public function the_write_is_guarded_as_a_form_and_the_suggestions_are_throttled(): void
    {
        $routes = app('router')->getRoutes();

        // CSRF and the session come with the web group; Laravel lets tests through, so the group is what is held.
        $this->assertContains('web', $routes->getByName('webx-delivery.selection.update')?->middleware() ?? []);
        $this->assertContains('throttle:webx-delivery-write', $routes->getByName('webx-delivery.selection.update')?->middleware() ?? []);
        $this->assertSame(['throttle:webx-delivery'], $routes->getByName('webx-delivery.localities')?->middleware());

        config()->set('webx-delivery.throttle.suggest', 2);
        $this->getJson('/api/delivery/countries')->assertOk();
        $this->getJson('/api/delivery/countries')->assertOk();
        $this->getJson('/api/delivery/countries')->assertStatus(429);
    }
}
