<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as SentRequest;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payment\LiqPay\Api\Answer;
use WebxUi\Payment\LiqPay\Api\Client;
use WebxUi\Payment\LiqPay\Keys;
use WebxUi\Payment\LiqPay\LiqPayCheckoutType;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\Method;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Online\GatewayCalls;

/** Our requests: `version: 7`, SHA3-256, through `GatewayHttp` (spec §3.2, §9.1). */
final class ClientTest extends TestCase
{
    use Recorded;

    #[Test]
    public function a_request_is_byte_for_byte_the_one_the_sandbox_took(): void
    {
        $recorded = self::fixture('api/sig.status.sha3.v7');
        Http::fake(['www.liqpay.ua/*' => Http::response($recorded['response']['json'])]);

        $answer = $this->client()->call('status', ['order_id' => $recorded['request']['json']['order_id']]);

        Http::assertSent(static fn (SentRequest $request): bool => $request->url() === 'https://www.liqpay.ua/api/request'
            && $request->isForm()
            && $request['data'] === $recorded['request']['data']
            && $request['signature'] === $recorded['request']['signature']);
        $this->assertSame('payment_not_found', $answer->errorCode());
    }

    #[Test]
    public function every_request_is_an_event_of_the_journal_without_the_private_key(): void
    {
        Http::fake(['www.liqpay.ua/*' => Http::response(self::fixture('api/status.after-pay')['response']['json'])]);

        $this->client()->call('status', ['order_id' => 'attempt-1']);

        $event = PaymentGatewayEvent::query()->sole();
        $this->assertSame(['out', 'status'], [$event->direction, $event->kind]);
        $this->assertStringStartsWith('data=', (string) $event->request_body);
        $this->assertStringContainsString('&signature=', (string) $event->request_body);
        $this->assertStringNotContainsString(self::PRIVATE_KEY, (string) json_encode($event->getAttributes()));
    }

    #[Test]
    public function a_payment_with_the_status_error_is_not_a_failed_request(): void
    {
        $failed = Answer::of('{"status":"error","err_code":"invalid_signature"}');
        $payment = Answer::of('{"status":"error","err_code":"limit","payment_id":123}');

        $this->assertFalse($failed->isPayment());
        $this->assertTrue($payment->isPayment());
        $this->assertSame('123', $payment->paymentId());
    }

    /** @return array<string, array{0: callable}> */
    public static function brokenAnswers(): array
    {
        return [
            'no answer' => [static fn () => throw new ConnectionException('timed out')],
            'a 5xx' => [static fn () => Http::response('secret-looking body', 502)],
            'a page' => [static fn () => Http::response('<html>secret-looking body</html>')],
        ];
    }

    #[Test]
    #[DataProvider('brokenAnswers')]
    public function a_broken_answer_is_unavailable_without_its_body(callable $answer): void
    {
        Http::fake(['www.liqpay.ua/*' => $answer]);

        try {
            $this->client()->call('status', ['order_id' => 'attempt-1']);
            $this->fail('A broken answer passed.');
        } catch (GatewayUnavailable $e) {
            $this->assertStringNotContainsString('secret-looking', $e->getMessage());
        }
    }

    #[Test]
    public function without_a_key_of_the_mode_nothing_is_sent(): void
    {
        Http::fake();

        foreach ([[self::liqpayMethod(), Credentials::none()], [self::liqpayMethod(['test_public_key' => '']), self::testKey()]] as [$method, $credentials]) {
            try {
                $this->client($method, $credentials)->call('status', ['order_id' => 'attempt-1']);
                $this->fail('A request went without a key.');
            } catch (GatewayUnavailable $e) {
                $this->assertSame('Enter both keys of this mode', $e->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    private function client(?Method $method = null, ?Credentials $credentials = null): Client
    {
        $method ??= self::liqpayMethod();
        $credentials ??= self::testKey();
        $http = $this->app->make(GatewayCalls::class)->http($this->app->make(LiqPayCheckoutType::class), $method, $credentials, ExchangeKind::Status, 'poll');

        return Client::for($http, Keys::of($method, $credentials));
    }
}
