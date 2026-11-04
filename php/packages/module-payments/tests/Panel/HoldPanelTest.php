<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\Panel\OrderCard;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Online\Settlement;
use WebxUi\Payments\Panel\EventRows;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Reported;
use WebxUi\Payments\SettleResult;
use WebxUi\Payments\Testing\FakeGatewayServer;
use WebxUi\Payments\Testing\FakeGatewaySite;

/**
 * «Capture» and «Release the hold» of the tab «Payment» (spec §9.5, §13.4; decision 32): the
 * order's action — `orders.manage`; a dry run says what would move and asks the gateway nothing;
 * a part where the gateway takes one; a refusal of the gateway leaves the money held.
 */
final class HoldPanelTest extends PanelCase
{
    #[Test]
    public function the_tab_offers_the_hold_to_whoever_works_the_orders(): void
    {
        [$order, $payment] = $this->held();

        $this->signIn(['payments.view']);
        $this->getJson($this->api('orders/'.$order->id))->assertOk()
            ->assertJsonPath('data.state', 'held')
            ->assertJsonPath('data.attempts.0.can_capture', false)
            ->assertJsonPath('data.attempts.0.partial', true)
            // The sum to capture as the shop writes its money: «49,99» beside «49,99 €».
            ->assertJsonPath('data.attempts.0.amount_decimal', '49,99');
        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), [])->assertForbidden();
        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/void'), [])->assertForbidden();

        $this->signIn(['payments.view', 'orders.manage']);
        $this->getJson($this->api('orders/'.$order->id))->assertJsonPath('data.attempts.0.can_capture', true);
    }

    #[Test]
    public function a_dry_run_says_what_moves_and_asks_the_gateway_nothing(): void
    {
        [$order, $payment] = $this->held();
        $this->signIn(['payments.view', 'orders.manage']);
        $events = PaymentGatewayEvent::query()->count();

        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), ['amount' => '30,00', 'dry_run' => true])->assertOk()
            ->assertJsonPath('data.dry_run', true)
            ->assertJsonPath('data.amount', 3000)
            ->assertJsonPath('data.held', 4999)
            ->assertJsonPath('data.released', 1999)
            // Less than the order costs: no milestone, and the dialog says what stays unpaid.
            ->assertJsonPath('data.covers', false)
            ->assertJsonPath('data.owes', 4999)
            ->assertJsonPath('data.left', 1999);
        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), ['amount' => '', 'dry_run' => true])->assertOk()
            ->assertJsonPath('data.covers', true)
            ->assertJsonPath('data.left', 0);
        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/void'), ['dry_run' => true])->assertOk()
            ->assertJsonPath('data.held', 4999);

        // More than is held, and an amount that is not one: refused by field, before the gateway.
        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), ['amount' => '60', 'dry_run' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('amount');
        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), ['amount' => '1.234'])
            ->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->assertSame($events, PaymentGatewayEvent::query()->count());
        $this->assertSame(PaymentStatus::Authorized, $payment->refresh()->status);
        $this->assertNull($order->refresh()->paid_at);
    }

    /** A part short of the order (decision of 03.11.2026): no milestone, the order owes the rest. */
    #[Test]
    public function a_part_is_captured_and_the_order_owes_the_rest(): void
    {
        [$order, $payment] = $this->held();
        $manager = $this->signIn(['payments.view', 'orders.manage']);

        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), ['amount' => '30,00'])->assertOk()
            ->assertJsonPath('data.state', 'awaiting')
            ->assertJsonPath('data.due', 1999)
            ->assertJsonPath('data.paid', 3000)
            ->assertJsonPath('data.attempts.0.status', 'paid')
            ->assertJsonPath('data.attempts.0.can_capture', false);

        $this->assertSame([PaymentStatus::Paid, 3000, 'panel'], [$payment->refresh()->status, $payment->paid_amount, PaymentGatewayEvent::query()->where('kind', 'capture')->sole()->source]);
        $this->assertSame([3000, null], [$order->refresh()->paid_total, $order->paid_at]);

        // The decision is «Captured» in the manager's name, and the order's history line is theirs.
        $this->assertSame(["30,00\u{00A0}€ of 49,99\u{00A0}€ by {$manager->name}"], $this->messages($order, 'captured'));
        $note = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::NOTE)->latest('id')->firstOrFail();
        $this->assertSame(['panel', $manager->id], [$note->source, $note->admin_id]);
        $this->assertStringStartsWith('Held money captured', (string) $note->note);

        // The money is a part of what was owed, not a top-up: its own line, «Captured 30,00 of 49,99».
        $lines = StatusChange::query()->where('order_id', $order->id)->whereIn('kind', [StatusChange::PAYMENT, StatusChange::PAYMENT_PART])->get();
        $this->assertSame([StatusChange::PAYMENT_PART], $lines->pluck('kind')->all());
        $this->assertSame(['amount' => 3000, 'owed' => 4999], array_intersect_key(OrderCard::paymentLine($lines->sole()->note), ['amount' => 0, 'owed' => 0]));
        $this->assertSame('payment:'.$payment->method_key, $lines->sole()->source);
    }

    #[Test]
    public function the_hold_is_released(): void
    {
        [$order, $payment] = $this->held();
        $manager = $this->signIn(['payments.view', 'orders.manage']);

        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/void'), [])->assertOk()
            ->assertJsonPath('data.attempts.0.status', 'cancelled')
            ->assertJsonPath('data.state', 'awaiting');
        $this->assertSame(["49,99\u{00A0}€ back to the buyer by {$manager->name}"], $this->messages($order, 'released'));
        $this->assertSame(PaymentStatus::Cancelled, $payment->refresh()->status);
        $this->assertNull($order->refresh()->paid_at);
        $this->assertNull($order->payment_failed_at);

        // Nothing is held any more: both actions say so.
        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/void'), [])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');
    }

    #[Test]
    public function a_refused_capture_keeps_the_money_held_and_says_so(): void
    {
        [$order, $payment] = $this->held();
        $this->signIn(['payments.view', 'orders.manage']);
        $this->server()->failNext(FakeGatewayServer::CAPTURE_REFUSED);

        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.payment.0', 'The gateway refused the capture, the money is still held: The gateway refused the capture');

        $this->assertSame([PaymentStatus::Authorized, SettleResult::CAPTURE_FAILED], [$payment->refresh()->status, $payment->problem]);

        // The line of the journal does not read as money taken, the event keeps what it stored.
        $event = PaymentGatewayEvent::query()->where('kind', 'captured')->sole();
        $this->assertSame('rejected', $event->outcome);
        $this->assertSame('capture_refused', EventRows::line($event)['kind']);
    }

    #[Test]
    public function the_note_of_the_hold_leaves_the_notes_when_the_hold_is_over(): void
    {
        foreach (['capture', 'void'] as $action) {
            [$order, $payment] = $this->held();
            $this->signIn(['payments.view', 'orders.manage']);
            $notes = fn (): array => StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::NOTE)->pluck('note')->all();
            $this->assertStringStartsWith('Money held', $notes()[0], $action);

            $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/'.$action), [])->assertOk();

            $this->assertSame([], array_values(array_filter($notes(), static fn (string $note): bool => str_starts_with($note, 'Money held'))), $action);
            $this->assertSame(1, StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::NOTE_CLOSED)->count(), $action);
        }
    }

    #[Test]
    public function the_note_of_a_refused_capture_leaves_with_the_hold(): void
    {
        $ends = [
            'capture' => fn (Order $order, Payment $payment) => $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), [])->assertOk(),
            'release' => fn (Order $order, Payment $payment) => $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/void'), [])->assertOk(),
            'expiry' => fn (Order $order, Payment $payment) => $this->app->make(Settlement::class)->settle($payment, new GatewayStatus(Reported::Expired)),
        ];

        foreach ($ends as $end => $finish) {
            [$order, $payment] = $this->held();
            $this->signIn(['payments.view', 'orders.manage']);
            $refused = fn (string $kind): array => StatusChange::query()->where('order_id', $order->id)->where('kind', $kind)->where('note', 'like', 'The gateway refused to capture%')->pluck('note')->all();

            $this->server()->failNext(FakeGatewayServer::CAPTURE_REFUSED);
            $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), [])->assertUnprocessable();
            $this->assertCount(1, $refused(StatusChange::NOTE), $end);

            $finish($order, $payment->refresh());

            $this->assertSame([], $refused(StatusChange::NOTE), $end);
            $this->assertCount(1, $refused(StatusChange::NOTE_CLOSED), $end);
        }
    }

    #[Test]
    public function a_refused_capture_stays_in_the_notes_while_the_money_is_held(): void
    {
        [$order, $payment] = $this->held();
        $this->signIn(['payments.view', 'orders.manage']);
        $this->server()->failNext(FakeGatewayServer::CAPTURE_REFUSED);
        $this->postJson($this->api('orders/'.$order->id.'/attempts/'.$payment->id.'/capture'), [])->assertUnprocessable();

        $notes = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::NOTE)->pluck('note')->all();

        $this->assertCount(2, $notes);
        $this->assertStringStartsWith('Money held', $notes[0]);
        $this->assertStringStartsWith('The gateway refused to capture', $notes[1]);
    }

    #[Test]
    public function an_attempt_of_another_order_is_not_found(): void
    {
        [, $payment] = $this->held();
        $other = $this->onlineOrder();
        $this->signIn(['payments.view', 'orders.manage']);

        $this->postJson($this->api('orders/'.$other->id.'/attempts/'.$payment->id.'/capture'), [])->assertNotFound();
    }

    /**
     * The tab's events of a kind as the panel reads them.
     *
     * @return list<string|null>
     */
    private function messages(Order $order, string $kind): array
    {
        /** @var list<array{kind: string, message: string|null}> $events */
        $events = $this->getJson($this->api('orders/'.$order->id))->assertOk()->json('data.attempts.0.events');

        return array_values(array_map(static fn (array $event): ?string => $event['message'], array_filter($events, static fn (array $event): bool => $event['kind'] === $kind)));
    }

    /** @return array{0: Order, 1: Payment} */
    private function held(): array
    {
        [$order, $payment] = $this->attemptAtGateway(hold: true);
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));
        $this->assertSame(PaymentStatus::Authorized, $payment->refresh()->status);

        return [$order, $payment];
    }
}
