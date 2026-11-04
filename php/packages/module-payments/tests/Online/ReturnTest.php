<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Checkout\PlacedOrders;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Site\OrderLinks;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Testing\FakeGatewayNotification;
use WebxUi\Payments\Testing\FakeGatewayNotifier;
use WebxUi\Payments\Testing\FakeGatewayServer;
use WebxUi\Payments\Testing\FakeGatewaySite;

/**
 * The buyer back from the gateway (spec §8.3): journaled first, as it came, without cookies; the
 * status from the gateway's API under the attempt's lock — never from what the address says —;
 * a return and a notification in either order make one milestone; and the buttons of gateway A
 * (§21.2) end where the table says.
 */
final class ReturnTest extends OnlineCase
{
    #[Test]
    public function the_return_asks_the_api_and_a_forged_address_changes_nothing(): void
    {
        [$order, $payment] = $this->opened('card');

        // The gateway did not take the money; the address says it did.
        $this->get($this->returnUrl($payment).'?status=paid&amount=49.99')->assertRedirect($this->payPage($order));

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $return = PaymentGatewayEvent::query()->where('kind', 'return')->sole();
        $this->assertSame(['browser', 'GET', 'same', 'pending', 'pending', 'return'], [$return->direction, $return->http_method, $return->outcome, $return->status_before, $return->status_after, $return->source]);
        $this->assertStringContainsString('status=paid', (string) $return->url, 'Journaled as it came.');
        $this->assertSame([$return->trace], PaymentGatewayEvent::query()->where('kind', 'status')->pluck('trace')->all(), 'The question to the API is in the trace of the return.');
    }

    #[Test]
    public function a_return_and_a_late_notification_make_one_milestone(): void
    {
        [$order, $payment] = $this->opened('card');
        $this->server()->act($payment->uuid, 'pay');

        $this->get($this->returnUrl($payment))
            ->assertRedirect($this->app->make(OrderLinks::class)->page((string) $order->number, 'en'))
            ->assertSessionHas('webx-payments.status', 'Payment received');

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame('applied', PaymentGatewayEvent::query()->where('kind', 'return')->sole()->outcome);

        $this->app->make(FakeGatewayNotifier::class)->send($payment->uuid);
        $notify = PaymentGatewayEvent::query()->where('kind', 'notify')->sole();
        $this->assertSame(['same', 'paid', 'paid'], [$notify->outcome, $notify->status_before, $notify->status_after]);
        $this->assertSame(1, $order->milestones()->where('milestone', 'paid')->count());
        $this->assertSame(4999, $order->refresh()->paid_total);
    }

    #[Test]
    public function a_notification_first_and_the_return_reads_the_row(): void
    {
        [$order, $payment] = $this->opened('card');
        $this->server()->act($payment->uuid, 'pay');
        $this->app->make(FakeGatewayNotifier::class)->send($payment->uuid);
        $served = $this->server()->served();

        // The order placed in this session: «Thank you» of the checkout.
        $this->withSession([PlacedOrders::SESSION => [['number' => (string) $order->number, 'decrease' => null]]]);
        $response = $this->get($this->returnUrl($payment));

        $this->assertStringContainsString((string) $order->number, (string) $response->headers->get('Location'));
        $this->assertStringNotContainsString('signature=', (string) $response->headers->get('Location'));
        $this->assertSame($served, $this->server()->served(), 'Paid already: nobody asks the gateway.');
        $return = PaymentGatewayEvent::query()->where('kind', 'return')->sole();
        $this->assertSame(['same', 'paid', 'paid', 'The status was known before the return'], [$return->outcome, $return->status_before, $return->status_after, $return->message]);
        $this->assertSame(1, $order->milestones()->where('milestone', 'paid')->count());
    }

    #[Test]
    public function a_post_return_is_journaled_without_cookies_and_without_csrf(): void
    {
        [, $payment] = $this->opened('unsigned');
        $this->server()->act($payment->uuid, 'decline');

        $this->call('POST', $this->returnUrl($payment), ['result' => 'fail'], ['laravel_session' => 'abc'], [], ['HTTP_COOKIE' => 'laravel_session=abc'])
            ->assertRedirect();

        $return = PaymentGatewayEvent::query()->where('kind', 'return')->sole();
        $this->assertSame(['POST', 'applied', 'failed'], [$return->http_method, $return->outcome, $return->status_after]);
        $this->assertArrayNotHasKey('cookie', array_change_key_case((array) $return->request_headers));
        $this->assertSame(PaymentStatus::Failed, $payment->refresh()->status);
        $this->assertTrue(Route::has(PaymentLinks::RETURN));
    }

