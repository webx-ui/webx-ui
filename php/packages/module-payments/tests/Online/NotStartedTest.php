<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Online\Poll;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Reported;
use WebxUi\Payments\Testing\FakeGatewayServer;
use WebxUi\Payments\Testing\FakeGatewaySite;
use WebxUi\Payments\Types\BankTransfer;

/**
 * The buyer came back without beginning the payment (spec §8.4): the attempt stays open to its
 * deadline, the page says «Payment not completed» instead of «we are checking», and a late payment
 * still counts. «Choose another method» on this page, after a failure and with bank details.
 */
final class NotStartedTest extends OnlineCase
{
    /** @return array<string, array{0: string}> */
    public static function kinds(): array
    {
        return ['card' => ['card'], 'form' => ['form'], 'unsigned' => ['unsigned']];
    }

    #[Test]
    #[DataProvider('kinds')]
    public function the_buyer_who_left_the_gateways_page_sees_both_buttons(string $kind): void
    {
        [$order, $payment] = $this->left($kind);

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertTrue($payment->notStarted());
        $this->assertTrue($order->refresh()->awaiting_payment);

        $this->get($this->payPage($order))
            ->assertOk()
            ->assertSee('Payment not completed')
            ->assertSee('To pay: 49,99')
            ->assertSee('Pay again — ')
            ->assertSee('Choose another method')
            ->assertSee('webx-payments-others', false)
            ->assertSee('wait a minute before paying again')
            ->assertDontSee('We are checking')
            ->assertDontSee('http-equiv="refresh"', false);
    }

    #[Test]
    public function the_status_json_tells_a_script_to_stop_waiting(): void
    {
        [, $payment] = $this->left();

        $this->getJson(route('webx-payments.status', ['uuid' => $payment->uuid]))
            ->assertOk()
            ->assertExactJson(['status' => 'pending', 'final' => false, 'not_started' => true, 'declined' => false]);
    }

    #[Test]
    public function a_late_payment_from_the_tab_left_open_is_a_payment(): void
    {
        [$order, $payment] = $this->left();

        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertFalse($payment->notStarted());
        $this->assertArrayNotHasKey(Payment::NOT_STARTED, $payment->meta ?? []);
        $this->assertNotNull($order->refresh()->paid_at);
    }

    #[Test]
    public function the_page_itself_finds_a_late_payment_whose_notification_got_lost(): void
    {
        [$order, $payment] = $this->left();
        $this->get($this->payPage($order))->assertSee('Payment not completed');

        $this->server()->act($payment->uuid, 'pay');
        $this->travel(16)->seconds();

        $this->get($this->payPage($order))->assertSee('Order '.$order->number.' is paid')->assertDontSee('Pay again');
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
    }

    #[Test]
    public function any_other_answer_of_the_gateway_takes_the_mark_off(): void
    {
        [$order, $payment] = $this->left();
        $state = $this->server()->payment($payment->uuid);
        $this->assertNotNull($state);
        $this->server()->put([...$state, 'status' => 'processing']);
        $this->travel(16)->seconds();

        $this->get($this->payPage($order))->assertSee('We are checking')->assertDontSee('Pay again');
        $this->assertFalse($payment->refresh()->notStarted());
    }

    #[Test]
    public function a_gateway_that_merely_lost_the_payment_is_still_being_checked(): void
    {
        [$order, $payment] = $this->attemptAtGateway();
        $this->server()->forget($payment->uuid);
        $this->get(route('webx.payments.return', ['uuid' => $payment->uuid]));

        $this->get($this->payPage($order))->assertSee('We are checking')->assertDontSee('Payment not completed');
    }

    #[Test]
    public function the_poll_keeps_the_attempt_open_until_its_deadline(): void
    {
        [, $payment] = $this->left();
        $this->travel(10)->minutes();
        $this->app->make(Poll::class)->run();

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertTrue($payment->notStarted());

        $this->travel(2)->days();
        $this->app->make(Poll::class)->run();

        $this->assertSame(PaymentStatus::Expired, $payment->refresh()->status);
        $this->assertArrayNotHasKey(Payment::NOT_STARTED, $payment->meta ?? []);
    }

