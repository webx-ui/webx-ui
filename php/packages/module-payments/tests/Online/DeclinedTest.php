<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\Online\Settlement;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Reported;

/**
 * The bank declined the card and the gateway keeps the payment open for another one (spec §8.4):
 * the buyer is told at once and may go on, the page stops asking by itself, the attempt stays open
 * to its deadline and a late payment from the gateway's tab still counts.
 */
final class DeclinedTest extends OnlineCase
{
    private const string REASON = 'A limit of the card was reached';

    #[Test]
    public function the_buyer_reads_the_reason_and_the_ways_on(): void
    {
        [$order, $payment] = $this->declined();

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertTrue($payment->declined());
        $this->assertSame(self::REASON, $payment->declineReason());
        $this->assertTrue($order->refresh()->awaiting_payment);

        $this->get($this->payPage($order))
            ->assertOk()
            ->assertSee('The payment did not go through: the bank declined the card')
            ->assertSee(self::REASON)
            ->assertSee('Pay again — ')
            ->assertSee('Choose another method')
            ->assertSee('Check again')
            ->assertSee('wait a minute before paying again')
            ->assertDontSee('We are checking')
            ->assertDontSee('http-equiv="refresh"', false);
    }

    #[Test]
    public function the_status_json_tells_a_script_to_stop_waiting(): void
    {
        [, $payment] = $this->declined();

        $this->getJson(route('webx-payments.status', ['uuid' => $payment->uuid]))
            ->assertOk()
            ->assertExactJson(['status' => 'pending', 'final' => false, 'not_started' => false, 'declined' => true]);
    }

    #[Test]
    public function pay_again_is_a_new_attempt_at_the_gateway(): void
    {
        [$order, $payment] = $this->declined();

        $this->post($this->app->make(PaymentLinks::class)->attempts($order))->assertRedirect();

        $this->assertNotSame($payment->id, Payment::query()->latest('id')->firstOrFail()->id);
    }

    #[Test]
    public function a_late_payment_of_the_old_tab_is_a_payment(): void
    {
        [$order, $payment] = $this->declined();

        $this->app->make(Settlement::class)->settle($payment, new GatewayStatus(Reported::Paid, amount: $payment->amount, currency: $payment->currency));

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertFalse($payment->declined());
        $this->assertNotNull($order->refresh()->paid_at);
    }

    #[Test]
    public function any_other_answer_of_the_gateway_takes_the_mark_off(): void
    {
        [, $payment] = $this->declined();

        $this->app->make(Settlement::class)->settle($payment, new GatewayStatus(Reported::Pending));

        $this->assertFalse($payment->refresh()->declined());
        $this->assertNull($payment->declineReason());
    }

    #[Test]
    public function a_declined_payment_is_a_pending_one_that_was_started(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GatewayStatus(Reported::Failed, declined: true);
    }

    #[Test]
    public function it_is_not_a_payment_not_begun_either(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GatewayStatus(Reported::Pending, notStarted: true, declined: true);
    }

    /** @return array{0: Order, 1: Payment} */
    private function declined(): array
    {
        [$order, $payment] = $this->attemptAtGateway();
        $settlement = $this->app->make(Settlement::class);
        $settlement->settle($payment, new GatewayStatus(Reported::Pending, reason: self::REASON, declined: true));
        // The gateway was asked just now: the fake one knows no such state, the page is not to ask it.
        Cache::put('webx-payments:checked:'.$payment->uuid, true, 60);

        return [$order, $payment->refresh()];
    }
}
