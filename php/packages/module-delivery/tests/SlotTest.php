<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Delivery\CartDelivery;
use WebxUi\Delivery\Checkout\DeliveryCheckoutSlot;
use WebxUi\Delivery\DeliverySnapshot;
use WebxUi\Delivery\Models\DeliverySelection;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Checkout\Slots\ShippingLine;
use WebxUi\Orders\Checkout\Slots\SlotStep;
use WebxUi\Orders\Checkout\Steps\StepError;

/**
 * Delivery as the checkout's slot «delivery» (§22.2 of the orders spec): registered by itself
 * where the checkout is, it prints the step, says what is chosen in one line, gives the column its
 * line «Delivery cost» and the payment step its neutral choice, and stops the confirmation while
 * the choice is not complete.
 */
final class SlotTest extends StepTestCase
{
    private DeliveryCheckoutSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->slot = $this->app->make(DeliveryCheckoutSlot::class);
    }

    /** @param  array<string, mixed>|null  $choice */
    private function checkout(?array $choice = null): CheckoutContext
    {
        if ($choice !== null) {
            DeliverySelection::query()->updateOrCreate(['cart_id' => $this->basket->id], ['country_code' => 'XX', ...$choice]);
        }

        $this->app->make(CartDelivery::class)->forget();
        $state = $this->app->make(Calculator::class)->calculate($this->basket, 'en', Purpose::Checkout);

        return new CheckoutContext($this->basket, $state, null, null, 'en', 'EUR');
    }

    /**
     * @param  list<StepError>  $errors
     * @return list<string>
     */
    private static function codes(array $errors): array
    {
        return array_map(static fn (StepError $error): string => $error->code, $errors);
    }

    #[Test]
    public function the_checkout_finds_it_in_its_slot_without_being_told(): void
    {
        $slots = $this->app->make(CheckoutSlots::class);

        $this->assertInstanceOf(DeliveryCheckoutSlot::class, $slots->shipping());
        $this->assertSame('delivery', $this->slot->key());
    }

    #[Test]
    public function it_prints_the_step_that_tells_the_checkout_of_each_change(): void
    {
        $html = $this->slot->render($this->checkout())->toHtml();

        $this->assertStringContainsString('data-webx-delivery', $html);
        $this->assertStringContainsString('data-checkout-event="'.SlotStep::CHANGED.'"', $html);
    }

    #[Test]
    public function the_step_is_needed_only_for_what_travels(): void
    {
        $this->assertTrue($this->slot->needed($this->checkout()));

        // An assembly service travels nowhere.
        $service = $this->app->make(CartService::class)->add(null, [['type' => 'test.goods', 'ref' => 2]], 'en')->cart;
        $state = $this->app->make(Calculator::class)->calculate($service, 'en', Purpose::Checkout);

        $this->assertFalse($this->slot->needed(new CheckoutContext($service, $state, null, null, 'en', 'EUR')));
    }

    #[Test]
    public function an_incomplete_choice_stops_the_confirmation_with_its_reason(): void
    {
        $this->assertSame(['delivery.locality'], self::codes($this->slot->check($this->checkout(), Purpose::Checkout)));
        $this->assertSame(['delivery.method'], self::codes($this->slot->check($this->checkout(['locality_id' => $this->townA->id]), Purpose::Checkout)));
        $this->assertSame(['delivery.point'], self::codes($this->slot->check($this->checkout(['method_id' => $this->pickup->id]), Purpose::Checkout)));
        $this->assertSame(['delivery.address'], self::codes($this->slot->check($this->checkout(['method_id' => $this->courier->id, 'address' => ['street' => 'Elm']]), Purpose::Order)));

        $context = $this->checkout(['address' => ['street' => 'Elm', 'house' => '5']]);
        $this->assertSame([], $this->slot->check($context, Purpose::Checkout));
        $this->assertSame([], $this->slot->check($context, Purpose::Order));
        $this->assertArrayHasKey('delivery.quote', $this->slot->errorCodes());
    }

    #[Test]
    public function a_point_gone_after_the_choice_is_caught(): void
    {
        $point = $this->point($this->townA);
        $context = $this->checkout(['locality_id' => $this->townA->id, 'method_id' => $this->pickup->id, 'point_id' => $point->id]);
        $this->assertSame([], $this->slot->check($context, Purpose::Checkout));

        $point->forceFill(['active' => false])->save();

        $errors = $this->slot->check($this->checkout(), Purpose::Order);
        $this->assertSame(['delivery.point'], self::codes($errors));
        $this->assertSame('Pick-up point is no longer available, choose another one.', $errors[0]->message);
    }

    #[Test]
    public function the_summary_the_totals_line_and_the_choice_for_payment(): void
    {
        $this->assertNull($this->slot->summary($this->checkout()));
        $this->assertNull($this->slot->totalsLine($this->checkout()));
        $this->assertNull($this->slot->choice($this->checkout()));

        $context = $this->checkout(['locality_id' => $this->townA->id, 'method_id' => $this->courier->id, 'address' => ['street' => 'Elm', 'house' => '5']]);
        $this->assertSame("Our courier, Town A · 5,00\u{a0}€", $this->slot->summary($context));
        $this->assertEquals(new ShippingLine(ShippingLine::FIXED, amount: 500), $this->slot->totalsLine($context));
        $this->assertFalse($this->slot->choice($context)?->storePickup);

        $context = $this->checkout(['method_id' => $this->pickup->id, 'point_id' => $this->point($this->townA)->id]);
        $this->assertEquals(new ShippingLine(ShippingLine::FREE), $this->slot->totalsLine($context));
        $choice = $this->slot->choice($context);
        $this->assertNotNull($choice);
        $this->assertTrue($choice->storePickup);
        $this->assertSame('store-pickup', $choice->method);
        $this->assertSame('store', $choice->carrier);
    }

    #[Test]
    public function the_order_keeps_what_was_chosen_in_the_words_of_the_moment(): void
    {
        $point = $this->point($this->townA, 'store', ['titles' => ['en' => 'Store C']]);
        $block = $this->slot->snapshot($this->checkout(['locality_id' => $this->townA->id, 'method_id' => $this->pickup->id, 'point_id' => $point->id]));

        $this->assertSame('store-pickup', $block['method']);
        $this->assertSame('store.pickup', $block['type']);
        $this->assertSame('store', $block['carrier']);
        // In every language of the moment, as §11.1 has it: the order never reads the method again.
        $this->assertSame(['en' => 'Pick up at a store'], $block['title']);
        $this->assertSame(['id' => $this->townA->id, 'name' => ['en' => 'Town A'], 'kind' => 'town', 'region' => ['en' => 'Region A']], $block['locality']);
        $this->assertSame($point->id, $block['point']['id']);
        $this->assertSame(['en' => 'Store C'], $block['point']['title']);
        $this->assertSame(['kind' => 'free', 'amount' => 0, 'was' => null, 'estimate' => null, 'currency' => 'EUR', 'in_total' => false], $block['price']);
        $this->assertSame('sender', $block['payer']);
        $this->assertFalse($block['paid_by_store']);
        $this->assertSame('XX', $block['country']);
        $this->assertNull($block['address']);
        // The order accepts it by the delivery's own rules (§6.4 of the orders spec).
        $this->assertSame([], DeliverySnapshot::validate($block));
        $this->assertSame('Pick up at a store, Town A · Free', DeliverySnapshot::summary($block, 'en'));
    }
}
