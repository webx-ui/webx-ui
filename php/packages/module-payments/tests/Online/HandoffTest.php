<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Orders;
use WebxUi\Payments\BeginRefused;
use WebxUi\Payments\Handoff;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Testing\FakeGateway;
use WebxUi\Payments\Testing\FakeGatewayServer;

/**
 * The way to the gateway (spec §8.2): `begin()` makes a row and calls nobody; GET /pay/go/{uuid}
 * opens the payment once, journals the satellite's requests (`out/key`, `out/start`) and the
 * handoff (`browser/handoff`) under one trace, and sends the buyer on — a redirect or a form. A
 * second visit never opens a second payment; a gateway that did not answer fails the attempt.
 */
final class HandoffTest extends OnlineCase
{
    #[Test]
    public function begin_makes_an_attempt_for_what_is_owed_and_calls_nobody(): void
    {
        $order = $this->onlineOrder();
        $payment = $this->begin($order);

        $this->assertSame([PaymentStatus::Created, 4999, 'EUR', 'gateway-a-card', 'gateway-a.card', 'pay-page', false], [
            $payment->status, $payment->amount, $payment->currency, $payment->method_key, $payment->type, $payment->source, $payment->hold,
        ]);
        $this->assertSame(26, strlen($payment->uuid));
        $this->assertSame(0, $this->server()->served());
        $this->assertSame([], $this->events());

        // An attempt that never went to the gateway is closed by the next one.
        $next = $this->begin($order);
        $this->assertSame(PaymentStatus::Cancelled, $payment->refresh()->status);
        $this->assertSame(PaymentStatus::Created, $next->status);
    }

    #[Test]
    public function a_hold_is_asked_only_where_the_type_captures(): void
    {
        $this->app->make('db')->table('payment_methods')->whereIn('key', ['gateway-a-card', 'gateway-a-form'])->update(['settings' => json_encode(['hold' => true])]);

        $this->assertTrue($this->begin($this->onlineOrder('card'))->hold);
        $this->assertFalse($this->begin($this->onlineOrder('form'))->hold);
    }

    #[Test]
    public function the_card_goes_to_the_gateways_page_once_and_every_step_is_journaled(): void
    {
        $order = $this->onlineOrder();
        $payment = $this->begin($order);
        $go = $this->app->make(PaymentLinks::class)->go($payment);

        $this->get($go)->assertRedirect(route('webx.payments.fake.page', ['uuid' => $payment->uuid]));

        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('ga_'.strtolower($payment->uuid), $payment->gateway_ref);
        $this->assertNotNull($payment->expires_at);
        $this->assertSame(['out/key', 'out/start', 'browser/handoff'], $this->events());
        $this->assertSame(1, PaymentGatewayEvent::query()->distinct()->count('trace'), 'One action, one trace.');

        $start = PaymentGatewayEvent::query()->where('kind', 'start')->sole();
        $this->assertSame([201, $payment->id, $order->id, 'gateway-a', 'gateway-a-card'], [$start->response_status, $start->payment_id, $start->order_id, $start->gateway, $start->method_key]);
        $this->assertSame(['[secret:header]'], $start->request_headers['Authorization'] ?? null);

        $handoff = PaymentGatewayEvent::query()->where('kind', 'handoff')->sole();
        $this->assertSame([302, 'applied', 'created', 'pending'], [$handoff->response_status, $handoff->outcome, $handoff->status_before, $handoff->status_after]);
        $this->assertStringEndsWith('/pay/fake/'.$payment->uuid, (string) ($handoff->response_headers['Location'][0] ?? ''));

        foreach (PaymentGatewayEvent::query()->get() as $event) {
            $this->assertStringNotContainsString(FakeGateway::SECRET, $event->url.json_encode($event->request_headers).$event->request_body.$event->response_body);
        }

        // «Back» from the gateway: the page of payment, not a second payment.
        $served = $this->server()->served();
        $this->get($go)->assertRedirect($this->payPage($order));
        $this->assertSame($served, $this->server()->served());
        $this->assertSame(3, PaymentGatewayEvent::query()->count());
    }

