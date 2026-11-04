<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request as SentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payment\LiqPay\LiqPayCheckoutType;
use WebxUi\Payment\LiqPay\LiqPayGateway;
use WebxUi\Payments\Contracts\ChargesOnline;
use WebxUi\Payments\Contracts\Gateway;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\Method;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Reported;
use WebxUi\Payments\Testing\GatewayContract;

/**
 * The contract of a satellite (base §21.3) on the recorded answers (spec §17.2).
 */
final class GatewayContractTest extends TestCase
{
    use GatewayContract;
    use Recorded;

    private PromiseInterface $next;

    protected function gateway(): Gateway
    {
        return $this->app->make(Gateways::class)->get(LiqPayGateway::KEY);
    }

    protected function methodTypes(): array
    {
        return [$this->app->make(LiqPayCheckoutType::class)];
    }

    protected function credentials(ChargesOnline $type): Credentials
    {
        return self::testKey();
    }

    protected function method(ChargesOnline $type): Method
    {
        return self::liqpayMethod();
    }

    protected function answerStatus(ChargesOnline $type, Payment $payment, string $gatewayStatus): void
    {
        $this->answer(Http::response([
            ...self::fixture('api/status.after-pay')['response']['json'],
            'order_id' => $payment->uuid,
            'status' => $gatewayStatus,
            'amount' => $payment->amount / 100,
            'currency' => $payment->currency,
        ]));
    }

    /** LiqPay as the recordings show it: it takes what is asked up to the hold, and lets it go. */
    protected function answerAuthorized(ChargesOnline $type, Payment $payment): void
    {
        $payment->forceFill(['status' => PaymentStatus::Authorized, 'authorized_amount' => $payment->amount]);

        Http::fake(['www.liqpay.ua/*' => static function (SentRequest $request) use ($payment) {
            $sent = json_decode((string) base64_decode((string) $request['data'], true), true);
            $held = $payment->amount / 100;

            return Http::response(match (true) {
                $sent['action'] === 'refund' => self::fixture('api/hold.release-refund')['response']['json'],
                (float) $sent['amount'] > $held => self::fixture('api/hold.completion-above')['response']['json'],
                default => [...self::fixture('api/hold.completion-full')['response']['json'], 'order_id' => $payment->uuid, 'amount' => (float) $sent['amount'], 'currency' => $payment->currency],
            });
        }]);
    }

    protected function answerError(ChargesOnline $type, Payment $payment): string
    {
        $this->answer(Http::response('<html>upstream failed</html>', 502));

        return 'upstream failed';
    }

    protected function statusTable(ChargesOnline $type): array
    {
        return self::table();
    }

    protected function notification(ChargesOnline $type, Payment $payment, string $variant): ?Request
    {
        $data = self::dataOf('success', ['order_id' => $payment->uuid]);
        $signature = self::signed($data);

        return match ($variant) {
            'valid' => self::posted($data, $signature),
            'signature' => self::posted($data, self::signed($data, key: 'another-key')),
            'amount' => self::posted(self::dataOf('success', ['order_id' => $payment->uuid, 'amount' => 0.01]), $signature),
            'status' => self::posted(self::dataOf('failure', ['order_id' => $payment->uuid]), $signature),
            'unrelated' => self::posted($other = self::dataOf('success', ['order_id' => $payment->uuid, 'action' => 'subscribe', 'status' => 'subscribed']), self::signed($other)),
            default => null,
        };
    }

    #[Test]
    public function every_status_of_the_table_comes_through_a_notification(): void
    {
        $type = $this->app->make(LiqPayCheckoutType::class);

        foreach (self::table() as $status => $reported) {
            $data = self::dataOf('success', ['status' => $status]);
            $read = $type->readNotification($this->method($type), $this->credentials($type), self::posted($data, self::signed($data)), $this->http($type, ExchangeKind::Status, $this->payment($type)));

            $this->assertSame($reported, $read->status?->status, $status);
        }
    }

    /**
     * The table of §9.2, written out and not taken from the code it checks; `reversed` depends on the
     * attempt, and subscriptions are unrelated in a notification.
     *
     * @return array<string, Reported>
     */
    private static function table(): array
    {
        return [
            'success' => Reported::Paid,
            'wait_compensation' => Reported::Paid,
            'wait_accept' => Reported::Paid,
            'wait_reserve' => Reported::Paid,
            'hold_wait' => Reported::Authorized,
            'failure' => Reported::Failed,
            'error' => Reported::Failed,
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
        ];
    }

    private static function posted(string $data, string $signature): Request
    {
        return Request::create('/payments/notify/liqpay-checkout', 'POST', ['data' => $data, 'signature' => $signature]);
    }

    /** Fakes pile up and the first one answers: all of them read the same answer. */
    private function answer(PromiseInterface $response): void
    {
        $this->next = $response;
        Http::fake(['www.liqpay.ua/*' => fn (): PromiseInterface => $this->next]);
    }
}
