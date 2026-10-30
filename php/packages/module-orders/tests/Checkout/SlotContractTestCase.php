<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Contracts\Support\Htmlable;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\AddResult;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Blocks\OrderBlocks;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Checkout\Slots\PaymentSlot;
use WebxUi\Orders\Checkout\Slots\ShippingChoice;
use WebxUi\Orders\Checkout\Slots\ShippingLine;
use WebxUi\Orders\Checkout\Slots\ShippingSlot;
use WebxUi\Orders\Checkout\Slots\Slot;
use WebxUi\Orders\Checkout\Steps\StepError;
use WebxUi\Orders\Data\OrderData;

/**
 * What every slot of the checkout keeps to (spec §22, §34): the fakes of this package pass it, and
 * `module-delivery` and `module-payments` extend it for their own slots — so the checkout tested
 * with the fakes is the checkout their slots get.
 *
 * A subclass gives the slot and makes a valid choice for the cart; the cart is one line of a
 * product that goes by mail, in euros (`PlaceTestCase`), unless the subclass says otherwise.
 */
abstract class SlotContractTestCase extends CheckoutTestCase
{
    /** The slot under test, a fresh one: nothing chosen for any cart. */
    abstract protected function slot(): Slot;

    /** Chooses what the buyer would for this checkout, as the slot's own API does. */
    abstract protected function choose(Slot $slot, CheckoutContext $context): void;

    /** The cart the slot is shown for. */
    protected function slotCart(): AddResult
    {
        return $this->cart();
    }

    protected function context(AddResult $cart): CheckoutContext
    {
        $state = $this->app->make(Calculator::class)->calculate($cart->cart, 'en', Purpose::Checkout);

        return new CheckoutContext($cart->cart, $state, null, null, 'en', $state->currency);
    }

    #[Test]
    public function the_slot_has_a_key_of_the_checkout_and_the_contract_of_it(): void
    {
        $slot = $this->slot();
        (new CheckoutSlots)->register($slot);

        $this->assertContains($slot->key(), array_keys(CheckoutSlots::POSITIONS));
        $this->assertInstanceOf($slot->key() === CheckoutSlots::DELIVERY ? ShippingSlot::class : PaymentSlot::class, $slot);
    }

    #[Test]
    public function with_nothing_chosen_the_step_is_shown_and_not_ready(): void
    {
        $slot = $this->slot();
        $context = $this->context($this->slotCart());

        $this->assertTrue($slot->needed($context));
        $this->assertInstanceOf(Htmlable::class, $slot->render($context));
        $this->assertNotSame('', trim($slot->render($context)->toHtml()));
        $this->assertNull($slot->summary($context));

        $errors = $slot->check($context, Purpose::Checkout);
        $this->assertNotSame([], $errors);
        $this->assertNotSame([], $slot->check($context, Purpose::Order));

        foreach ($errors as $error) {
            $this->assertInstanceOf(StepError::class, $error);
            $this->assertSame($slot->key(), $error->step);
            $this->assertStringStartsWith($slot->key().'.', $error->code);
            $this->assertNotSame('', $error->message);
        }

        if ($slot instanceof ShippingSlot) {
            $this->assertNull($slot->totalsLine($context));
            $this->assertNull($slot->choice($context));
        }
    }

    #[Test]
    public function a_choice_makes_the_step_ready_and_gives_the_order_its_block(): void
    {
        $slot = $this->slot();
        $context = $this->context($this->slotCart());
        $this->choose($slot, $context);

        $this->assertSame([], $slot->check($context, Purpose::Checkout));
        $this->assertSame([], $slot->check($context, Purpose::Order));
        $this->assertNotSame('', (string) $slot->summary($context));

        $block = $slot->snapshot($context);
        $this->assertIsString($block['method'] ?? null);
        $this->assertIsArray($block['title'] ?? null);

        // The owner's own rules of the block accept it (§6.4), when the owner registered them.
        $blocks = $this->app->make(OrderBlocks::class);

        if ($blocks->has($slot->key())) {
            $blocks->check([$slot->key() => $block]);
        }

        if ($slot instanceof ShippingSlot) {
            $this->assertInstanceOf(ShippingChoice::class, $slot->choice($context));
            $line = $slot->totalsLine($context);
            $this->assertInstanceOf(ShippingLine::class, $line);
        }

        if ($slot instanceof PaymentSlot) {
            // Asked inside place(): it answers for a valid choice.
            $slot->awaitsPayment($context);
        }
    }

    #[Test]
    public function after_the_order_the_payment_slot_answers_and_does_not_throw(): void
    {
        $slot = $this->slot();

        if (! $slot instanceof PaymentSlot) {
            $this->expectNotToPerformAssertions();

            return;
        }

        $data = OrderData::of($this->place($this->slotCart()));
        $next = $slot->next($data);
        $again = $slot->payAgainUrl($data);

        $this->assertTrue($next === null || filter_var($next, FILTER_VALIDATE_URL) !== false || str_starts_with($next, '/'));
        $this->assertTrue($again === null || $again !== '');
    }
}
