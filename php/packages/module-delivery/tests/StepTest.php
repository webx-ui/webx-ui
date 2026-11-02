<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Delivery\CartDelivery;
use WebxUi\Delivery\Checkout\StepView;
use WebxUi\Delivery\Models\DeliveryMethod;
use WebxUi\Delivery\Models\DeliverySelection;
use WebxUi\Delivery\PopularLocalities;
use WebxUi\Geography\Editor;
use WebxUi\Widgets\Facades\Widgets;

/**
 * The step as the checkout prints it (§12.3): the card of the town and the dialog with the search
 * and the popular towns (decision 40), the methods with their prices, the chosen one opened in
 * place — a list of points with a search, the address of either courier — and, without
 * JavaScript, one form.
 */
final class StepTest extends StepTestCase
{
    /** @param  array<string, mixed>  $attributes */
    private function choose(array $attributes): void
    {
        DeliverySelection::query()->updateOrCreate(['cart_id' => $this->basket->id], ['country_code' => 'XX', ...$attributes]);
        $this->app->make(CartDelivery::class)->forget();
    }

    private function render(?MessageBag $errors = null, ?string $name = null): string
    {
        $state = $this->app->make(Calculator::class)->calculate($this->basket, 'en', Purpose::Checkout);

        return view('webx-delivery::step', [
            'step' => $this->app->make(StepView::class)->build($state, $this->basket->id, 'en', $errors, $name),
            'event' => 'webx:checkout:slot-changed',
        ])->render();
    }

    #[Test]
    public function before_a_town_there_is_the_card_that_asks_for_one_and_the_dialog(): void
    {
        $html = $this->render();

        $this->assertMatchesRegularExpression('~<p class="webx-delivery__card is-empty">.*?Choose a city or town</span>\s*<a class="webx-delivery__change" href="#webx-delivery-locality" data-webx-dialog="webx-delivery-where" aria-haspopup="dialog">Choose</a>~s', $html);
        // No methods before the town; one country — no choice of it (decision 17).
        $this->assertStringNotContainsString('webx-delivery__methods', $html);
        $this->assertStringContainsString('<input type="hidden" name="country" value="XX">', $html);
        $this->assertStringNotContainsString('data-delivery-country', $html);

        // The dialog: the server's search and the popular towns in the administrator's order.
        $this->assertStringContainsString('<h2 class="webx-dialog__title" id="webx-delivery-where-title">Where to deliver the order?</h2>', $html);
        $this->assertStringContainsString('data-webx-select="http://localhost/api/delivery/localities?country=XX"', $html);
        $this->assertMatchesRegularExpression('~data-delivery-town="'.$this->townB->id.'".*?>Town B</button>.*?data-delivery-town="'.$this->townA->id.'"~s', $html);
        // Outside the form: the dialog's list is not sent with the form without JavaScript.
        $this->assertGreaterThan(strpos($html, '</form>'), strpos($html, '<dialog'));
        $this->assertStringContainsString('data-checkout-event="webx:checkout:slot-changed"', $html);
        $this->assertContains('select', Widgets::claimed());
    }

    #[Test]
    public function an_empty_list_of_popular_towns_leaves_the_search_alone(): void
    {
        $this->app->make(PopularLocalities::class)->set([]);

        $html = $this->render();

        $this->assertStringNotContainsString('webx-delivery__popular', $html);
        $this->assertStringContainsString('webx-delivery__search', $html);
    }

    #[Test]
    public function without_javascript_the_town_is_a_field_with_a_list_to_start_from(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('<form class="webx-delivery__form" method="post" action="http://localhost/api/delivery/selection">', $html);
        $this->assertStringContainsString('<input type="hidden" name="_method" value="PUT">', $html);
        $this->assertMatchesRegularExpression('~<input class="webx-delivery__control" type="text" id="webx-delivery-locality" name="locality_name" value="" list="webx-delivery-locality-list"~', $html);
        $this->assertMatchesRegularExpression('~<option value="Town B">Town B, Region B</option>~', $html);
        $this->assertStringContainsString('<button type="submit" class="webx-delivery__show">Show delivery methods</button>', $html);
    }

    #[Test]
    public function the_methods_of_the_town_show_their_prices_and_the_chosen_pickup_its_points(): void
    {
        $this->choose(['locality_id' => $this->townA->id, 'method_id' => $this->pickup->id]);

        $html = $this->render();

        $this->assertStringContainsString('Town A, Region A', $html);
        $this->assertMatchesRegularExpression('~<div class="webx-delivery__method is-chosen">.*?Pick up at a store.*?webx-delivery__price--free">\s*Free~s', $html);
        $this->assertMatchesRegularExpression("~Our courier</span>.*?webx-delivery__price--fixed\">\\s*5,00\u{a0}€~s", $html);
        // The pick-up opens a list with a search of its points; the first page is loaded.
        $this->assertMatchesRegularExpression('~data-webx-select="http://localhost/api/delivery/points\?method=store-pickup&amp;locality='.$this->townA->id.'"~', $html);
        $this->assertMatchesRegularExpression('~<option value="\d+"\s+data-hint="1 Example street"\s*>Store A</option>~', $html);
        $this->assertStringContainsString('Store B', $html);
        $this->assertMatchesRegularExpression('~<p class="webx-delivery__summary" data-delivery-cost="free">.*?Delivery cost.*?Free~s', $html);
    }

