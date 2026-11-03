<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Exchange\ExchangeConnection;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Online\Notifications;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Testing\FakeGateway;
use WebxUi\Payments\Testing\FakeGatewayServer;

/**
 * POST /payments/notify/{method} (spec §9.2): every notification is an event of the journal with
 * its decision — applied, a repeat `duplicate`, a bad signature `rejected`, a payment nobody has
 * `unmatched`, an unsigned one only a reason to ask the API —, the journal not written is 503 and
 * a failure while settling is 500 and `error`, the attempt untouched.
 */
final class NotifyTest extends OnlineCase
{
    #[Test]
    public function a_signed_notification_pays_the_order_once_and_a_repeat_is_a_duplicate(): void
    {
        [$order, $payment] = $this->opened('card');
        $this->server()->act($payment->uuid, 'pay');

        $this->notify($payment)->assertOk()->assertExactJson(['received' => true]);

        $payment->refresh();
        $order->refresh();
        $this->assertSame([PaymentStatus::Paid, 4999], [$payment->status, $payment->paid_amount]);
        $this->assertNotNull($order->paid_at);
        $this->assertSame($payment->gateway_ref, $order->milestones()->where('milestone', 'paid')->value('reference'));

        $event = $this->last();
        $this->assertSame(['in', 'notify', 'gateway-a', 'gateway-a-card', 'applied', 'valid', 'paid', 'pending', 'paid'], [
            $event->direction, $event->kind, $event->gateway, $event->method_key, $event->outcome, $event->signature, $event->reported, $event->status_before, $event->status_after,
        ]);
        $this->assertSame([$payment->id, $order->id, 200, '{"received":true}'], [$event->payment_id, $event->order_id, $event->response_status, $event->response_body]);
        $this->assertSame('192.0.2.10', $event->ip);
        $this->assertNotNull($event->event_key);
        $this->assertSame($this->server()->notification($payment->uuid)->getContent(), $event->request_body, 'The raw body, byte for byte.');

        // The gateway repeats it: the same event key, nothing moves, the event says so.
        $this->notify($payment)->assertOk();
        $repeat = $this->last();
        $this->assertSame(['duplicate', 'paid', 'paid', $event->event_key], [$repeat->outcome, $repeat->status_before, $repeat->status_after, $repeat->event_key]);
        $this->assertSame(1, $order->milestones()->where('milestone', 'paid')->count());
        $this->assertSame(4999, $order->refresh()->paid_total);
    }