    #[Test]
    public function the_gateway_gets_of_the_buyer_only_what_the_type_needs(): void
    {
        $card = $this->begin($this->onlineOrder('card'));
        $this->get($this->app->make(PaymentLinks::class)->go($card));
        $body = json_decode((string) PaymentGatewayEvent::query()->where('kind', 'start')->sole()->request_body, true);

        $this->assertSame('alex@example.test', strtolower((string) ($body['customer']['email'] ?? '')));
        $this->assertSame([['name' => 'Item A', 'qty' => 1, 'price' => '49.99', 'total' => '49.99']], $body['items']);
        $this->assertStringNotContainsString('555', (string) json_encode($body), 'The card type does not need the phone.');
        $this->assertSame('49.99', $body['amount']);
        $this->assertFalse($body['capture'] === false);

        $unsigned = $this->begin($this->onlineOrder('unsigned'));
        $form = $this->get($this->app->make(PaymentLinks::class)->go($unsigned))->assertOk()->getContent();
        $this->assertStringContainsString('name="phone" value="+1 555 010-0000"', (string) $form);
        $this->assertStringNotContainsString('example.test', (string) $form, 'The unsigned type does not need the e-mail.');
    }

    #[Test]
    public function a_form_gateway_gets_a_page_that_posts_itself_and_no_request_is_made(): void
    {
        $payment = $this->begin($this->onlineOrder('form'));
        $page = (string) $this->get($this->app->make(PaymentLinks::class)->go($payment))->assertOk()->getContent();

        $this->assertStringContainsString('action="'.route('webx.payments.fake.action', ['uuid' => $payment->uuid, 'action' => 'open']).'"', $page);
        $this->assertStringContainsString('name="data"', $page);
        $this->assertStringContainsString('name="signature"', $page);
        $this->assertStringContainsString('.submit()', $page);
        $this->assertSame(0, $this->server()->served());
        $this->assertSame(['browser/handoff'], $this->events());
        $this->assertSame($page, PaymentGatewayEvent::query()->sole()->response_body, 'The form is journaled as it went to the browser.');
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    #[Test]
    public function a_gateway_that_did_not_answer_fails_the_attempt_and_the_buyer_reads_it(): void
    {
        $order = $this->onlineOrder();
        $payment = $this->begin($order);
        $this->server()->failNext(FakeGatewayServer::TIMEOUT);

        $this->get($this->app->make(PaymentLinks::class)->go($payment))
            ->assertRedirect($this->payPage($order))
            ->assertSessionHas('webx-payments.error', 'Could not open the payment. Try again or choose another method.');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('The gateway did not answer in time', $payment->failure_reason);
        $this->assertNotNull($order->refresh()->payment_failed_at);
        $this->assertSame(['out/key', 'out/start'], $this->events());
        $this->assertStringContainsString('timed out', (string) PaymentGatewayEvent::query()->where('kind', 'start')->sole()->transport_error);

        // Once is once: the failed attempt is not opened again.
        $served = $this->server()->served();
        $this->get($this->app->make(PaymentLinks::class)->go($payment))->assertRedirect($this->payPage($order));
        $this->assertSame($served, $this->server()->served());
    }

    #[Test]
    public function a_cancelled_order_and_an_offline_method_take_no_attempt(): void
    {
        $order = $this->onlineOrder();
        $this->app->make(Orders::class)->cancel($order, 'panel', 'The buyer changed their mind');

        try {
            $this->begin($order);
            $this->fail('A cancelled order took an attempt.');
        } catch (BeginRefused $refused) {
            $this->assertSame([BeginRefused::CANCELLED, 'The order is cancelled'], [$refused->reason, $refused->getMessage()]);
        }

        $this->enableBuiltIns();
        $transfer = $this->app->make(Methods::class)->find('bank-transfer');
        $this->assertNotNull($transfer);
        $this->expectExceptionObject(new BeginRefused(BeginRefused::NOT_ONLINE));
        $this->app->make(Payments::class)->begin($this->onlineOrder(), $transfer, 'panel');
    }

    #[Test]
    public function a_form_never_carries_a_card_field(): void
    {
        foreach (['card_number', 'cvv', 'exp_month', 'cardExpiry', 'pan', 'CVC2'] as $name) {
            try {
                Handoff::post('https://gateway.test/pay', [$name => '4444']);
                $this->fail("«{$name}» went to the gateway's form.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(['company', 'expires_at', 'data'], array_keys(Handoff::post('https://gateway.test/pay', ['company' => 'A', 'expires_at' => '1', 'data' => 'x'])->fields));
    }

    private function begin(Order $order): Payment
    {
        $method = $this->app->make(Methods::class)->find((string) $order->payment['method']);
        $this->assertNotNull($method);

        return $this->app->make(Payments::class)->begin($order, $method, 'pay-page');
    }
}
