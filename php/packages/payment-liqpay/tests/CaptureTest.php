<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as SentRequest;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Models\Order;
use WebxUi\Payment\LiqPay\Api\Answer;
use WebxUi\Payment\LiqPay\Api\Statuses;
use WebxUi\Payment\LiqPay\LiqPayCheckoutType;
use WebxUi\Payment\LiqPay\PaymentLiqPayServiceProvider;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\GatewayHttp;
use WebxUi\Payments\GatewayRefused;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\HoldRefused;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Online\GatewayCalls;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Reported;
use WebxUi\Payments\SettleResult;
use WebxUi\Payments\Tests\Checkout\PaymentsCheckoutCase;

/** The hold of §10 on the recorded answers: the type alone, then through the base and the journal. */
final class CaptureTest extends PaymentsCheckoutCase
{
    use Recorded;

    /** @var list<array<string, mixed>> what went to LiqPay, decoded */
    private array $sent = [];

    /** @var list<array<string, mixed>> the next answers, in order */
    private array $answers = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        PaymentMethod::query()->where('key', LiqPayCheckoutType::METHOD)->firstOrFail()->update([
            'enabled' => true,
            'settings' => ['mode' => 'test', 'test_public_key' => self::PUBLIC_KEY, 'hold' => true],
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

    #[Test]
    public function the_type_holds_for_the_smaller_guarantee_and_takes_a_part(): void
    {
        $type = $this->type();

        $this->assertSame(7, $type->maxHoldDays());
        $this->assertTrue($type->capturesPartially());
    }

    #[Test]
    public function a_capture_sends_the_attempt_and_the_amount_as_a_decimal_string(): void
    {
        $this->answers('api/hold.completion-part');

        $this->capture(500);

        $this->assertSame(['version' => 7, 'public_key' => self::PUBLIC_KEY, 'action' => 'hold_completion', 'order_id' => 'attempt-1', 'amount' => '5.00'], $this->sent[0]);
    }

    #[Test]
    public function the_whole_hold_is_paid_with_what_was_taken(): void
    {
        $this->answers('api/hold.completion-full');

        $status = $this->capture(1234);

        $this->assertSame([Reported::Paid, 1234, 'UAH'], [$status->status, $status->amount, $status->currency]);
        $this->assertNotNull($status->paidAt);
    }

    #[Test]
    public function a_part_is_paid_with_the_part(): void
    {
        foreach (['api/hold.completion-part', 'api/page.hold.completion-part'] as $fixture) {
            $this->answers($fixture);

            $status = $this->capture(500);

            $this->assertSame([Reported::Paid, 500], [$status->status, $status->amount], $fixture);
        }
    }

    #[Test]
    public function more_than_is_held_is_refused_in_words(): void
    {
        $this->answers('api/hold.completion-above');

        $this->assertRefused('The amount is more than is held', fn () => $this->capture(9900));
    }

    #[Test]
    public function a_part_of_a_hold_in_another_currency_is_refused_with_what_to_do(): void
    {
        $this->answers('api/hold.completion-part-eur');

        $this->assertRefused('LiqPay captures a part only of a payment in hryvnias: capture the whole sum or release the hold', fn () => $this->capture(500));
    }

    #[Test]
    public function a_lost_answer_is_found_by_asking_and_nothing_is_taken_twice(): void
    {
        $this->answers('api/hold.completion-repeat', 'api/hold.status-after-full');

        $status = $this->capture(1234);

        $this->assertSame([Reported::Paid, 1234], [$status->status, $status->amount]);
        $this->assertSame(['hold_completion', 'status'], array_column($this->sent, 'action'));
    }

    #[Test]
    public function a_capture_of_a_hold_that_is_gone_is_refused_in_words(): void
    {
        $this->answers('api/hold.completion-after-release', 'api/hold.status-after-release');

        $this->assertRefused('LiqPay does not allow this in the present status of the payment', fn () => $this->capture(1234));
    }

    #[Test]
    public function a_capture_that_failed_keeps_the_attempt_and_says_so(): void
    {
        $this->fake([...self::answer('api/hold.completion-full'), 'status' => 'failure', 'err_code' => 'limit']);

        $this->assertRefused('The bank refused: a limit of the card was reached', fn () => $this->capture(1234));
    }

    #[Test]
    public function a_code_the_dictionary_does_not_know_never_shows_the_body(): void
    {
        $this->fake(['result' => 'error', 'status' => 'error', 'err_code' => 'something_new', 'err_description' => 'raw words of the gateway']);

        $this->assertRefused('LiqPay refused the operation', fn () => $this->capture(1234));
    }

    #[Test]
    public function a_timeout_is_unavailable_and_not_a_refusal(): void
    {
        Http::fake(['www.liqpay.ua/*' => static fn () => throw new ConnectionException('timed out')]);

        $this->expectException(GatewayUnavailable::class);

        $this->capture(1234);
    }

    #[Test]
    public function a_release_is_a_refund_of_all_that_is_held(): void
    {
        $this->answers('api/hold.release-refund');

        $status = $this->void($this->inMemory(authorized: 1000));

        $this->assertSame(Reported::Cancelled, $status->status);
        $this->assertSame(
            ['version' => 7, 'public_key' => self::PUBLIC_KEY, 'action' => 'refund', 'order_id' => 'attempt-1', 'amount' => '10.00', 'currency' => 'UAH'],
            $this->sent[0],
        );
    }

    #[Test]
    public function a_refused_release_says_why_in_words(): void
    {
        $this->answers('api/hold.release-part-currency');

        $this->assertRefused('The hold cannot be released in part', fn () => $this->void($this->inMemory()));
    }

    #[Test]
    public function a_paid_attempt_is_never_refunded_by_a_release(): void
    {
        $this->answers('api/hold.release-refund');

        $this->assertRefused('LiqPay does not allow this in the present status of the payment', fn () => $this->void($this->inMemory(status: PaymentStatus::Paid)));
        $this->assertSame([], $this->sent);
    }

    #[Test]
    public function the_card_decides_how_long_a_hold_lasts(): void
    {
        $answer = self::answer('api/page.hold.status-after-return');
        $created = CarbonImmutable::createFromTimestampMs($answer['create_date']);

        $this->assertEquals($created->addDays(10), Statuses::read(new Answer($answer))->holdExpiresAt);
        $this->assertEquals($created->addDays(7), Statuses::read(new Answer([...$answer, 'sender_card_type' => 'mc']))->holdExpiresAt);
        $this->assertEquals($created->addDays(7), Statuses::read(new Answer([...$answer, 'sender_card_type' => null]))->holdExpiresAt);
    }

    #[Test]
    public function only_a_hold_has_an_end_and_an_answer_without_a_date_leaves_it_to_the_base(): void
    {
        $this->assertNull(Statuses::read(new Answer(self::answer('api/hold.completion-full')))->holdExpiresAt);
        $this->assertNull(Statuses::read(new Answer([...self::answer('api/hold.status'), 'create_date' => null]))->holdExpiresAt);
    }

    #[Test]
    public function a_part_through_the_base_is_money_in_with_the_rest_still_to_pay(): void
    {
        [$order, $payment] = $this->held();
        $this->fake([...self::answer('api/hold.completion-part'), 'order_id' => $payment->uuid, 'amount' => 20, 'currency' => 'EUR']);

        $this->app->make(Payments::class)->capture($payment, 2000, 'panel');

        $payment->refresh();
        $this->assertSame([PaymentStatus::Paid, 2000, 2000, null], [$payment->status, $payment->capture_amount, $payment->paid_amount, $payment->problem]);
        $this->assertNull($order->refresh()->paid_at);
        $this->assertSame('20.00', $this->sent[0]['amount']);
        $this->assertSame(1, PaymentGatewayEvent::query()->where('payment_id', $payment->id)->where('kind', ExchangeKind::Capture->value)->count());
    }

    #[Test]
    public function the_whole_hold_through_the_base_pays_the_order(): void
    {
        [$order, $payment] = $this->held();
        $this->fake([...self::answer('api/hold.completion-full'), 'order_id' => $payment->uuid, 'amount' => 49.99, 'currency' => 'EUR']);

        $this->app->make(Payments::class)->capture($payment, null, 'panel');

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertNotNull($order->refresh()->paid_at);
    }

    #[Test]
    public function the_base_stops_more_than_is_held_before_liqpay_hears_of_it(): void
    {
        [, $payment] = $this->held();
        $this->fake(self::answer('api/hold.completion-above'));

        try {
            $this->app->make(Payments::class)->capture($payment, 5000, 'panel');
            $this->fail('A capture above the hold went through.');
        } catch (HoldRefused $refused) {
            $this->assertSame(HoldRefused::TOO_MUCH, $refused->reason);
        }

        $this->assertSame([], $this->sent);
        $this->assertSame(PaymentStatus::Authorized, $payment->refresh()->status);
    }

    #[Test]
    public function a_refusal_of_liqpay_leaves_the_hold_with_a_problem_and_words(): void
    {
        [, $payment] = $this->held();
        $this->fake(self::answer('api/hold.completion-above'));

        $result = $this->app->make(Payments::class)->capture($payment, 4999, 'panel');

        $payment->refresh();
        $this->assertSame(SettleResult::CAPTURE_FAILED, $result->problem);
        $this->assertSame([PaymentStatus::Authorized, SettleResult::CAPTURE_FAILED], [$payment->status, $payment->problem]);
        $this->assertStringContainsString('The amount is more than is held', (string) $result->message);
    }

    #[Test]
    public function a_release_through_the_base_cancels_and_the_order_still_awaits_payment(): void
    {
        [$order, $payment] = $this->held();
        $this->fake(self::answer('api/hold.release-refund'));

        $this->app->make(Payments::class)->void($payment, 'panel');

        $this->assertSame(PaymentStatus::Cancelled, $payment->refresh()->status);
        $this->assertNull($order->refresh()->paid_at);
        $this->assertSame('49.99', $this->sent[0]['amount']);
        $this->assertSame('EUR', $this->sent[0]['currency']);
        $this->assertSame(1, PaymentGatewayEvent::query()->where('payment_id', $payment->id)->where('kind', ExchangeKind::Void->value)->count());
    }

    #[Test]
    public function the_private_key_stays_out_of_the_journal_of_a_hold(): void
    {
        [, $payment] = $this->held();
        $this->fake(self::answer('api/hold.release-refund'));

        $this->app->make(Payments::class)->void($payment, 'panel');

        $this->assertStringNotContainsString(self::PRIVATE_KEY, (string) json_encode(PaymentGatewayEvent::query()->get()->toArray()));
    }

    private function type(): LiqPayCheckoutType
    {
        return $this->app->make(LiqPayCheckoutType::class);
    }

    private function http(ExchangeKind $kind): GatewayHttp
    {
        return $this->app->make(GatewayCalls::class)->http($this->type(), self::liqpayMethod(), self::testKey(), $kind, 'test');
    }

    private function capture(int $amount): GatewayStatus
    {
        return $this->type()->capture(self::liqpayMethod(), self::testKey(), $this->inMemory(), $amount, $this->http(ExchangeKind::Capture));
    }

    private function void(Payment $payment): GatewayStatus
    {
        return $this->type()->void(self::liqpayMethod(), self::testKey(), $payment, $this->http(ExchangeKind::Void));
    }

    /** An attempt that lives only in memory, for the type alone. */
    private function inMemory(PaymentStatus $status = PaymentStatus::Authorized, ?int $authorized = null): Payment
    {
        $payment = new Payment;
        $payment->forceFill(['uuid' => 'attempt-1', 'amount' => 1234, 'authorized_amount' => $authorized, 'currency' => 'UAH', 'status' => $status]);

        return $payment;
    }

    /** @return array{0: Order, 1: Payment} an order of 49,99 € with the whole of it held */
    private function held(): array
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
            'authorized_amount' => 4999,
            'currency' => 'EUR',
            'status' => 'authorized',
            'hold' => true,
            'source' => 'test',
        ]);

        return [$order, $payment];
    }

    /** @return array<string, mixed> */
    private static function answer(string $fixture): array
    {
        return self::fixture($fixture)['response']['json'];
    }

    /** The recorded answers, one for each request. */
    private function answers(string ...$fixtures): void
    {
        $this->fake(...array_map(self::answer(...), $fixtures));
    }

    /** @param  array<string, mixed>  ...$answers */
    private function fake(array ...$answers): void
    {
        $this->sent = [];
        $this->answers = $answers;

        // Fakes pile up and the first one answers: all of them read the same list.
        Http::fake(['www.liqpay.ua/*' => function (SentRequest $request) {
            $this->sent[] = json_decode((string) base64_decode((string) $request['data'], true), true);

            return Http::response(array_shift($this->answers));
        }]);
    }

    private function assertRefused(string $words, callable $call): void
    {
        try {
            $call();
            $this->fail('LiqPay refused and the call went through.');
        } catch (GatewayRefused $refused) {
            $this->assertSame($words, $refused->getMessage());
        }
    }
}