    #[Test]
    public function pay_again_is_a_new_attempt_and_the_page_goes_on_warning(): void
    {
        [$order, $payment] = $this->left();
        $links = $this->app->make(PaymentLinks::class);

        $this->post($links->attempts($order));
        $new = Payment::query()->latest('id')->firstOrFail();

        $this->assertNotSame($payment->id, $new->id);
        $this->assertSame('gateway-a-card', $new->method_key);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status, 'Nothing closes an attempt the gateway does not know.');
        $this->get($this->payPage($order))->assertSee('wait a minute before paying again');
    }

    #[Test]
    public function another_method_is_chosen_from_payment_not_completed(): void
    {
        [$order] = $this->left();
        $this->get($this->payPage($order))
            ->assertSee('Choose another method')
            ->assertSee('value="gateway-a-form"', false)
            ->assertDontSee('value="gateway-a-card"', false);

        $this->chooseForm($order);
    }

    #[Test]
    public function a_failed_payment_offers_another_method_too(): void
    {
        [$order, $payment] = $this->attemptAtGateway();
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'decline']));

        $this->get($this->payPage($order->refresh()))
            ->assertSee('The payment did not go through')
            ->assertSee('Pay — Card (gateway A)')
            ->assertSee('Choose another method')->assertSee('value="gateway-a-form"', false)
            ->assertDontSee('value="gateway-a-card"', false);

        $this->chooseForm($order);
    }

    #[Test]
    public function an_order_never_tried_may_go_over_to_another_method_too(): void
    {
        $this->get($this->payPage($this->onlineOrder()))->assertSee('Pay — Card (gateway A)')->assertSee('Choose another method');
    }

    #[Test]
    public function the_bank_details_offer_another_method(): void
    {
        $order = $this->place($this->cart(), [
            'awaitPayment' => true,
            'blocks' => ['payment' => ['method' => BankTransfer::METHOD, 'type' => BankTransfer::KEY, 'kind' => 'offline', 'title' => ['en' => 'Bank transfer'], 'price' => null]],
        ]);

        $this->get($this->payPage($order))->assertSee('Choose another method')->assertSee('value="gateway-a-form"', false);
        $this->chooseForm($order);
    }

    #[Test]
    public function a_top_up_left_at_the_gateway_keeps_the_block_of_the_first_payment(): void
    {
        [$order, $payment] = $this->attemptAtGateway();
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));
        $order->refresh()->forceFill(['payable' => $order->payable + 1500, 'total' => $order->total + 1500]);
        $order->balance = Balance::stored($order);
        $order->save();

        $this->post($this->app->make(PaymentLinks::class)->attempts($order), ['method' => 'gateway-a-card']);
        $topUp = Payment::query()->latest('id')->firstOrFail();
        $this->get($this->app->make(PaymentLinks::class)->go($topUp));
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $topUp->uuid, 'action' => 'abandon']));
        $this->get(route('webx.payments.return', ['uuid' => $topUp->uuid]));

        $this->assertTrue($topUp->refresh()->notStarted());
        $this->get($this->payPage($order))
            ->assertSee('Top up — Gateway A page')
            ->assertSee('wait a minute before paying again')
            ->assertDontSee('Choose another method')
            ->assertDontSee('Payment not completed');
    }

    #[Test]
    public function only_a_pending_answer_can_say_not_started(): void
    {
        $this->assertTrue((new GatewayStatus(Reported::Pending, notStarted: true))->notStarted);

        $this->expectException(InvalidArgumentException::class);
        new GatewayStatus(Reported::Paid, notStarted: true);
    }

    /** @return array{0: Order, 1: Payment} an attempt the buyer left at the gateway's page, and came back from */
    private function left(string $kind = 'card'): array
    {
        [$order, $payment] = $this->attemptAtGateway($kind);

        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'abandon']))
            ->assertRedirect(route('webx.payments.return', ['uuid' => $payment->uuid]));
        $this->assertSame(FakeGatewayServer::ABANDONED, $this->server()->payment($payment->uuid)['status'] ?? null);

        $this->get(route('webx.payments.return', ['uuid' => $payment->uuid]))->assertRedirect($this->payPage($order));

        return [$order, $payment];
    }

    /** «Continue» with the `form` method: the order's block changes, a new attempt goes to the gateway. */
    private function chooseForm(Order $order): void
    {
        $response = $this->post($this->app->make(PaymentLinks::class)->method($order), ['method' => 'gateway-a-form']);
        $new = Payment::query()->latest('id')->firstOrFail();

        $response->assertRedirect($this->app->make(PaymentLinks::class)->go($new));
        $this->assertSame('gateway-a-form', $new->method_key);
        $this->assertSame('gateway-a-form', $order->refresh()->payment['method'] ?? null);
    }
}
