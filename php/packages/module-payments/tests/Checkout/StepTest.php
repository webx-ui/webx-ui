<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Checkout;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\AddResult;
use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\CheckoutContext;
use WebxUi\Orders\Checkout\CheckoutContexts;
use WebxUi\Orders\Checkout\Slots\CheckoutSlots;
use WebxUi\Orders\Checkout\Slots\ShippingLine;
use WebxUi\Orders\Checkout\Slots\SlotStep;
use WebxUi\Orders\Checkout\Steps\StepError;
use WebxUi\Orders\Tests\Checkout\UsesFakeSlots;
use WebxUi\Payments\Checkout\PaymentsCheckoutSlot;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Models\PaymentSelection;
use WebxUi\Payments\Selections;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * The payment step (spec §10.1, §7.3, §12): found by the checkout without being told, shown only
 * when there is something to pay, its methods following the delivery, a choice the delivery took
 * away dropped with a word to the buyer, and the order's block with the bank details.
 */
final class StepTest extends PaymentsCheckoutCase
{
    use UsesFakeSlots;

    private PaymentsCheckoutSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableBuiltIns();
        $this->slot = $this->app->make(PaymentsCheckoutSlot::class);
    }

    #[Test]
    public function the_checkout_finds_it_in_its_slot_without_being_told(): void
    {
        $this->assertInstanceOf(PaymentsCheckoutSlot::class, $this->app->make(CheckoutSlots::class)->payment());
    }

    #[Test]
    public function with_nothing_to_pay_there_is_no_step(): void
    {
        $cart = $this->cart();
        $context = $this->checkoutOf($cart, self::courier());
        $zero = new CheckoutContext($context->cart, new CartState(
            currency: 'EUR', locale: 'en', lines: $context->state->lines, lineAdjustments: [], subtotal: 4999, adjustments: [],
            total: 4999, tenders: [], payable: 0, benefits: [], hints: [], warnings: [], money: $this->app->make(Money::class),
        ), null, null, 'en', 'EUR', static fn () => self::courier());

        $this->assertTrue($this->slot->needed($context));
        $this->assertFalse($this->slot->needed($zero));
        $this->assertSame([], $this->app->make(Methods::class)->available($this->slot->paymentContext($zero)));
    }

    #[Test]
    public function at_the_shops_point_on_receipt_reads_pay_at_the_counter(): void
    {
        $html = $this->slot->render($this->checkoutOf($this->cart(), self::pickup()))->toHtml();

        $this->assertStringContainsString('Pay at the counter', $html);
        $this->assertStringContainsString('Bank transfer', $html);
        $this->assertStringContainsString('data-checkout-event="'.SlotStep::CHANGED.'"', $html);
    }

    #[Test]
    public function by_a_carrier_without_cash_on_delivery_only_the_transfer_is_offered(): void
    {
        $options = $this->slot->state($this->checkoutOf($this->cart(), self::courier()))->options;

        $this->assertSame([BankTransfer::METHOD], array_map(static fn ($o): string => $o->key(), $options));
    }

    #[Test]
    public function cash_on_delivery_says_the_carrier_takes_its_own_fee(): void
    {
        $options = $this->slot->state($this->checkoutOf($this->cart(), self::courier(cod: true)))->options;

        $this->assertSame(OnReceipt::METHOD, $options[0]->key());
        $this->assertSame('Payment on receipt', $options[0]->title);
        $this->assertSame('The delivery service charges its own fee for cash on delivery', $options[0]->note);
    }

    #[Test]
    public function a_choice_the_new_delivery_takes_away_is_dropped_with_a_word(): void
    {
        $cart = $this->cart();
        $this->app->make(Selections::class)->put($cart->cart->id, OnReceipt::METHOD);
        $this->assertSame('Pay at the counter', $this->slot->summary($this->checkoutOf($cart, self::pickup())));

        $this->app->forgetScopedInstances();
        $context = $this->checkoutOf($cart, self::courier());
        $state = $this->slot->state($context);

        $this->assertNull($state->chosen);
        $this->assertTrue($state->dropped);
        $this->assertSame(0, PaymentSelection::query()->where('cart_id', $cart->cart->id)->count());
        $this->assertStringContainsString('not available for this delivery', $this->slot->render($context)->toHtml());
        $this->assertSame(['payment.method'], array_map(static fn (StepError $e): string => $e->code, $this->slot->check($context, Purpose::Order)));
    }

    #[Test]
    public function before_the_delivery_is_chosen_the_methods_that_depend_on_it_wait(): void
    {
        $this->installSlots(shipping: true, payment: false);
        $slot = $this->app->make(CheckoutSlots::class)->payment();
        $this->assertInstanceOf(PaymentsCheckoutSlot::class, $slot);
        $cart = $this->cart();
        $context = $this->checkoutWithFakeDelivery($cart);

        $state = $slot->state($context);

        $this->assertTrue($state->context->shippingPending);
        $this->assertTrue($state->waiting);
        $this->assertSame([BankTransfer::METHOD], array_map(static fn ($o): string => $o->key(), $state->options));
        $this->assertStringContainsString('Choose delivery first', $slot->render($context)->toHtml());

        // Chosen, the delivery opens the methods that waited for it.
        $this->shipping->choose('store-pickup', new ShippingLine(ShippingLine::FREE), 'Pick up', 'store', pickup: true);
        $slot->forget($context);
        $fresh = $this->checkoutWithFakeDelivery($cart);

        $this->assertSame([OnReceipt::METHOD, BankTransfer::METHOD], array_map(static fn ($o): string => $o->key(), $slot->state($fresh)->options));
        $this->assertFalse($slot->state($fresh)->waiting);
    }

    #[Test]
    public function a_step_with_every_method_waiting_already_brings_the_script_of_the_list(): void
    {
        // The redraw that brings the list inserts scripts the browser never runs, so this is the
        // only moment the page can get it.
        $this->installSlots(shipping: true, payment: false);
        PaymentMethod::query()->get()->each(static fn (PaymentMethod $method) => $method->update(['delivery_rule' => ['mode' => 'on_hand']]));
        $slot = $this->app->make(CheckoutSlots::class)->payment();
        $this->assertInstanceOf(PaymentsCheckoutSlot::class, $slot);
        $context = $this->checkoutWithFakeDelivery($this->cart());

        $this->assertSame([], $slot->state($context)->options);
        $this->assertTrue($slot->state($context)->waiting);

        $html = $slot->render($context)->toHtml();

        $this->assertStringContainsString('Choose delivery first', $html);
        $this->assertSame(1, substr_count($html, '/step.js'));
        $this->assertSame(1, substr_count($html, '/methods.js'));
    }

    #[Test]
    public function the_block_of_a_transfer_carries_the_details_in_every_language(): void
    {
        $cart = $this->cart();
        $this->app->make(Selections::class)->put($cart->cart->id, BankTransfer::METHOD);
        $context = $this->checkoutOf($cart, self::courier());

        $block = $this->slot->snapshot($context);

        $this->assertSame([
            'method' => 'bank-transfer',
            'type' => 'store.bank_transfer',
            'kind' => 'offline',
            'title' => ['en' => 'Bank transfer', 'ru' => 'По реквизитам', 'kk' => 'Bank transfer'],
            'price' => null,
            'instructions' => [
                'recipient' => ['en' => 'Shop A Ltd'],
                'account' => ['en' => 'Account 0000 0000 0000 0000, Bank A'],
                'purpose' => ['en' => 'Payment for order {number}', 'ru' => 'Оплата заказа {number}', 'kk' => 'Payment for order {number}'],
                'note' => ['en' => 'Pay within 3 days'],
            ],
        ], $block);
        $this->assertTrue($this->slot->awaitsPayment($context));

        $this->app->make(Selections::class)->put($cart->cart->id, OnReceipt::METHOD);
        $this->slot->forget($context);
        $atThePoint = $this->checkoutOf($cart, self::pickup());
        $this->assertFalse($this->slot->awaitsPayment($atThePoint));
        $this->assertSame('Pay at the counter', $this->slot->snapshot($atThePoint)['title']['en']);
    }

    #[Test]
    public function a_method_switched_off_in_the_panel_leaves_the_step_at_once(): void
    {
        $cart = $this->cart();
        $this->assertCount(2, $this->slot->state($this->checkoutOf($cart, self::pickup()))->options);

        PaymentMethod::query()->where('key', BankTransfer::METHOD)->firstOrFail()->update(['enabled' => false]);

        $this->assertSame([OnReceipt::METHOD], array_map(static fn ($o): string => $o->key(), $this->slot->state($this->checkoutOf($cart, self::pickup()))->options));
    }

    /** A checkout whose delivery is the fake slot of the orders' tests, as CheckoutContexts makes it. */
    private function checkoutWithFakeDelivery(AddResult $cart): CheckoutContext
    {
        $this->app->make(CheckoutContexts::class)->forget();

        return $this->app->make(CheckoutContexts::class)->of($cart->cart);
    }
}
