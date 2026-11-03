<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Orders;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Testing\FakeGatewayServer;

/**
 * The page of payment of an order (spec §8.4) as far as this stage goes: behind the order's signed
 * link, «Pay» with the order's online method — a new attempt and the step to the gateway —, the
 * reason after a failure, and the states without a button: paid, cancelled, «we are checking».
 */
final class PayPageTest extends OnlineCase
{
    #[Test]
    public function the_page_offers_to_pay_and_pay_goes_to_the_gateway(): void
    {
        $order = $this->onlineOrder();

        $this->get($this->payPage($order))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Payment for order '.$order->number)
            ->assertSee('To pay: 49,99')
            ->assertSee('Pay — Card (gateway A)')
            ->assertSee('noindex', false);

        $response = $this->post($this->app->make(PaymentLinks::class)->attempts($order));
        $payment = Payment::query()->sole();
        $response->assertRedirect($this->app->make(PaymentLinks::class)->go($payment));
        $this->assertSame([PaymentStatus::Created, 'pay-page'], [$payment->status, $payment->source]);

        $this->get($this->app->make(PaymentLinks::class)->go($payment))->assertRedirect(route('webx.payments.fake.page', ['uuid' => $payment->uuid]));
        $this->get($this->payPage($order))->assertSee('We are checking the payment')->assertDontSee('Pay — Card');
    }

    #[Test]
    public function a_link_without_its_signature_says_nothing(): void
    {
        $order = $this->onlineOrder();

        $this->get('/pay/'.$order->number)->assertNotFound();
        $this->post('/pay/'.$order->number.'/attempts')->assertNotFound();
        $this->get('/pay/go/'.str_repeat('0', 26))->assertNotFound();
        $this->assertSame(0, Payment::query()->count());
    }

    #[Test]
    public function a_failed_attempt_is_said_and_pay_is_offered_again(): void
    {
        $order = $this->onlineOrder();
        $this->post($this->app->make(PaymentLinks::class)->attempts($order));
        $this->server()->failNext(FakeGatewayServer::SERVER_ERROR);
        $this->followingRedirects()->get($this->app->make(PaymentLinks::class)->go(Payment::query()->sole()))
            ->assertSee('Could not open the payment')
            ->assertSee('The payment did not go through')
            ->assertSee('Pay — Card (gateway A)');
    }

    #[Test]
    public function a_paid_or_cancelled_order_has_no_button(): void
    {
        $paid = $this->onlineOrder();
        $this->app->make(Payments::class)->markPaid($paid, 'panel');
        $this->get($this->payPage($paid->refresh()))->assertSee('Order '.$paid->number.' is paid')->assertDontSee('Pay —');
        $this->post($this->app->make(PaymentLinks::class)->attempts($paid))->assertRedirect($this->payPage($paid));
        $this->assertSame(1, Payment::query()->count(), 'A paid order took a new attempt.');

        $cancelled = $this->onlineOrder();
        $this->app->make(Orders::class)->cancel($cancelled, 'panel', 'The buyer changed their mind');
        $this->get($this->payPage($cancelled->refresh()))->assertSee('Order '.$cancelled->number.' is cancelled')->assertDontSee('Pay —');
    }
}