    #[Test]
    public function the_chosen_point_is_shown_with_its_address_and_phone(): void
    {
        $point = $this->point($this->townA, 'store', ['titles' => ['en' => 'Store C'], 'address' => ['en' => '3 Example street']]);
        $this->choose(['locality_id' => $this->townA->id, 'method_id' => $this->pickup->id, 'point_id' => $point->id]);

        $html = $this->render();

        $this->assertMatchesRegularExpression('~<div class="webx-delivery__point-card">\s*<p class="webx-delivery__point-title">Store C</p>\s*<p class="webx-delivery__point-address">3 Example street</p>~', $html);
        $this->assertStringContainsString('<a href="tel:+80012345678">+800 1234 5678</a>', $html);
        $this->assertMatchesRegularExpression('~<option value="'.$point->id.'"\s+data-hint="3 Example street"\s+selected\s*>Store C</option>~', $html);
    }

    #[Test]
    public function a_point_and_a_street_beyond_the_first_ones_loaded_are_named_in_their_lists(): void
    {
        $this->carrierA(branches: 25, streets: 60);
        $branch = DeliveryMethod::query()->where('key', 'carrier-a-branch')->firstOrFail();
        $far = (int) DB::table('delivery_points')->where('carrier', 'carrier-a')->where('number', '24')->value('id');
        $this->choose(['locality_id' => $this->townA->id, 'method_id' => $branch->id, 'point_id' => $far]);

        $this->assertMatchesRegularExpression('~<option value="'.$far.'"\s+data-hint="24 Example street[^"]*"\s+selected\s*>Branch 24</option>~', $this->render());

        $courier = DeliveryMethod::query()->where('key', 'carrier-a-courier')->firstOrFail();
        $this->choose(['method_id' => $courier->id, 'point_id' => null, 'address' => ['street' => 'Elm 9 street', 'street_id' => 's-9', 'house' => '1']]);

        // Elm 9 sorts after Elm 59 and is not among the first fifty.
        $this->assertMatchesRegularExpression('~<option value="s-9"\s+selected\s*>Elm 9 street</option>~', $this->render());
    }

    #[Test]
    public function the_shops_courier_takes_a_street_as_text(): void
    {
        $this->choose(['locality_id' => $this->townA->id, 'method_id' => $this->courier->id, 'address' => ['street' => 'Elm', 'house' => '5', 'apartment' => '7', 'note' => 'Ring twice']]);

        $html = $this->render();

        $this->assertStringContainsString('data-strict="false"', $html);
        $this->assertMatchesRegularExpression('~<input class="webx-select__native" type="text" id="webx-delivery-address-street" name="address\[street\]" value="Elm"~', $html);
        $this->assertMatchesRegularExpression('~name="address\[house\]" value="5"~', $html);
        $this->assertMatchesRegularExpression('~name="address\[apartment\]" value="7"~', $html);
        $this->assertStringContainsString('>Ring twice</textarea>', $html);
    }

    #[Test]
    public function a_carriers_courier_takes_a_street_of_its_directory_and_without_streets_is_greyed_out(): void
    {
        $binding = $this->carrierA();
        $courier = DeliveryMethod::query()->where('key', 'carrier-a-courier')->firstOrFail();
        $this->choose(['locality_id' => $this->townA->id, 'method_id' => $courier->id, 'address' => ['street' => 'Elm 2 street', 'street_id' => 's-2', 'house' => '1']]);

        $html = $this->render();

        $this->assertStringContainsString('data-strict="true"', $html);
        $this->assertMatchesRegularExpression('~<select class="webx-select__native" id="webx-delivery-address-street" name="address\[street_id\]" required\s*>~', $html);
        $this->assertMatchesRegularExpression('~<option value="s-2"\s+selected\s*>Elm 2 street</option>~', $html);
        $this->assertStringContainsString('By carrier rates', $html);

        $binding->forceFill(['streets' => 0])->save();
        $html = $this->render();
        $this->assertMatchesRegularExpression('~<div class="webx-delivery__method is-disabled">.*?<input class="webx-delivery__radio" type="radio" id="webx-delivery-method-'.$courier->id.'" name="method" value="'.$courier->id.'"\s+disabled\s+aria-describedby="[^"]+-hint"\s*>.*?Courier delivery is not available here, choose another method~s', $html);
        // Shown, not chosen: nothing opens under it.
        $this->assertStringNotContainsString('data-delivery-address', $html);
    }

    #[Test]
    public function a_refusal_by_field_and_a_prefix_of_the_owners_form(): void
    {
        $this->choose(['locality_id' => $this->townA->id, 'method_id' => $this->courier->id]);

        $html = $this->render(new MessageBag(['address.house' => 'Enter the house number.']), 'delivery');

        $this->assertMatchesRegularExpression('~<p class="webx-delivery__error" data-delivery-error="address.house"\s*>Enter the house number.</p>~', $html);
        $this->assertStringContainsString('name="delivery[address][house]"', $html);
        $this->assertStringContainsString('name="delivery[method]"', $html);
        $this->assertStringContainsString('data-name="delivery"', $html);
    }

    #[Test]
    public function the_component_takes_the_visitors_cart_by_itself(): void
    {
        $this->choose(['locality_id' => $this->townA->id]);
        $this->withCart()->get('/');

        $html = Blade::render('<x-webx-delivery::step />');

        $this->assertStringContainsString('data-webx-delivery', $html);
        $this->assertStringContainsString('<script type="module" src="http://localhost/webx/delivery/delivery.js?v=', $html);
        // The script once a page, however many ask.
        $this->assertStringNotContainsString('delivery.js', Blade::render('<x-webx-delivery::step />'));
    }

    #[Test]
    public function a_switched_off_town_drops_out_of_the_step(): void
    {
        $this->choose(['locality_id' => $this->townB->id]);
        $this->app->make(Editor::class)->deactivate($this->townB->fresh());

        $this->getJson('/api/delivery/localities/popular?country=XX')
            ->assertJsonMissing(['id' => $this->townB->id]);
        $this->assertStringNotContainsString('data-delivery-town="'.$this->townB->id.'"', $this->render());
    }
}
