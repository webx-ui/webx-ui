<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as SentRequest;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payment\LiqPay\LiqPayCheckoutType;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\GatewayStatus;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Online\GatewayCalls;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Reported;

/** `action=status` and the table of §9.2, one row at a time, on the recorded answers (spec §9). */
final class StatusTest extends TestCase
{
    use Recorded;

    /** @var array<string, mixed> */
    private array $answer = [];

    #[Test]
    public function the_question_is_about_our_attempt(): void
    {
        $sent = null;
        Http::fake(['www.liqpay.ua/*' => static function (SentRequest $request) use (&$sent) {
            $sent = json_decode((string) base64_decode((string) $request['data'], true), true);

            return Http::response(self::fixture('api/status.after-pay')['response']['json']);
        }]);

        $this->fetch(self::recorded('api/status.after-pay'));

        $this->assertSame(['version' => 7, 'public_key' => self::PUBLIC_KEY, 'action' => 'status', 'order_id' => 'attempt-1'], $sent);
    }

    /** @return array<string, array{0: string, 1: Reported}> */
    public static function rows(): array
    {
        $rows = [
            'success' => Reported::Paid,
            'wait_compensation' => Reported::Paid,
            'wait_accept' => Reported::Paid,
            'wait_reserve' => Reported::Paid,
            'hold_wait' => Reported::Authorized,
            'failure' => Reported::Failed,
            'error' => Reported::Failed,
            'reversed' => Reported::Refunded,
            'try_again' => Reported::Pending,
            '3ds_verify' => Reported::Pending,
            'captcha_verify' => Reported::Pending,
            'cvv_verify' => Reported::Pending,
            'ivr_verify' => Reported::Pending,
            'otp_verify' => Reported::Pending,
            'password_verify' => Reported::Pending,
            'phone_verify' => Reported::Pending,
            'pin_verify' => Reported::Pending,
            'senderapp_verify' => Reported::Pending,
            'wait_qr' => Reported::Pending,
            'wait_sender' => Reported::Pending,
            'processing' => Reported::Pending,
            'prepared' => Reported::Pending,
            'cash_wait' => Reported::Pending,
            'invoice_wait' => Reported::Pending,
            'wait_secure' => Reported::Pending,
            'wait_card' => Reported::Pending,
            'wait_lc' => Reported::Pending,
            'subscribed' => Reported::Pending,
            'unsubscribed' => Reported::Pending,
        ];

        return array_combine(array_keys($rows), array_map(null, array_keys($rows), array_values($rows)));
    }

    #[Test]
    #[DataProvider('rows')]
    public function every_row_of_the_table(string $status, Reported $expected): void
    {
        $this->assertSame($expected, $this->fetch(self::recorded('api/status.after-pay', ['status' => $status]))->status);
    }

    #[Test]
    public function a_paid_payment_says_how_much_when_and_what_with(): void
    {
        $recorded = self::recorded('api/status.after-pay');
        $status = $this->fetch($recorded);

        $this->assertSame(Reported::Paid, $status->status);
        $this->assertSame((string) $recorded['payment_id'], $status->gatewayRef);
        $this->assertSame((int) round($recorded['amount'] * 100), $status->amount);
        $this->assertSame($recorded['currency'], $status->currency);
        $this->assertSame($recorded['end_date'], $status->paidAt?->getTimestampMs());
        $this->assertNull($status->reason);
        $this->assertSame($recorded['paytype'], $status->meta['paytype']);
    }

    #[Test]
    public function the_amount_of_the_order_counts_not_the_amount_debited(): void
    {
        $status = $this->fetch(self::recorded('api/currency.USD'));

        $this->assertSame('USD', $status->currency);
        $this->assertSame(1234, $status->amount);
    }

    #[Test]
    public function wait_accept_is_paid_and_its_note_goes_to_the_journal_by_the_reason(): void
    {
        $status = $this->fetch(self::recorded('api/status.after-pay', ['status' => 'wait_accept']));

        $this->assertSame(Reported::Paid, $status->status);
        $this->assertSame('LiqPay has not checked the shop yet: the money comes after the check', $status->reason);
    }

    #[Test]
    public function wait_reserve_names_its_status(): void
    {
        $this->assertSame('The payment went through; the money is reserved for an earlier refund', $this->fetch(self::recorded('api/status.after-pay', ['status' => 'wait_reserve']))->reason);
    }

    #[Test]
    public function try_again_stays_pending_with_the_reason_of_the_bank(): void
    {
        foreach (['api/page.decline.status-after-return', 'api/late.try-again'] as $fixture) {
            $status = $this->fetch(self::recorded($fixture));

            $this->assertSame(Reported::Pending, $status->status, $fixture);
            $this->assertFalse($status->notStarted, $fixture);
            $this->assertTrue($status->declined, $fixture);
            $this->assertSame('The bank refused: a limit of the card was reached', $status->reason, $fixture);
        }
    }

    #[Test]
    public function a_try_again_that_succeeded_is_paid_on_the_same_payment(): void
    {
        $this->assertSame(Reported::Paid, $this->fetch(self::recorded('api/page.decline.status-after-retry'))->status);
    }

    #[Test]
    public function a_verification_waits_for_the_buyer_and_is_no_failure(): void
    {
        $status = $this->fetch(self::recorded('api/page.otp.status-after-return'));

        $this->assertSame(Reported::Pending, $status->status);
        $this->assertSame('LiqPay waits for more data from the buyer', $status->reason);
    }

