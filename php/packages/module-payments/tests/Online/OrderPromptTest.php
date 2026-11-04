<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Site\OrderLinks;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Testing\FakeGatewaySite;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * The order's page while it awaits an online payment (spec §8.4) and «Choose another method»
 * (§8.5): the buyer reads «Awaiting payment» with the way to pay, not «Accepted, we will be in
 * touch»; the list holds every method of the order, offline ones by their delivery rule.
 */
final class OrderPromptTest extends OnlineCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PaymentMethod::query()->whereIn('key', [OnReceipt::METHOD, BankTransfer::METHOD])->update(['enabled' => true]);
    }

    #[Test]
    public function an_order_awaiting_an_online_payment_says_so_with_both_buttons(): void
    {
        $order = $this->onlineOrder();

        $this->orderPage($order)
            ->assertOk()
            ->assertSee('Awaiting payment')
            ->assertSee('webx-payments-prompt', false)
            ->assertSee('Choose another method')
            ->assertSee(e($this->app->make(PaymentLinks::class)->attempts($order)), false)
            ->assertDontSee('The order is waiting for payment.')
            ->assertDontSee('We have your order and will be in touch');
    }

    #[Test]
    public function a_payment_being_checked_gets_no_second_pay(): void
    {
        [$order, $payment] = $this->attemptAtGateway();
        $payment->forceFill(['status' => 'pending'])->save();

        $this->orderPage($order)
            ->assertSee('We are checking the payment')
            ->assertSee('Check again')
            ->assertDontSee(e($this->app->make(PaymentLinks::class)->attempts($order)), false);
    }

    #[Test]
    public function a_paid_order_is_back_to_the_shops_words(): void
    {
        [$order, $payment] = $this->attemptAtGateway();
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));

        $this->orderPage($order->refresh())
            ->assertSee('We have your order and will be in touch')
            ->assertDontSee('webx-payments-prompt', false);
    }

    #[Test]
    public function an_order_on_receipt_keeps_the_shops_words(): void
    {
        $order = $this->place($this->cart(), [
            'blocks' => ['payment' => ['method' => OnReceipt::METHOD, 'type' => OnReceipt::KEY, 'kind' => 'offline', 'title' => ['en' => 'On receipt'], 'price' => null]],
        ]);

        $this->orderPage($order)->assertSee('We have your order and will be in touch')->assertDontSee('webx-payments-prompt', false);
    }

    #[Test]
    public function the_list_holds_the_offline_methods_too(): void
    {
        $order = $this->onlineOrder();

        $this->get($this->payPage($order))
            ->assertSee('value="'.OnReceipt::METHOD.'"', false)
            ->assertSee('value="'.BankTransfer::METHOD.'"', false)
            ->assertSee('value="gateway-a-form"', false)
            ->assertDontSee('name="method" value="gateway-a-card"', false);
    }

    #[Test]
    public function a_delivery_with_no_money_at_hand_leaves_on_receipt_out(): void
    {
        $order = $this->onlineOrder();
        PaymentMethod::query()->where('key', OnReceipt::METHOD)->update(['delivery_rule' => json_encode(['mode' => 'on_hand'])]);
        $order->forceFill(['delivery' => ['method' => 'courier', 'type' => 'store.courier', 'carrier' => 'store', 'title' => ['en' => 'Courier'], 'price' => 0]])->save();

        $this->get($this->payPage($order))->assertDontSee('value="'.OnReceipt::METHOD.'"', false)->assertSee('value="'.BankTransfer::METHOD.'"', false);

        $order->forceFill(['delivery' => ['method' => 'pickup', 'type' => 'store.pickup', 'carrier' => 'store', 'title' => ['en' => 'Pick-up'], 'price' => 0]])->save();

        $this->get($this->payPage($order))->assertSee('value="'.OnReceipt::METHOD.'"', false);
    }

    #[Test]
    public function going_over_to_on_receipt_stops_the_waiting_and_opens_no_attempt(): void
    {
        $order = $this->onlineOrder();

        $this->post($this->app->make(PaymentLinks::class)->method($order), ['method' => OnReceipt::METHOD])
            ->assertRedirect($this->payPage($order));

        $order->refresh();
        $this->assertSame(OnReceipt::METHOD, $order->payment['method']);
        $this->assertFalse($order->awaiting_payment);
        $this->assertSame(0, Payment::query()->where('order_id', $order->id)->count());
        $this->get($this->payPage($order))->assertSee('The order is paid on receipt');
        $this->orderPage($order)->assertSee('We have your order and will be in touch');
    }

    #[Test]
    public function going_over_to_bank_details_shows_them(): void
    {
        $order = $this->onlineOrder();

        $this->post($this->app->make(PaymentLinks::class)->method($order), ['method' => BankTransfer::METHOD])->assertRedirect();

        $order->refresh();
        $this->assertSame(BankTransfer::METHOD, $order->payment['method']);
        $this->assertTrue($order->awaiting_payment);
        $this->orderPage($order)->assertDontSee('webx-payments-prompt', false)->assertSee('The order is waiting for payment.');
    }

    #[Test]
    public function a_paid_order_keeps_the_block_of_its_first_payment(): void
    {
        [$order, $payment] = $this->attemptAtGateway();
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));

        $this->post($this->app->make(PaymentLinks::class)->method($order->refresh()), ['method' => OnReceipt::METHOD])->assertRedirect();

        $this->assertSame('gateway-a-card', $order->refresh()->payment['method']);
    }

    /** @return TestResponse<Response> */
    private function orderPage(Order $order): TestResponse
    {
        return $this->get($this->app->make(OrderLinks::class)->page((string) $order->number, (string) $order->locale));
    }
}
