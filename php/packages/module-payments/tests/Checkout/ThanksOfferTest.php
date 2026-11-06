<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Checkout;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Cart\AddResult;
use WebxUi\Orders\Checkout\Slots\ShippingLine;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Tests\Checkout\UsesFakeSlots;
use WebxUi\Orders\Tests\Checkout\VisitsCheckout;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Testing\FakeGatewaySite;
use WebxUi\Payments\Tests\Online\OnlineCase;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * «Pay online now» on «Thank you» (spec §8.4, groups spec §6.8): the page prints the order's body,
 * so the block of the order's page comes with it, and the form works from here because its address
 * is signed — the session that knows the buyer on this page is not asked. Placed through the
 * checkout: the page is only for the session that placed the order.
 */
final class ThanksOfferTest extends OnlineCase
{
    use UsesFakeSlots;
    use VisitsCheckout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableBuiltIns();
        $this->installSlots(shipping: true, payment: false);
        $this->shipping->choose('store-pickup', new ShippingLine(ShippingLine::FREE), 'Pick up at the store', 'store', pickup: true);
    }

    #[Test]
    public function an_order_on_receipt_is_offered_to_pay_online_now_with_the_list_of_the_order_page(): void
    {
        [, $order, $page] = $this->placedOnReceipt();
        $html = (string) $page->getContent();

        $page->assertOk()
            ->assertSee('Pay online now')
            ->assertSee(e($this->app->make(PaymentLinks::class)->method($order)), false)
            ->assertSee('data-webx-payments-methods', false)
            ->assertSee('value="gateway-a-card"', false)
            ->assertSee('data-method="'.BankTransfer::METHOD.'"', false)
            ->assertDontSee('data-method="'.OnReceipt::METHOD.'"', false)
            ->assertDontSee('webx-orders-thanks__pay', false);

        $this->assertMatchesRegularExpression('/<details class="webx-payments-others"\s+open\s*>/', $html);
        // Apple Pay alone is taken away by the script, so without it the row would stay on Android.
        $this->assertSame(1, substr_count($html, 'webx/payments/methods.js'));
    }

    #[Test]
    public function the_form_of_thanks_takes_the_order_to_the_gateway_and_leaves_its_contents_alone(): void
    {
        [$cart, $order, $page] = $this->placedOnReceipt();
        $lines = $order->items()->pluck('name')->all();
        $payable = $order->payable;

        $this->visit($cart, $this->action($page), ['group' => 'online', 'method' => 'gateway-a-card'])
            ->assertRedirectContains('/pay/go/');

        $payment = Payment::query()->sole();
        $this->get($this->app->make(PaymentLinks::class)->go($payment));
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));

        $this->visit($cart, '/pay/return/'.$payment->uuid)->assertRedirect('/checkout/thanks/'.$order->number);
        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()
            ->assertSee('Payment received')
            ->assertDontSee('Pay online now')
            ->assertDontSee('webx-payments-others', false);

        $order->refresh();
        $this->assertNotNull($order->paid_at);
        $this->assertSame('gateway-a-card', $order->payment['method']);
        $this->assertSame($payable, $order->payable);
        $this->assertSame($payable, $payment->amount);
        $this->assertSame($lines, $order->items()->pluck('name')->all());
    }

    #[Test]
    public function a_paid_order_on_receipt_is_offered_nothing(): void
    {
        [$cart, $order] = $this->placedOnReceipt();
        $order->forceFill(['paid_at' => now()])->save();

        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()
            ->assertDontSee('Pay online now')
            ->assertDontSee('webx-payments-others', false);
    }

    #[Test]
    public function an_order_on_receipt_with_nothing_online_is_offered_nothing(): void
    {
        PaymentMethod::query()->where('key', 'like', 'gateway-a-%')->update(['enabled' => false]);

        [, , $page] = $this->placedOnReceipt();

        $page->assertOk()
            ->assertDontSee('Pay online now')
            ->assertDontSee('webx-payments-others', false);
    }

    #[Test]
    public function an_order_awaiting_an_online_payment_keeps_its_own_way_to_pay(): void
    {
        $cart = $this->cart();
        $this->visit($cart, '/api/payments/selection', ['_method' => 'PUT', 'method' => 'gateway-a-card'], json: true)->assertOk();
        $this->fill($cart);
        $this->submit($cart)->assertRedirect();
        $order = Order::query()->sole();

        $this->visit($cart, '/checkout/thanks/'.$order->number)->assertOk()
            ->assertSee('Awaiting payment')
            ->assertSee('Choose another method')
            ->assertDontSee('Pay online now');
    }

    /**
     * Placed on receipt, and «Thank you» as its buyer opens it.
     *
     * @return array{0: AddResult, 1: Order, 2: TestResponse<Response>}
     */
    private function placedOnReceipt(): array
    {
        $cart = $this->cart();
        $this->visit($cart, '/api/payments/selection', ['_method' => 'PUT', 'method' => OnReceipt::METHOD], json: true)->assertOk();
        $this->fill($cart);
        $this->submit($cart)->assertRedirect();
        $order = Order::query()->sole();

        return [$cart, $order, $this->visit($cart, '/checkout/thanks/'.$order->number)];
    }

    /**
     * Where the page's own form posts: what the buyer's browser would.
     *
     * @param  TestResponse<Response>  $page
     */
    private function action(TestResponse $page): string
    {
        $this->assertSame(1, preg_match('/<form class="webx-payments__form" method="post" action="([^"]+)"/', (string) $page->getContent(), $match));

        return html_entity_decode($match[1]);
    }
}