    #[Test]
    public function a_refusal_says_why_in_words_and_an_unknown_code_in_those_of_liqpay(): void
    {
        $failed = $this->fetch(self::recorded('api/status.after-failure'));

        $this->assertSame(Reported::Failed, $failed->status);
        $this->assertNotNull($failed->reason);

        $this->assertSame('Something new', $this->fetch(self::recorded('api/status.after-failure', ['err_code' => 'brand_new', 'err_description' => 'Something new']))->reason);
    }

    #[Test]
    public function a_page_past_its_term_is_expired_and_not_failed(): void
    {
        $status = $this->fetch(self::recorded('api/expiry.short.status-after-pay'));

        $this->assertSame(Reported::Expired, $status->status);
        $this->assertSame('The time of the payment is over', $status->reason);
    }

    #[Test]
    public function an_unknown_status_is_pending_and_keeps_the_word_of_liqpay(): void
    {
        $status = $this->fetch(self::recorded('api/status.after-pay', ['status' => 'brand_new']));

        $this->assertSame(Reported::Pending, $status->status);
        $this->assertSame('Unknown status of LiqPay: brand_new', $status->reason);
    }

    #[Test]
    public function a_payment_nobody_began_is_pending_and_not_started(): void
    {
        foreach (['api/checkout.status-before-paying', 'api/sig.status.sha3.v7'] as $fixture) {
            $status = $this->fetch(self::recorded($fixture));

            $this->assertSame(Reported::Pending, $status->status, $fixture);
            $this->assertTrue($status->notStarted, $fixture);
            $this->assertNull($status->gatewayRef, $fixture);
        }
    }

    #[Test]
    public function a_payment_with_the_status_error_is_failed_and_a_request_with_it_is_not(): void
    {
        $payment = $this->fetch(['status' => 'error', 'err_code' => 'limit', 'payment_id' => 123, 'amount' => 12.34, 'currency' => 'UAH']);

        $this->assertSame(Reported::Failed, $payment->status);

        foreach (['api/sig.status.bad-private.sha3' => 'The signature does not match: check the private key', 'api/sig.status.bad-public' => 'LiqPay does not know this public key'] as $fixture => $words) {
            try {
                $this->fetch(self::recorded($fixture));
                $this->fail($fixture.' passed as a status.');
            } catch (GatewayUnavailable $e) {
                $this->assertSame($words, $e->getMessage());
            }
        }
    }

    #[Test]
    public function an_unknown_error_of_a_request_does_not_carry_the_body(): void
    {
        try {
            $this->fetch(['status' => 'error', 'err_code' => 'brand_new', 'err_description' => 'secret-looking words']);
            $this->fail('A failed request passed as a status.');
        } catch (GatewayUnavailable $e) {
            $this->assertSame('LiqPay did not answer', $e->getMessage());
        }
    }

    #[Test]
    public function no_answer_is_unavailable(): void
    {
        Http::fake(['www.liqpay.ua/*' => static fn () => throw new ConnectionException('timed out')]);

        $this->expectException(GatewayUnavailable::class);

        $this->ask($this->payment(PaymentStatus::Pending));
    }

    #[Test]
    public function reversed_lets_a_hold_go_and_gives_back_a_paid_payment(): void
    {
        $released = self::recorded('api/hold.status-after-release');
        $refunded = self::recorded('api/late.refund-after-rest');

        $this->assertSame(Reported::Cancelled, $this->fetch($released, PaymentStatus::Authorized)->status);

        $status = $this->fetch($refunded, PaymentStatus::Paid);
        $this->assertSame(Reported::Refunded, $status->status);
        $this->assertSame((int) round($refunded['refund_amount'] * 100), $status->refundedAmount);
    }

    #[Test]
    public function success_with_a_refund_is_a_refund_only_of_a_paid_attempt(): void
    {
        $answer = self::recorded('api/status.after-pay', ['refund_amount' => 1.5]);

        $status = $this->fetch($answer, PaymentStatus::Paid);
        $this->assertSame([Reported::Refunded, 150], [$status->status, $status->refundedAmount]);

        $this->assertSame(Reported::Paid, $this->fetch($answer, PaymentStatus::Pending)->status);
    }

    #[Test]
    public function a_refund_just_made_still_reads_as_paid(): void
    {
        $this->assertSame(Reported::Paid, $this->fetch(self::recorded('api/refund.status-after-rest'), PaymentStatus::Paid)->status);
    }

    #[Test]
    public function a_hold_is_authorized_with_its_amount(): void
    {
        $status = $this->fetch(self::recorded('api/page.hold.status-after-return'));

        $this->assertSame(Reported::Authorized, $status->status);
        $this->assertSame(1234, $status->amount);
    }

    /**
     * The json of a recorded answer with some of its fields changed.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private static function recorded(string $fixture, array $changes = []): array
    {
        return [...self::fixture($fixture)['response']['json'], ...$changes];
    }

    /** @param  array<string, mixed>  $answer */
    private function fetch(array $answer, PaymentStatus $attempt = PaymentStatus::Pending): GatewayStatus
    {
        $this->answer = $answer;

        // Fakes pile up and the first one answers: all of them read the same answer.
        Http::fake(['www.liqpay.ua/*' => fn () => Http::response($this->answer)]);

        return $this->ask($this->payment($attempt));
    }

    private function ask(Payment $payment): GatewayStatus
    {
        $type = $this->app->make(LiqPayCheckoutType::class);
        $method = self::liqpayMethod();
        $http = $this->app->make(GatewayCalls::class)->http($type, $method, self::testKey(), ExchangeKind::Status, 'test');

        return $type->fetchStatus($method, self::testKey(), $payment, $http);
    }

    private function payment(PaymentStatus $status): Payment
    {
        $payment = new Payment;
        $payment->forceFill(['uuid' => 'attempt-1', 'amount' => 1234, 'currency' => 'UAH', 'status' => $status]);

        return $payment;
    }
}
