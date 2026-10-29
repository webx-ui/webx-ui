<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Checkout\Slots\ShippingLine;
use WebxUi\Orders\Checkout\Slots\Slot;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Tests\Fixtures\FakePaymentSlot;
use WebxUi\Orders\Tests\Fixtures\FakeShippingSlot;

/**
 * Delivery and payment in the checkout (spec §22): the line «Delivery cost», a slot that fails
 * while the buyer looks and on the confirmation, the block of each in the order, an order that
 * awaits online payment, and where the buyer goes after it.
 */
final class CheckoutSlotsTest extends CheckoutTestCase
{
    use UsesFakeSlots;

    protected function setUp(): void
    {
        parent::setUp();

        $this->installSlots();
    }

    #[Test]
    public function the_registry_takes_two_slots_of_their_own_kinds_once(): void
    {
        $slots = new CheckoutSlots;
        $slots->register(new FakeShippingSlot);
        $slots->register(new FakePaymentSlot);

        $this->assertInstanceOf(FakeShippingSlot::class, $slots->shipping());
        $this->assertInstanceOf(FakePaymentSlot::class, $slots->payment());

        $this->expectException(LogicException::class);
        $slots->register(new FakeShippingSlot);
    }

    #[Test]
    public function a_slot_under_the_delivery_key_is_a_shipping_slot(): void
    {
        $payment = new FakePaymentSlot;
        $asDelivery = new class($payment) implements Slot
        {
            public function __construct(private readonly FakePaymentSlot $inner) {}

            public function key(): string
            {
                return 'delivery';
            }

            public function needed(CheckoutContext $context): bool
            {
                return true;
            }

            public function render(CheckoutContext $context): Htmlable
            {
                return $this->inner->render($context);
            }

            public function summary(CheckoutContext $context): ?string
            {
                return null;
            }

            public function check(CheckoutContext $context, Purpose $purpose): array
            {
                return [];
            }

            public function snapshot(CheckoutContext $context): array
            {
                return [];
            }
        };

        $this->expectException(InvalidArgumentException::class);
        (new CheckoutSlots)->register($asDelivery);
    }

    #[Test]
    public function a_third_slot_is_a_new_block_of_the_order_and_not_a_registration(): void
    {
        $this->expectExceptionMessage('The checkout has no slot «gift»');
        (new CheckoutSlots)->register(new class implements Slot
        {
            public function key(): string
            {
                return 'gift';
            }

            public function needed(CheckoutContext $context): bool
            {
                return true;
            }

            public function render(CheckoutContext $context): Htmlable
            {
                return new HtmlString('');
            }

            public function summary(CheckoutContext $context): ?string
            {
                return null;
            }

            public function check(CheckoutContext $context, Purpose $purpose): array
            {
                return [];
            }

            public function snapshot(CheckoutContext $context): array
            {
                return [];
            }
        });
    }

    #[Test]
    public function the_delivery_cost_is_the_slots_line_and_not_a_discount(): void
    {
        $cart = $this->cart();
        $money = $this->app->make(Money::class);

        // Nothing chosen yet.
        $this->visit($cart, '/checkout/summary')->assertOk()
            ->assertSee('Delivery cost')
            ->assertSee('choose a method')
            ->assertSee('name="expected_payable" value="4999"', false);

        // A fixed price: in the amount to pay, its own line, not a discount.
        $this->shipping->choose('courier', new ShippingLine(ShippingLine::FIXED, 500));
        $this->visit($cart, '/checkout/summary')->assertOk()
            ->assertSee('data-kind="fixed"', false)
            ->assertSee($money->format(500, 'EUR'))
            ->assertDontSee('Discount')
            ->assertSee('name="expected_payable" value="5499"', false);

        // Free, with the price it would have cost crossed out.
        $this->shipping->choose('courier', new ShippingLine(ShippingLine::FREE, was: 500));
        $this->visit($cart, '/checkout/summary')->assertOk()
            ->assertSee('Free <s class="webx-orders-checkout__was">'.e($money->format(500, 'EUR')).'</s>', false)
            ->assertSee('name="expected_payable" value="4999"', false);

        // At the carrier's rates: an estimate, and not in the amount to pay.
        $this->shipping->choose('carrier-a-door', new ShippingLine(ShippingLine::CARRIER_RATES, estimate: 800));
        $this->visit($cart, '/checkout/summary')->assertOk()
            ->assertSee('at carrier rates ≈ '.$money->format(800, 'EUR'))
            ->assertSee('+ delivery at carrier rates')
            ->assertSee('name="expected_payable" value="4999"', false);
    }