    #[Test]
    public function a_bad_signature_or_a_forged_body_is_rejected_and_the_gateway_hears_400(): void
    {
        [, $payment] = $this->opened('card');
        $this->server()->act($payment->uuid, 'pay');

        foreach (['signature', 'amount', 'status'] as $variant) {
            $this->notify($payment, $variant)->assertStatus(400)->assertExactJson(['error' => 'signature']);
            $event = $this->last();
            // Linked to the attempt the body claims, for the manager — the status stays.
            $this->assertSame(['rejected', 'invalid', $payment->id, $payment->order_id, 'pending', 'pending', 400], [
                $event->outcome, $event->signature, $event->payment_id, $event->order_id, $event->status_before, $event->status_after, $event->response_status,
            ], $variant);
            // The description is short; the claim is the event's mark, not words in it.
            $this->assertStringNotContainsString('claimed', (string) $event->message);
        }

        // A claim of a payment nobody has links nothing.
        $this->call('POST', $this->app->make(PaymentLinks::class)->notifyUrl('gateway-a-card'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_GATEWAY_A_SIGNATURE' => 'bad'], '{"data":{"id":"nope","order_id":"nope"}}')->assertStatus(400);
        $this->assertSame([null, null], [$this->last()->payment_id, $this->last()->order_id]);

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertSame(4, PaymentGatewayEvent::query()->where('kind', 'notify')->where('signature', 'invalid')->count());
    }

    #[Test]
    public function a_payment_nobody_has_is_unmatched_and_a_dispute_is_ignored(): void
    {
        [, $payment] = $this->opened('card');

        $this->notify($payment, 'foreign')->assertOk();
        $this->assertSame(['unmatched', 'valid', null], [$this->last()->outcome, $this->last()->signature, $this->last()->payment_id]);
        $this->assertStringContainsString('No attempt of this method', (string) $this->last()->message);

        $this->notify($payment, 'unrelated')->assertOk();
        $this->assertSame(['ignored', 'Not about a payment: dispute.created'], [$this->last()->outcome, $this->last()->message]);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    #[Test]
    public function an_unsigned_notification_is_only_a_reason_to_ask_the_api(): void
    {
        [$order, $payment] = $this->opened('unsigned');

        // «Paid», says the body; the gateway's API says it is not.
        $this->notify($payment, 'status')->assertOk()->assertSee('OK');
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertNull($order->refresh()->paid_at);

        $event = PaymentGatewayEvent::query()->where('kind', 'notify')->sole();
        $this->assertSame(['same', 'untrusted', 'pending'], [$event->outcome, $event->signature, $event->reported]);
        $status = PaymentGatewayEvent::query()->where('kind', 'status')->sole();
        $this->assertSame([$event->trace, 'notify', 200], [$status->trace, $status->source, $status->response_status], 'The question to the API is in the same trace.');
        $this->assertStringNotContainsString(FakeGateway::SECRET, (string) $status->url);

        // Paid at the gateway: now the API says so, and the order is paid.
        $this->server()->act($payment->uuid, 'pay');
        $this->notify($payment)->assertOk();
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertNotNull($order->refresh()->paid_at);
        $this->assertSame(['applied', 'untrusted', 'paid'], [$this->lastNotify()->outcome, $this->lastNotify()->signature, $this->lastNotify()->reported]);
    }

    #[Test]
    public function a_form_gateway_gets_its_signed_answer_and_a_decline_fails_the_order_payment(): void
    {
        [$order, $payment] = $this->opened('form');
        $this->server()->act($payment->uuid, 'decline');

        $answer = $this->notify($payment)->assertOk()->json();
        $this->assertSame(FakeGatewayServer::sign((string) $answer['data']), $answer['signature']);
        $this->assertSame('accept', json_decode((string) base64_decode((string) $answer['data']), true)['status'] ?? null);

        $this->assertSame([PaymentStatus::Failed, 'Card declined (test)'], [$payment->refresh()->status, $payment->failure_reason]);
        $this->assertNotNull($order->refresh()->payment_failed_at);
        $this->assertNull($order->paid_at);
        $this->assertSame(['applied', 'failed', 'pending', 'failed'], [$this->last()->outcome, $this->last()->reported, $this->last()->status_before, $this->last()->status_after]);
    }

    #[Test]
    public function a_paid_attempt_does_not_go_back_to_failed(): void
    {
        [$order, $payment] = $this->opened('card');
        $this->server()->act($payment->uuid, 'pay');
        $this->notify($payment);

        $this->server()->act($payment->uuid, 'decline');
        $this->notify($payment)->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame(['ignored', 'paid', 'paid'], [$this->last()->outcome, $this->last()->status_before, $this->last()->status_after]);
        $this->assertNotNull($order->refresh()->paid_at);
    }

    #[Test]
    public function an_unknown_method_or_one_without_keys_is_404_and_still_journaled(): void
    {
        [, $payment] = $this->opened('card');
        $request = $this->server()->notification($payment->uuid);

        $this->send($request, 'no-such-method')->assertNotFound();
        $this->assertSame(['rejected', 'none', null, 'no-such-method', 'The method is not found'], [$this->last()->outcome, $this->last()->signature, $this->last()->gateway, $this->last()->method_key, $this->last()->message]);
        $this->assertSame($request->getContent(), $this->last()->request_body);

        $this->send($request, 'bank-transfer')->assertNotFound();
        $this->assertSame('The method is not online, or its type is not installed', $this->last()->message);

        $this->app->make('db')->table('payment_methods')->where('key', 'gateway-a-card')->update(['credentials' => null]);
        $this->notify($payment)->assertNotFound();
        $this->assertSame('The method has no keys', $this->last()->message);
    }

    #[Test]
    public function a_switched_off_method_still_hears_its_gateway(): void
    {
        [, $payment] = $this->opened('card');
        $this->app->make('db')->table('payment_methods')->where('key', 'gateway-a-card')->update(['enabled' => false]);
        $this->server()->act($payment->uuid, 'pay');

        $this->notify($payment)->assertOk();
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
    }

    #[Test]
    public function the_journal_not_written_is_503_and_the_status_waits(): void
    {
        [$order, $payment] = $this->opened('card');
        $this->server()->act($payment->uuid, 'pay');
        $events = $this->app->make('db')->table('payment_gateway_events')->count();

        $this->app['config']->set('database.connections.nowhere', ['driver' => 'sqlite', 'database' => '/nonexistent/dir/db.sqlite']);
        $this->app['config']->set('webx-payments.exchange.connection', 'nowhere');

        foreach ([ExchangeConnection::class, ExchangeJournal::class, Notifications::class] as $class) {
            $this->app->forgetInstance($class);
        }

        $this->notify($payment)->assertStatus(503)->assertHeader('Retry-After', '60');

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertNull($order->refresh()->paid_at);
        $this->assertSame($events, $this->app->make('db')->table('payment_gateway_events')->count());
    }

    #[Test]
    public function a_failure_while_settling_is_500_and_an_error_event_and_nothing_moves(): void
    {
        [$order, $payment] = $this->opened('card');
        $this->server()->act($payment->uuid, 'pay');
        Payment::saving(static fn () => throw new RuntimeException('the database went away'));

        $this->notify($payment)->assertStatus(500);

        $event = $this->last();
        $this->assertSame(['error', 'pending', 'pending', 500], [$event->outcome, $event->status_before, $event->status_after, $event->response_status]);
        $this->assertStringContainsString('the database went away', (string) $event->message);
        $this->assertSame(PaymentStatus::Pending, Payment::query()->findOrFail($payment->id)->status);
        $this->assertNull($order->refresh()->paid_at, 'The settle was rolled back whole.');
        $this->assertSame(0, $order->milestones()->where('milestone', 'paid')->count());
    }

    #[Test]
    public function an_unsigned_type_whose_api_fails_is_500_and_the_gateway_repeats(): void
    {
        [, $payment] = $this->opened('unsigned');
        $this->server()->failNext(FakeGatewayServer::SERVER_ERROR);

        $this->notify($payment)->assertStatus(500);
        $this->assertSame('error', $this->lastNotify()->outcome);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);

        // The repeat is not a duplicate: nothing was applied.
        $this->notify($payment)->assertOk();
        $this->assertSame('same', $this->lastNotify()->outcome);
    }

    #[Test]
    public function a_body_over_the_limit_is_413_and_journaled_cut(): void
    {
        [, $payment] = $this->opened('card');
        $this->app['config']->set('webx-payments.notify.max_body', 64);
        $this->app['config']->set('webx-payments.exchange.body_limit', 32);

        $this->notify($payment)->assertStatus(413);
        $event = $this->last();
        $this->assertSame(['rejected', 413, true, 32], [$event->outcome, $event->response_status, $event->request_truncated, strlen((string) $event->request_body)]);
        $this->assertGreaterThan(64, $event->request_size);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    #[Test]
    public function the_list_of_the_gateways_addresses_is_on_top_of_the_signature(): void
    {
        [, $payment] = $this->opened('card');
        $this->server()->act($payment->uuid, 'pay');
        $this->app->make('db')->table('payment_methods')->where('key', 'gateway-a-card')->update(['settings' => json_encode(['notify_ips' => '198.51.100.0/24'])]);

        $this->notify($payment)->assertForbidden();
        $this->assertSame(['rejected', 'none'], [$this->last()->outcome, $this->last()->signature]);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);

        $this->app->make('db')->table('payment_methods')->where('key', 'gateway-a-card')->update(['settings' => json_encode(['notify_ips' => ['192.0.2.0/24']])]);
        $this->notify($payment)->assertOk();
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
    }

    #[Test]
    public function the_route_has_no_session_csrf_auth_or_throttle(): void
    {
        $route = $this->app->make(Router::class)->getRoutes()->getByName(PaymentLinks::NOTIFY);
        $this->assertNotNull($route);
        $this->assertSame(['POST'], $route->methods());
        $this->assertSame([], $route->gatherMiddleware(), 'Decision 49: nothing may refuse a notification before the journal.');
        $this->assertStringEndsWith('/payments/notify/gateway-a-card', $this->app->make(PaymentLinks::class)->notifyUrl('gateway-a-card'));

        // No cookie is set on the answer: there is no session to start.
        [, $payment] = $this->opened('card');
        $this->server()->act($payment->uuid, 'pay');
        $this->assertSame([], $this->notify($payment)->assertOk()->headers->getCookies());
    }

    /** @return array{0: Order, 1: Payment} an order and its attempt opened at gateway A */
    private function opened(string $kind): array
    {
        $order = $this->onlineOrder($kind);
        $this->post($this->app->make(PaymentLinks::class)->attempts($order));
        $payment = Payment::query()->latest('id')->firstOrFail();
        $this->get($this->app->make(PaymentLinks::class)->go($payment));

        if ($kind !== 'card') {
            // A form type: the browser posts the handed-off form to the gateway's page.
            $this->server()->open($payment->uuid, $kind, 4999, 'EUR', $kind === 'form' ? 'wait' : 'CREATED');
        }

        return [$order, $payment->refresh()];
    }

    /** @return TestResponse<Response> */
    private function notify(Payment $payment, string $variant = 'valid'): TestResponse
    {
        return $this->send($this->server()->notification($payment->uuid, $variant), $payment->method_key);
    }

    /** @return TestResponse<Response> */
    private function send(Request $request, string $method): TestResponse
    {
        return $this->call('POST', $this->app->make(PaymentLinks::class)->notifyUrl($method), [], [], [], $request->server->all(), $request->getContent());
    }

    private function last(): PaymentGatewayEvent
    {
        return PaymentGatewayEvent::query()->orderByDesc('id')->firstOrFail();
    }

    private function lastNotify(): PaymentGatewayEvent
    {
        return PaymentGatewayEvent::query()->where('kind', 'notify')->orderByDesc('id')->firstOrFail();
    }
}