    #[Test]
    public function a_declined_payment_comes_back_to_the_page_with_the_reason(): void
    {
        [$order, $payment] = $this->opened('card');
        $this->server()->act($payment->uuid, 'decline');

        $this->get($this->returnUrl($payment))->assertRedirect($this->payPage($order));

        $this->assertSame([PaymentStatus::Failed, 'Card declined (test)'], [$payment->refresh()->status, $payment->failure_reason]);
        $this->assertNotNull($order->refresh()->payment_failed_at);
        $this->get($this->payPage($order))->assertOk()->assertSee('The payment did not go through: Card declined (test)')->assertSee('Pay — ');
    }

    #[Test]
    public function an_approval_is_completed_on_the_return_and_charged_once(): void
    {
        [$order, $payment] = $this->opened('form');
        $this->server()->act($payment->uuid, 'approve');

        $this->get($this->returnUrl($payment))->assertRedirect();
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame(1, $this->server()->payment($payment->uuid)['charges'] ?? null);
        $this->assertSame(['out/complete', 'out/status'], PaymentGatewayEvent::query()->where('direction', 'out')->orderBy('id')->get()->map(static fn ($e): string => $e->direction.'/'.$e->kind)->all());

        $this->get($this->returnUrl($payment))->assertRedirect();
        $this->assertSame(1, $this->server()->payment($payment->uuid)['charges'] ?? null);
        $this->assertNotNull($order->refresh()->paid_at);
    }

    #[Test]
    public function a_gateway_that_does_not_answer_leaves_the_status_and_the_page_says_checking(): void
    {
        [$order, $payment] = $this->opened('card');
        $this->server()->act($payment->uuid, 'pay');
        $this->server()->failNext(FakeGatewayServer::TIMEOUT);

        $this->get($this->returnUrl($payment))->assertRedirect($this->payPage($order));

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertSame('error', PaymentGatewayEvent::query()->where('kind', 'return')->sole()->outcome);

        // The page asks the gateway itself, once in 15 s: now it answers, and the order is paid.
        $this->get($this->payPage($order))->assertOk()->assertSee('Order '.$order->number.' is paid');
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
    }

    #[Test]
    public function the_page_we_are_checking_asks_the_gateway_at_most_every_15_seconds_and_gives_up_after_two_minutes(): void
    {
        [$order, $payment] = $this->opened('card');

        $this->get($this->payPage($order))->assertOk()->assertSee('We are checking the payment')->assertSee('http-equiv="refresh"', false)->assertDontSee('Pay — ');
        $served = $this->server()->served();
        $this->get($this->payPage($order))->assertOk();
        $this->getJson(route('webx-payments.status', ['uuid' => $payment->uuid]))->assertExactJson(['status' => 'pending', 'final' => false, 'not_started' => false, 'declined' => false]);
        $this->assertSame($served, $this->server()->served(), 'Within 15 s the row answers.');

        $this->travel(16)->seconds();
        $this->server()->act($payment->uuid, 'pay');
        $this->getJson(route('webx-payments.status', ['uuid' => $payment->uuid]))->assertExactJson(['status' => 'paid', 'final' => true, 'not_started' => false, 'declined' => false]);
        $this->assertSame(0, PaymentGatewayEvent::query()->whereIn('kind', ['notify', 'return'])->count(), 'The poll is not an event; its question to the API is.');

        // Another attempt the bank never confirms: after two minutes no refresh and no «Pay».
        [$late] = $this->opened('card');
        $this->travel(3)->minutes();
        $this->get($this->payPage($late))->assertOk()
            ->assertSee('The bank has not confirmed the payment yet')
            ->assertSee('Check again')
            ->assertDontSee('http-equiv="refresh"', false)
            ->assertDontSee('Pay — ');
    }

    #[Test]
    public function an_unknown_attempt_is_404_and_still_journaled(): void
    {
        $this->get(route(PaymentLinks::RETURN, ['uuid' => str_repeat('A', 26)]).'?x=1')->assertNotFound();

        $return = PaymentGatewayEvent::query()->where('kind', 'return')->sole();
        $this->assertSame(['unmatched', null], [$return->outcome, $return->payment_id]);
    }

