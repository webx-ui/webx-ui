<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use WebxUi\Orders\Models\Order;
use WebxUi\Payment\LiqPay\Api\Client;
use WebxUi\Payment\LiqPay\Api\Signature;
use WebxUi\Payment\LiqPay\Keys;
use WebxUi\Payment\LiqPay\LiqPayCheckoutType;
use WebxUi\Payment\LiqPay\PaymentLiqPayServiceProvider;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Online\GatewayCalls;
use WebxUi\Payments\Online\PaymentLinks;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Tests\Checkout\PaymentsCheckoutCase;

/**
 * A notification through the base's address, from the raw body to the journal (spec §8.2, §12):
 * the body stays as it came so its signature can be checked again, no private key, no starred date.
 */
final class JournalTest extends PaymentsCheckoutCase
{
    use Recorded;

    /** A date in milliseconds that passes Luhn, as the card rule would see it. */
    private const int LUHN_DATE = 1730000000001;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        PaymentMethod::query()->where('key', LiqPayCheckoutType::METHOD)->firstOrFail()->update([
            'enabled' => true,
            'settings' => ['mode' => 'test', 'test_public_key' => self::PUBLIC_KEY],
            'credentials' => ['test_private_key' => self::PRIVATE_KEY],
        ]);
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), PaymentLiqPayServiceProvider::class];
    }

    /** @return array<string, array{0: string}> */
    public static function algorithms(): array
    {
        return ['SHA3-256' => ['sha3-256'], 'SHA1' => ['sha1']];
    }

    #[Test]
    #[DataProvider('algorithms')]
    public function a_signed_notification_pays_the_order_and_the_journal_keeps_the_body_as_it_came(string $algorithm): void
    {
        [$order, $payment] = $this->openAttempt();
        $data = $this->paid($payment);
        $body = self::body($data, self::signed($data, $algorithm));

        $response = $this->notify($body);

        $response->assertOk();
        $this->assertSame('', $response->getContent());
        $this->assertSame([PaymentStatus::Paid, 4999], [$payment->refresh()->status, $payment->paid_amount]);
        $this->assertNotNull($order->refresh()->paid_at);

        $event = $this->last();
        $this->assertSame(['in', 'notify', 'liqpay', 'applied', 'valid', 'paid', 'pending', 'paid', 200], [
            $event->direction, $event->kind, $event->gateway, $event->outcome, $event->signature, $event->reported, $event->status_before, $event->status_after, $event->response_status,
        ]);
        $this->assertSame($body, $event->request_body, 'The raw body, byte for byte.');

        parse_str((string) $event->request_body, $kept);
        $this->assertIsString($kept['data']);
        $this->assertIsString($kept['signature']);
        $this->assertTrue(Signature::verify($kept['data'], $kept['signature'], self::PRIVATE_KEY), 'The journal can check the signature again.');
        $this->assertNoPrivateKeyInTheJournal();
    }

    #[Test]
    public function a_repeat_touches_nothing_whichever_way_it_was_signed(): void
    {
        [$order, $payment] = $this->openAttempt();
        $data = $this->paid($payment);

        $this->notify(self::body($data, self::signed($data)))->assertOk();
        $first = $this->last();

        foreach (['sha3-256', 'sha1'] as $algorithm) {
            $this->notify(self::body($data, self::signed($data, $algorithm)))->assertOk();
            $repeat = $this->last();

            $this->assertSame(['duplicate', 'paid', 'paid', $first->event_key], [$repeat->outcome, $repeat->status_before, $repeat->status_after, $repeat->event_key]);
        }

        $this->assertSame(1, $order->milestones()->where('milestone', 'paid')->count());
        $this->assertSame(4999, $order->refresh()->paid_total);
    }

    #[Test]
    public function a_late_notification_of_a_status_already_there_changes_nothing(): void
    {
        [, $payment] = $this->openAttempt();
        $data = $this->paid($payment);
        $this->notify(self::body($data, self::signed($data)))->assertOk();

        $late = self::dataOf('try-again', ['order_id' => $payment->uuid]);
        $this->notify(self::body($late, self::signed($late)))->assertOk();

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame('paid', $this->last()->status_after);
    }

    #[Test]
    public function a_bad_signature_is_400_the_status_stays_and_the_event_names_the_attempt(): void
    {
        [$order, $payment] = $this->openAttempt();
        $data = $this->paid($payment);
        $body = self::body($data, self::signed($data, key: 'another-key'));

        $response = $this->notify($body);

        $response->assertStatus(400);
        $this->assertSame('', $response->getContent());
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);

        $event = $this->last();
        $this->assertSame(['rejected', 'invalid', $payment->id, $order->id, 'pending', 'pending', 400], [
            $event->outcome, $event->signature, $event->payment_id, $event->order_id, $event->status_before, $event->status_after, $event->response_status,
        ]);
        $this->assertSame($body, $event->request_body);
    }

    #[Test]
    public function a_changed_amount_under_the_old_signature_is_refused(): void
    {
        [, $payment] = $this->openAttempt();
        $signature = self::signed($this->paid($payment));
        $forged = self::dataOf('success', ['order_id' => $payment->uuid, 'amount' => 0.01, 'currency' => 'EUR']);

        $this->notify(self::body($forged, $signature))->assertStatus(400);

        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    #[Test]
    public function a_body_that_is_no_notification_is_rejected_without_a_claim(): void
    {
        $this->notify('hello=world', ['hello' => 'world'])->assertStatus(400);

        $event = $this->last();
        $this->assertSame(['rejected', 'none', null], [$event->outcome, $event->signature, $event->payment_id]);
    }

    #[Test]
    public function a_payment_nobody_has_is_unmatched_and_lives_only_in_the_journal(): void
    {
        $data = self::dataOf('success', ['order_id' => (string) Str::uuid()]);

        $this->notify(self::body($data, self::signed($data)))->assertOk();

        $event = $this->last();
        $this->assertSame(['unmatched', 'valid', 'paid', null], [$event->outcome, $event->signature, $event->reported, $event->payment_id]);
        $this->assertStringContainsString('No attempt of this method', (string) $event->message);
    }

    #[Test]
    public function an_attempt_of_another_gateway_is_unmatched_and_stays_as_it_was(): void
    {
        [, $payment] = $this->openAttempt();
        $payment->forceFill(['type' => 'gateway-a.card'])->save();
        $data = $this->paid($payment);

        $this->notify(self::body($data, self::signed($data)))->assertOk();

        $this->assertSame('unmatched', $this->last()->outcome);
        $this->assertSame(PaymentStatus::Pending, $payment->refresh()->status);
    }

    #[Test]
    public function a_subscription_is_ignored(): void
    {
        $data = self::dataOf('success', ['action' => 'subscribe', 'status' => 'subscribed']);

        $this->notify(self::body($data, self::signed($data)))->assertOk();

        $this->assertSame(['ignored', 'valid'], [$this->last()->outcome, $this->last()->signature]);
    }

    #[Test]
    public function the_buyer_goes_to_the_page_with_a_signed_form_and_no_private_key_in_the_journal(): void
    {
        [, $payment] = $this->openAttempt();
        $payment->forceFill(['status' => 'created'])->save();

        $page = $this->get($this->app->make(PaymentLinks::class)->go($payment))->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNotNull($payment->expires_at, 'The term goes always, and the poll closes the attempt by it.');
        $this->assertStringContainsString('https://www.liqpay.ua/api/3/checkout', (string) $page->getContent());

        $event = PaymentGatewayEvent::query()->where('kind', 'handoff')->sole();
        $this->assertSame('browser', $event->direction);
        $this->assertStringContainsString('name="signature"', (string) $event->response_body);
        $this->assertSame(0, PaymentGatewayEvent::query()->where('kind', 'start')->count(), 'start() asks LiqPay nothing.');
        $this->assertNoPrivateKeyInTheJournal();
    }

    #[Test]
    public function a_return_with_no_parameters_finds_the_payment_by_asking(): void
    {
        [, $payment] = $this->openAttempt();
        Http::fake(['www.liqpay.ua/*' => Http::response([
            ...self::fixture('api/page.success.status-after-return')['response']['json'],
            'order_id' => $payment->uuid,
            'amount' => 49.99,
            'currency' => 'EUR',
        ])]);

        $this->get($this->app->make(PaymentLinks::class)->returnUrl($payment))->assertRedirect();

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame(1, PaymentGatewayEvent::query()->where(['direction' => 'out', 'kind' => 'status'])->count());
    }

    #[Test]
    public function a_buyer_back_without_paying_leaves_the_attempt_open_and_marked(): void
    {
        [, $payment] = $this->openAttempt();
        Http::fake(['www.liqpay.ua/*' => Http::response(self::fixture('api/checkout.status-before-paying')['response']['json'])]);

        $this->get($this->app->make(PaymentLinks::class)->returnUrl($payment))->assertRedirect();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertTrue($payment->notStarted());
    }

    #[Test]
    public function a_refusal_with_try_again_keeps_the_attempt_open_and_says_why_in_the_journal(): void
    {
        [, $payment] = $this->openAttempt();
        Http::fake(['www.liqpay.ua/*' => Http::response([
            ...self::fixture('api/page.decline.status-after-return')['response']['json'],
            'order_id' => $payment->uuid,
        ])]);

        $this->get($this->app->make(PaymentLinks::class)->returnUrl($payment))->assertRedirect();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertFalse($payment->notStarted());
        $this->assertTrue($payment->declined());
        $this->assertSame('The bank refused: a limit of the card was reached', $payment->declineReason());
    }

    #[Test]
    public function a_buyer_refused_by_the_bank_is_told_so_and_the_page_stops_asking(): void
    {
        [$order, $payment] = $this->openAttempt();
        Http::fake(['www.liqpay.ua/*' => Http::response([
            ...self::fixture('api/page.decline.status-after-return')['response']['json'],
            'order_id' => $payment->uuid,
        ])]);
        $links = $this->app->make(PaymentLinks::class);

        $this->get($links->returnUrl($payment))->assertRedirect();

        $this->get($links->page($order))
            ->assertOk()
            ->assertSee('the bank declined the card')
            ->assertSee('The bank refused: a limit of the card was reached')
            ->assertSee('Pay again')
            ->assertDontSee('We are checking')
            ->assertDontSee('http-equiv="refresh"', false);
    }

    #[Test]
    public function a_reversed_notification_asks_and_the_answer_decides(): void
    {
        [, $payment] = $this->openAttempt();
        $paid = $this->paid($payment);
        $this->notify(self::body($paid, self::signed($paid)))->assertOk();
        Http::fake(['www.liqpay.ua/*' => Http::response([
            ...self::fixture('api/late.refund-after-rest')['response']['json'],
            'order_id' => $payment->uuid,
            'amount' => 49.99,
            'refund_amount' => 49.99,
            'currency' => 'EUR',
        ])]);

        $data = self::dataOf('reversed', ['order_id' => $payment->uuid]);
        $this->notify(self::body($data, self::signed($data)))->assertOk();

        $payment->refresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(4999, $payment->refunded_amount);
    }

    #[Test]
    public function the_dates_of_an_answer_stay_and_a_card_number_beside_them_does_not(): void
    {
        $answer = [
            ...self::fixture('api/status.after-pay')['response']['json'],
            'create_date' => self::LUHN_DATE,
            'end_date' => self::LUHN_DATE,
            'payment_id' => self::LUHN_DATE,
            'note' => '4242424242424242',
        ];
        Http::fake(['www.liqpay.ua/*' => Http::response($answer)]);

        $type = $this->app->make(LiqPayCheckoutType::class);
        $method = self::liqpayMethod();
        $http = $this->app->make(GatewayCalls::class)->http($type, $method, self::testKey(), ExchangeKind::Status, 'poll');

        Client::for($http, Keys::of($method, self::testKey()))->call('status', ['order_id' => 'attempt-1']);

        $event = $this->last();
        $kept = json_decode((string) $event->response_body, true);
        $this->assertSame([self::LUHN_DATE, self::LUHN_DATE, self::LUHN_DATE], [$kept['create_date'], $kept['end_date'], $kept['payment_id']]);
        $this->assertStringNotContainsString('4242424242424242', (string) $event->response_body);
        $this->assertSame(1, $event->masked['pan'] ?? null);
        $this->assertNoPrivateKeyInTheJournal();
    }

    /** @return array{0: Order, 1: Payment} an order of 49,99 € and its attempt, open at LiqPay */
    private function openAttempt(): array
    {
        $order = $this->place($this->cart(), [
            'awaitPayment' => true,
            'blocks' => ['payment' => [
                'method' => LiqPayCheckoutType::METHOD,
                'type' => LiqPayCheckoutType::KEY,
                'kind' => 'online',
                'title' => ['en' => 'LiqPay'],
                'price' => null,
            ]],
        ]);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'method_key' => LiqPayCheckoutType::METHOD,
            'type' => LiqPayCheckoutType::KEY,
            'amount' => 4999,
            'currency' => 'EUR',
            'status' => 'pending',
            'source' => 'test',
        ]);

        return [$order, $payment];
    }

    private function paid(Payment $payment): string
    {
        return self::dataOf('success', ['order_id' => $payment->uuid, 'amount' => 49.99, 'currency' => 'EUR']);
    }

    private static function body(string $data, string $signature): string
    {
        return http_build_query(['data' => $data, 'signature' => $signature]);
    }

    /**
     * A form as it comes over the network: the raw body, and PHP's reading of it.
     *
     * @param  array<string, string>|null  $fields
     * @return TestResponse<Response>
     */
    private function notify(string $body, ?array $fields = null): TestResponse
    {
        if ($fields === null) {
            parse_str($body, $fields);
        }

        return $this->call('POST', $this->app->make(PaymentLinks::class)->notifyUrl(LiqPayCheckoutType::METHOD), $fields, [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], $body);
    }

    private function last(): PaymentGatewayEvent
    {
        return PaymentGatewayEvent::query()->latest('id')->firstOrFail();
    }

    private function assertNoPrivateKeyInTheJournal(): void
    {
        $hidden = [self::PRIVATE_KEY, base64_encode(self::PRIVATE_KEY), rawurlencode(self::PRIVATE_KEY)];

        foreach (PaymentGatewayEvent::query()->orderBy('id')->get() as $event) {
            foreach ($hidden as $value) {
                $this->assertStringNotContainsString($value, (string) json_encode($event->getAttributes()), "Event #{$event->id} keeps the private key.");
            }
        }
    }
}