    #[Test]
    public function a_slot_that_fails_while_the_buyer_looks_leaves_the_page_alive(): void
    {
        $log = Log::spy();
        $cart = $this->cart();

        $this->shipping->throwIn = 'render';
        $this->visit($cart, '/checkout')->assertOk()
            ->assertSeeInOrder(['id="checkout-step-delivery"', 'Could not load this step. Try again later.', 'id="checkout-step-payment"', 'class="fake-payment"'], false);

        // Not even known whether it is needed: the section is there and says so.
        $this->shipping->throwIn = 'needed';
        $this->visit($cart, '/checkout')->assertOk()
            ->assertSeeInOrder(['id="checkout-step-delivery"', 'Could not load this step. Try again later.'], false)
            ->assertDontSee('Delivery cost');

        // The payment slot's turn.
        $this->shipping->throwIn = null;
        $this->payment->throwIn = 'summary';
        $this->payment->choose('card', online: true);
        $this->visit($cart, '/checkout')->assertOk()->assertSee('class="fake-payment"', false);

        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => str_contains($message, '«delivery» failed in render()'))->once();
        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => str_contains($message, '«delivery» failed in needed()'))->atLeast()->once();
        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => str_contains($message, '«payment» failed in summary()'))->atLeast()->once();
    }

    #[Test]
    public function a_slot_that_fails_or_refuses_on_the_confirmation_makes_no_order(): void
    {
        $log = Log::spy();
        $cart = $this->cart();
        $this->fill($cart);
        $this->payment->choose('card', online: false);

        // Nothing chosen.
        $this->submit($cart, json: true)->assertStatus(422)
            ->assertJsonPath('errors.method', ['Choose a delivery method.']);

        // The strict check: the point is gone since.
        $this->shipping->choose('carrier-a-branch', new ShippingLine(ShippingLine::FIXED, 500));
        $this->shipping->pointGone = true;
        $this->submit($cart, json: true)->assertStatus(422)
            ->assertJsonPath('errors.point', ['The pickup point no longer works, choose another one.']);

        // The service is down while it checks, and while it writes its block.
        $this->shipping->pointGone = false;

        foreach (['check', 'snapshot', 'needed'] as $method) {
            $this->shipping->throwIn = $method;
            $this->submit($cart, json: true)->assertStatus(422)
                ->assertJsonPath('errors.delivery', ['This step could not be checked. Try again or choose another option.']);
        }

        $this->payment->throwIn = 'awaitsPayment';
        $this->shipping->throwIn = null;
        $this->submit($cart, json: true)->assertStatus(422)
            ->assertJsonPath('errors.payment', ['This step could not be checked. Try again or choose another option.']);

        $this->assertSame(0, Order::query()->count());

        // Both answer again: the same cart makes its order.
        $this->payment->throwIn = null;
        $this->submit($cart, json: true)->assertOk();
        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function the_order_gets_both_blocks_and_awaits_an_online_payment(): void
    {
        $cart = $this->cart();
        $this->fill($cart);
        $this->shipping->choose('courier', new ShippingLine(ShippingLine::FIXED, 500), title: 'Courier');
        $this->payment->choose('card', online: true, title: 'Card');

        $this->submit($cart, json: true)->assertOk();

        $order = Order::query()->sole();
        $this->assertTrue($order->awaiting_payment);
        $this->assertSame(5499, $order->payable);
        $this->assertSame('courier', $order->delivery['method'] ?? null);
        $this->assertSame(['en' => 'Card'], $order->payment['title'] ?? null);

        $other = $this->cart();
        $this->fill($other);
        $this->payment->choose('cash', online: false, title: 'Cash');
        $this->submit($other, json: true)->assertOk();

        $this->assertFalse(Order::query()->latest('id')->firstOrFail()->awaiting_payment);
    }

    #[Test]
    public function after_the_order_the_buyer_goes_where_the_payment_says(): void
    {
        $this->shipping->choose('courier', new ShippingLine(ShippingLine::FIXED, 500));
        $this->payment->choose('card', online: true);

        // A gateway: straight there; «Thank you» waits for the return.
        $this->payment->gateway = 'https://pay.example.test/go/1';
        $cart = $this->cart();
        $this->fill($cart);
        $this->submit($cart, json: true)->assertOk()->assertJsonPath('redirect', 'https://pay.example.test/go/1');
        $order = Order::query()->sole();
        $this->assertSame([$order->number], $this->payment->nexts);
        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()
            ->assertSee('href="https://pay.example.test/again/'.$order->number.'"', false)
            ->assertSee('Pay');

        // No gateway: «Thank you».
        $this->payment->gateway = null;
        $cart = $this->cart();
        $this->fill($cart);
        $placed = $this->submit($cart, json: true)->assertOk();
        $order = Order::query()->latest('id')->firstOrFail();
        $placed->assertJsonPath('redirect', url('/checkout/thanks/'.$order->number));

        // The gateway is down: the order stands, «Thank you» with «Pay».
        $log = Log::spy();
        $this->payment->throwIn = 'next';
        $cart = $this->cart();
        $this->fill($cart);
        $placed = $this->submit($cart, json: true)->assertOk();
        $order = Order::query()->latest('id')->firstOrFail();
        $placed->assertJsonPath('redirect', url('/checkout/thanks/'.$order->number));
        $this->assertTrue($order->awaiting_payment);
        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => str_contains($message, '«payment» failed in next()'))->once();

        // Paid by cash: nothing to pay online, no «Pay».
        $this->payment->throwIn = null;
        $this->payment->choose('cash', online: false);
        $cart = $this->cart();
        $this->fill($cart);
        $this->submit($cart, json: true)->assertOk();
        $order = Order::query()->latest('id')->firstOrFail();
        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()->assertDontSee('pay.example.test/again', false);
    }
}
