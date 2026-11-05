<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\BeginRefused;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Testing\FakeGatewaySite;
use WebxUi\Payments\Types\OnReceipt;

/**
 * A new attempt and what stood before it (spec §8.5, §9.4): an earlier attempt the gateway knows
 * is asked and closed — paid already, there is no new one; one the gateway cannot close stays,
 * and the page warns. A paid order that owes more after an edit is topped up by any online method
 * (decision 29); an order «on receipt» is paid online now by switching its method.
 */
final class AttemptsTest extends OnlineCase
{
    #[Test]
    public function an_earlier_attempt_is_closed_at_the_gateway_before_a_new_one(): void
    {
        [$order, $first] = $this->attemptAtGateway();

        $second = $this->begin($order);

        $this->assertSame([PaymentStatus::Cancelled, 'Closed for a new attempt'], [$first->refresh()->status, $first->failure_reason]);
        $this->assertSame('cancelled', $this->server()->payment($first->uuid)['status'] ?? null);
        $this->assertSame(PaymentStatus::Created, $second->status);
        $this->assertNull($order->refresh()->payment_failed_at, 'Closing for a new attempt is no failure of the order.');
        $this->assertSame(1, PaymentGatewayEvent::query()->where('kind', 'cancel')->where('payment_id', $first->id)->count());
    }

    #[Test]
    public function an_earlier_attempt_paid_meanwhile_refuses_the_new_one(): void
    {
        [$order, $first] = $this->attemptAtGateway();
        $this->server()->act($first->uuid, 'pay');

        try {
            $this->begin($order);
            $this->fail('A second attempt was opened for a paid order.');
        } catch (BeginRefused $refused) {
            $this->assertSame(BeginRefused::PAID, $refused->reason);
        }

        $this->assertSame(PaymentStatus::Paid, $first->refresh()->status);
        $this->assertNotNull($order->refresh()->paid_at);
        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());
    }

    #[Test]
    public function an_attempt_the_gateway_cannot_close_stays_and_the_page_warns(): void
    {
        [$order, $first] = $this->attemptAtGateway('unsigned');

        $second = $this->begin($order);

        $this->assertSame(PaymentStatus::Pending, $first->refresh()->status);
        $this->assertSame(PaymentStatus::Created, $second->status);
        $this->get($this->payPage($order))->assertOk()->assertSee('If you have already paid in another tab');
    }

    #[Test]
    public function money_held_refuses_a_new_attempt(): void
    {
        [$order, $payment] = $this->attemptAtGateway(hold: true);
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));
        $this->assertSame(PaymentStatus::Authorized, $payment->refresh()->status);

        $this->expectExceptionObject(new BeginRefused(BeginRefused::HELD));
        $this->begin($order);
    }

    #[Test]
    public function a_paid_order_that_owes_more_is_topped_up_by_another_online_method(): void
    {
        [$order, $payment] = $this->attemptAtGateway();
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));
        $this->assertNotNull($order->refresh()->paid_at);
        $this->owesMore($order, 1500);

        $page = $this->get($this->payPage($order))->assertOk();
        $page->assertSee('A top-up of')->assertSee('Top up — Gateway A page')->assertSee('Top up — Card (gateway A)');

        // Another online method than the order's: its block stays, the attempt keeps its method.
        $this->post($this->app->make(PaymentLinks::class)->attempts($order), ['method' => 'gateway-a-unsigned'])->assertRedirect();
        $topUp = Payment::query()->latest('id')->firstOrFail();
        $this->assertSame(['gateway-a-unsigned', 1500], [$topUp->method_key, $topUp->amount]);
        $this->get($this->app->make(PaymentLinks::class)->go($topUp));
        $this->server()->open($topUp->uuid, 'unsigned', 1500, 'EUR', 'CREATED');
        $this->server()->act($topUp->uuid, 'pay');
        $this->travel(6)->minutes();
        $this->artisan('webx:payments:poll')->assertSuccessful();

        $order->refresh();
        $this->assertSame([PaymentStatus::Paid, null], [$topUp->refresh()->status, $topUp->problem]);
        $this->assertSame(6499, $order->paid_total);
        $this->assertSame(0, Balance::of($order)->due);
        $this->assertSame(1, $order->milestones()->where('milestone', 'paid')->count());
        $this->assertSame('gateway-a-card', $order->payment['method'] ?? null);
        $this->get($this->payPage($order))->assertSee('is paid');
    }

    #[Test]
    public function a_top_up_takes_only_an_online_method_offered(): void
    {
        [$order, $payment] = $this->attemptAtGateway();
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));
        $this->owesMore($order->refresh(), 1500);

        $this->post($this->app->make(PaymentLinks::class)->attempts($order), ['method' => 'bank-transfer'])->assertRedirect($this->payPage($order));

        $this->assertSame(1, Payment::query()->where('order_id', $order->id)->count());
    }

    #[Test]
    public function an_order_on_receipt_is_paid_online_now(): void
    {
        $order = $this->place($this->cart(), [
            'awaitPayment' => false,
            'blocks' => ['payment' => ['method' => OnReceipt::METHOD, 'type' => OnReceipt::KEY, 'kind' => 'offline', 'title' => ['en' => 'On receipt'], 'price' => null]],
        ]);
        $this->assertFalse($order->awaiting_payment);

        $this->get($this->payPage($order))->assertOk()->assertSee('Pay online now')->assertSee('value="gateway-a-card"', false);

        $response = $this->post($this->app->make(PaymentLinks::class)->method($order), ['method' => 'gateway-a-card']);

        $payment = Payment::query()->where('order_id', $order->id)->sole();
        $response->assertRedirect($this->app->make(PaymentLinks::class)->go($payment));
        $order->refresh();
        $this->assertTrue($order->awaiting_payment);
        $this->assertSame(['gateway-a-card', 'gateway-a.card', 'online'], [$order->payment['method'] ?? null, $order->payment['type'] ?? null, $order->payment['kind'] ?? null]);
        $this->assertSame([PaymentStatus::Created, 4999], [$payment->status, $payment->amount]);

        // An offline method is not «online now».
        $this->post($this->app->make(PaymentLinks::class)->method($order), ['method' => 'bank-transfer'])->assertRedirect($this->payPage($order));
        $this->assertSame('gateway-a-card', $order->refresh()->payment['method'] ?? null);
    }

    private function begin(Order $order): Payment
    {
        $method = $this->app->make(Methods::class)->find('gateway-a-card');
        $this->assertNotNull($method);

        return $this->app->make(Payments::class)->begin($order, $method, 'pay-page');
    }

    /** An edit of the lines made the order dearer: the balance is what `Orders` would store. */
    private function owesMore(Order $order, int $amount): void
    {
        $order->forceFill(['payable' => $order->payable + $amount, 'total' => $order->total + $amount]);
        $order->balance = Balance::stored($order);
        $order->save();
    }
}