    #[Test]
    public function a_held_payment_is_accepted_for_the_buyer_and_the_order_still_awaits(): void
    {
        $this->app->make('db')->table('payment_methods')->where('key', 'gateway-a-card')->update(['settings' => json_encode(['hold' => true])]);
        [$order, $payment] = $this->opened('card');

        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']))->assertRedirect($this->returnUrl($payment));
        $this->assertSame([PaymentStatus::Authorized, 4999], [$payment->refresh()->status, $payment->authorized_amount]);
        $this->assertNotNull($payment->hold_expires_at);

        $this->get($this->returnUrl($payment))->assertSessionHas('webx-payments.status', 'Payment accepted');
        $order->refresh();
        $this->assertNull($order->paid_at);
        $this->assertTrue($order->awaiting_payment);
        $this->assertStringContainsString('Money held', (string) $this->app->make('db')->table('order_status_changes')->where('order_id', $order->id)->orderByDesc('id')->value('note'));
        $this->get($this->payPage($order))->assertSee('Payment accepted. The amount is reserved');
    }

    #[Test]
    public function the_buttons_of_gateway_a_end_as_the_table_says(): void
    {
        // «Pay»: the notification at once, the buyer back; one payment, two events.
        [$order, $payment] = $this->opened('card');
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']))->assertRedirect($this->returnUrl($payment));
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->get($this->returnUrl($payment));
        $this->assertSame(['applied', 'same'], PaymentGatewayEvent::query()->whereIn('kind', ['notify', 'return'])->orderBy('id')->pluck('outcome')->all());

        // «Repeat», «Bad signature», «Foreign payment»: the gateway's page says what the shop answered.
        foreach (['repeat-notify' => ['duplicate', 200], 'bad-signature' => ['rejected', 400], 'foreign' => ['unmatched', 200]] as $button => [$outcome, $code]) {
            $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => $button]))
                ->assertRedirect(route(FakeGatewaySite::PAGE, ['uuid' => $payment->uuid]))
                ->assertSessionHas('webx-payments.fake', "Gateway A: «{$button}» done. The shop answered the notification with HTTP {$code}.");
            $this->assertSame($outcome, PaymentGatewayEvent::query()->where('kind', 'notify')->orderByDesc('id')->firstOrFail()->outcome, $button);
        }

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame(1, $order->milestones()->where('milestone', 'paid')->count());

        // «Cancel»: no notification, the status comes from the API on the return.
        [, $cancelled] = $this->opened('card');
        $before = PaymentGatewayEvent::query()->where('kind', 'notify')->count();
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $cancelled->uuid, 'action' => 'cancel']))->assertRedirect($this->returnUrl($cancelled));
        $this->assertSame($before, PaymentGatewayEvent::query()->where('kind', 'notify')->count());
        $this->get($this->returnUrl($cancelled));
        $this->assertSame(PaymentStatus::Cancelled, $cancelled->refresh()->status);
    }

    #[Test]
    public function pay_and_notify_later_is_settled_by_the_return_and_the_notification_is_same(): void
    {
        Queue::fake();
        [$order, $payment] = $this->opened('card');

        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay-later']))->assertRedirect($this->returnUrl($payment));
        Queue::assertPushed(FakeGatewayNotification::class, static fn (FakeGatewayNotification $job): bool => $job->uuid === $payment->uuid && $job->delay !== null);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);

        $this->get($this->returnUrl($payment));
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);

        $this->app->call([new FakeGatewayNotification($payment->uuid), 'handle']);
        $this->assertSame('same', PaymentGatewayEvent::query()->where('kind', 'notify')->sole()->outcome);
        $this->assertSame(1, $order->milestones()->where('milestone', 'paid')->count());
    }

    /** @return array{0: Order, 1: Payment} */
    private function opened(string $kind): array
    {
        $order = $this->onlineOrder($kind);
        $this->post($this->app->make(PaymentLinks::class)->attempts($order));
        $payment = Payment::query()->latest('id')->firstOrFail();
        $this->get($this->app->make(PaymentLinks::class)->go($payment));

        if ($kind !== 'card') {
            $this->server()->open($payment->uuid, $kind, 4999, 'EUR', $kind === 'form' ? 'wait' : 'CREATED', ['return_url' => $this->returnUrl($payment)]);
        }

        return [$order, $payment->refresh()];
    }

    private function returnUrl(Payment $payment): string
    {
        return $this->app->make(PaymentLinks::class)->returnUrl($payment);
    }
}
