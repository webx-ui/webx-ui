<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\SettleResult;
use WebxUi\Payments\Testing\FakeGatewayServer;
use WebxUi\Payments\Testing\FakeGatewaySite;

/**
 * `webx:payments:poll` (spec §9.3): a notification that never came, an approval the buyer did not
 * come back from, an attempt past its deadline, a hold that ends tomorrow — each comes to its end
 * without the buyer, one request to the gateway per attempt a run.
 */
final class PollTest extends OnlineCase
{
    #[Test]
    public function a_lost_notification_is_made_good_by_the_poll(): void
    {
        [$order, $payment] = $this->attemptAtGateway();
        // Paid at the gateway; the notification never reached the shop, the buyer closed the tab.
        $this->server()->act($payment->uuid, 'pay');

        $this->artisan('webx:payments:poll')->assertSuccessful();
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status, 'Younger than five minutes: not asked yet.');

        $this->travel(6)->minutes();
        $this->artisan('webx:payments:poll')->expectsOutputToContain('Checked 1, expired 0')->assertSuccessful();

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertNotNull($order->refresh()->paid_at);
        $this->assertSame(1, $order->milestones()->where('milestone', 'paid')->count());
        $this->assertSame('poll', PaymentGatewayEvent::query()->where('kind', 'status')->orderByDesc('id')->firstOrFail()->source);

        // A paid attempt is not asked again.
        $served = $this->server()->served();
        $this->artisan('webx:payments:poll')->assertSuccessful();
        $this->assertSame($served, $this->server()->served());
    }

    #[Test]
    public function an_approval_the_buyer_did_not_come_back_from_is_completed(): void
    {
        [$order, $payment] = $this->attemptAtGateway('form');
        $this->server()->act($payment->uuid, 'approve');
        $this->travel(6)->minutes();

        $this->artisan('webx:payments:poll')->assertSuccessful();

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame(1, $this->server()->payment($payment->uuid)['charges'] ?? null);
        $this->assertNotNull($order->refresh()->paid_at);
    }

    #[Test]
    public function an_attempt_past_its_deadline_expires_with_an_internal_event(): void
    {
        [$order, $payment] = $this->attemptAtGateway();
        $this->travel(49)->hours();

        $this->artisan('webx:payments:poll')->expectsOutputToContain('expired 1')->assertSuccessful();

        $this->assertSame([PaymentStatus::Expired, 'The gateway did not answer in time'], [$payment->refresh()->status, $payment->failure_reason]);
        $this->assertNotNull($order->refresh()->payment_failed_at);
        $expire = PaymentGatewayEvent::query()->where('kind', 'expire')->sole();
        $this->assertSame(['internal', 'applied', 'pending', 'expired', null], [$expire->direction, $expire->outcome, $expire->status_before, $expire->status_after, $expire->request_body]);
        // The gateway was asked once before.
        $this->assertSame(1, PaymentGatewayEvent::query()->where('kind', 'status')->where('source', 'poll')->count());
    }

    #[Test]
    public function an_attempt_that_never_reached_the_gateway_expires_without_a_request(): void
    {
        $order = $this->onlineOrder();
        $method = $this->app->make(Methods::class)->find('gateway-a-card');
        $this->assertNotNull($method);
        $payment = $this->app->make(Payments::class)->begin($order, $method, 'pay-page');

        $this->travel(10)->minutes();
        $this->artisan('webx:payments:poll')->assertSuccessful();
        $this->assertSame(PaymentStatus::Created, $payment->refresh()->status, 'The buyer may still go to the gateway.');

        $this->travel(48)->hours();
        $this->artisan('webx:payments:poll')->assertSuccessful();

        $this->assertSame(PaymentStatus::Expired, $payment->refresh()->status);
        $this->assertSame(0, $this->server()->served());
    }

    #[Test]
    public function a_payment_the_gateway_does_not_know_is_asked_less_and_less_often(): void
    {
        [, $payment] = $this->attemptAtGateway();
        // The gateway forgot it — what the journal shows as «Status request → 404».
        $this->server()->forget($payment->uuid);
        $asked = static fn (): int => PaymentGatewayEvent::query()->where('kind', 'status')->where('source', 'poll')->count();

        // Every five minutes until its hour at the gateway is nearly out: eleven runs.
        for ($minute = 5; $minute <= 55; $minute += 5) {
            $this->travel(5)->minutes();
            $this->artisan('webx:payments:poll')->assertSuccessful();
        }

        // Asked at 5, 15 (+10) and 35 (+20) minutes; the next is at 75 (+40).
        $this->assertSame(3, $asked());
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
        $this->assertSame(3, $payment->meta['poll']['asked'] ?? null);

        // The deadline still expires it.
        $this->travel(49)->hours();
        $this->artisan('webx:payments:poll')->expectsOutputToContain('expired 1')->assertSuccessful();
        $this->assertSame(PaymentStatus::Expired, $payment->refresh()->status);
    }

    #[Test]
    public function one_gateway_down_does_not_stop_the_others(): void
    {
        [, $first] = $this->attemptAtGateway();
        [, $second] = $this->attemptAtGateway();
        $this->server()->act($second->uuid, 'pay');
        $this->travel(6)->minutes();
        // The token comes from the cache after the first request: the timeout hits the status of the first.
        $this->server()->failNext(FakeGatewayServer::TIMEOUT);

        $this->artisan('webx:payments:poll')->expectsOutputToContain('errors 1')->assertSuccessful();

        $this->assertSame(PaymentStatus::Pending, $first->refresh()->status);
        $this->assertSame(PaymentStatus::Paid, $second->refresh()->status);
    }

    #[Test]
    public function a_hold_that_ends_tomorrow_is_told_once_a_day_and_its_expiry_fails_the_order(): void
    {
        // «Once a day» is a date: from a morning, the hour later below stays on the same day. Run late
        // in the evening, it crossed midnight and the hold was told of again.
        $this->travelTo(now()->startOfDay()->addHours(9));

        [$order, $payment] = $this->attemptAtGateway(hold: true);
        $this->post(route(FakeGatewaySite::ACTION, ['uuid' => $payment->uuid, 'action' => 'pay']));
        $this->assertSame(PaymentStatus::Authorized, $payment->refresh()->status);

        $this->artisan('webx:payments:poll')->expectsOutputToContain('holds to warn of 0')->assertSuccessful();

        $this->travel(6)->days();
        $this->travel(2)->hours();
        $this->artisan('webx:payments:poll')->expectsOutputToContain('holds to warn of 1')->assertSuccessful();
        $this->assertSame([PaymentStatus::Authorized, SettleResult::HOLD_EXPIRING], [$payment->refresh()->status, $payment->problem]);
        $this->assertStringContainsString('capture it or let it go', (string) $this->app->make('db')->table('order_status_changes')->where('order_id', $order->id)->orderByDesc('id')->value('note'));

        $this->travel(1)->hours();
        $this->artisan('webx:payments:poll')->expectsOutputToContain('holds to warn of 0')->assertSuccessful();

        // The gateway let the hold go by its term: the attempt expires, the problem with it.
        $state = $this->server()->payment($payment->uuid);
        $this->assertNotNull($state);
        $this->server()->put([...$state, 'status' => 'expired']);
        $this->artisan('webx:payments:poll')->assertSuccessful();

        $this->assertSame([PaymentStatus::Expired, null, 'The hold expired'], [$payment->refresh()->status, $payment->problem, $payment->failure_reason]);
        $this->assertNotNull($order->refresh()->payment_failed_at);
        $this->assertSame(0, Payment::query()->where('problem', SettleResult::HOLD_EXPIRING)->count());
    }
}
