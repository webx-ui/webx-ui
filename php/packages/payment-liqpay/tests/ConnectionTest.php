<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as SentRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payment\LiqPay\LiqPayCheckoutType;
use WebxUi\Payment\LiqPay\LiqPayGateway;
use WebxUi\Payments\ConnectionResult;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Online\GatewayCalls;

/** «Test the connection»: the status of a payment nobody has, on the recorded answers (spec §5.3). */
final class ConnectionTest extends TestCase
{
    use Recorded;

    #[Test]
    public function not_found_means_both_keys_are_right(): void
    {
        Http::fake(['www.liqpay.ua/*' => Http::response(self::fixture('api/sig.status.sha3.v7')['response']['json'])]);

        $result = $this->connect();

        $this->assertTrue($result->ok);
        $this->assertSame('LiqPay accepted the keys', $result->message);
    }

    #[Test]
    public function the_question_is_a_status_of_a_random_uuid_and_lands_in_the_journal(): void
    {
        $sent = [];
        Http::fake(['www.liqpay.ua/*' => static function (SentRequest $request) use (&$sent) {
            $sent[] = json_decode((string) base64_decode((string) $request['data'], true), true);

            return Http::response(self::fixture('api/sig.status.sha3.v7')['response']['json']);
        }]);

        $this->connect();
        $this->connect();

        $this->assertSame('status', $sent[0]['action']);
        $this->assertTrue(Str::isUuid($sent[0]['order_id']));
        $this->assertNotSame($sent[0]['order_id'], $sent[1]['order_id']);
        $this->assertSame(['out', 'connection'], [PaymentGatewayEvent::query()->firstOrFail()->direction, PaymentGatewayEvent::query()->firstOrFail()->kind]);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function refusals(): array
    {
        return [
            'a wrong private key' => [self::fixture('api/sig.status.bad-private.sha3')['response']['json'], 'The private key does not match'],
            'the other word for it' => [['status' => 'error', 'err_code' => 'err_signature'], 'The private key does not match'],
            'a wrong public key' => [self::fixture('api/sig.status.bad-public')['response']['json'], 'The public key was not found'],
            'anything else' => [['status' => 'error', 'err_code' => 'brand_new', 'err_description' => 'secret-looking words'], 'LiqPay did not answer brand_new'],
            'no code at all' => [['result' => 'ok', 'err_description' => 'secret-looking words'], 'LiqPay did not answer'],
        ];
    }

    /** @param  array<string, mixed>  $answer */
    #[Test]
    #[DataProvider('refusals')]
    public function a_refusal_is_words_without_the_body(array $answer, string $words): void
    {
        Http::fake(['www.liqpay.ua/*' => Http::response($answer)]);

        $result = $this->connect();

        $this->assertFalse($result->ok);
        $this->assertSame($words, $result->message);
    }

    /** @return array<string, array{0: callable}> */
    public static function silences(): array
    {
        return [
            'a timeout' => [static fn () => throw new ConnectionException('timed out')],
            'a 5xx' => [static fn () => Http::response('secret-looking body', 502)],
            'not json' => [static fn () => Http::response('<html>secret-looking body</html>')],
        ];
    }

    #[Test]
    #[DataProvider('silences')]
    public function no_answer_is_said_so(callable $answer): void
    {
        Http::fake(['www.liqpay.ua/*' => $answer]);

        $result = $this->connect();

        $this->assertFalse($result->ok);
        $this->assertSame('LiqPay did not answer', $result->message);
    }

    /** @return array<string, array{0: array<string, string>, 1: array<string, string>}> */
    public static function wrongKeys(): array
    {
        return [
            'a live public key in the test mode' => [['mode' => 'test', 'test_public_key' => 'i00000000'], ['test_private_key' => 'sandbox_private']],
            'a live private key in the test mode' => [['mode' => 'test', 'test_public_key' => 'sandbox_public'], ['test_private_key' => 'private']],
            'a sandbox public key in the live mode' => [['mode' => 'live', 'live_public_key' => 'sandbox_public'], ['live_private_key' => 'private']],
            'a sandbox private key in the live mode' => [['mode' => 'live', 'live_public_key' => 'i00000000'], ['live_private_key' => 'sandbox_private']],
        ];
    }

    /**
     * @param  array<string, string>  $settings
     * @param  array<string, string>  $keys
     */
    #[Test]
    #[DataProvider('wrongKeys')]
    public function keys_of_the_other_mode_are_refused_without_a_request(array $settings, array $keys): void
    {
        Http::fake();

        $result = $this->connect($settings, Credentials::of($keys));

        $this->assertFalse($result->ok);
        $this->assertSame('These keys do not belong to the chosen mode', $result->message);
        Http::assertNothingSent();
    }

    #[Test]
    public function live_keys_in_the_live_mode_are_asked_about(): void
    {
        Http::fake(['www.liqpay.ua/*' => Http::response(self::fixture('api/sig.status.sha3.v7')['response']['json'])]);

        $this->assertTrue($this->connect(['mode' => 'live', 'live_public_key' => 'i00000000'], Credentials::of(['live_private_key' => 'private']))->ok);
    }

    /** @return array<string, array{0: array<string, string>, 1: array<string, string>}> */
    public static function missingKeys(): array
    {
        return [
            'no public key' => [['mode' => 'test'], ['test_private_key' => 'sandbox_private']],
            'no private key' => [['mode' => 'test', 'test_public_key' => 'sandbox_public'], []],
            'the keys of the other mode' => [['mode' => 'live', 'test_public_key' => 'sandbox_public'], ['test_private_key' => 'sandbox_private']],
        ];
    }

    /**
     * @param  array<string, string>  $settings
     * @param  array<string, string>  $keys
     */
    #[Test]
    #[DataProvider('missingKeys')]
    public function without_both_keys_of_the_mode_nothing_is_asked(array $settings, array $keys): void
    {
        Http::fake();

        $result = $this->connect($settings, Credentials::of($keys));

        $this->assertFalse($result->ok);
        $this->assertSame('Enter both keys of this mode', $result->message);
        Http::assertNothingSent();
    }

    /** @param  array<string, string>|null  $settings */
    private function connect(?array $settings = null, ?Credentials $credentials = null): ConnectionResult
    {
        $settings ??= ['mode' => 'test', 'test_public_key' => self::PUBLIC_KEY];
        $credentials ??= self::testKey();
        $type = $this->app->make(LiqPayCheckoutType::class);
        $http = $this->app->make(GatewayCalls::class)->http($type, self::liqpayMethod($settings), $credentials, ExchangeKind::Connection, 'panel');

        return $this->app->make(LiqPayGateway::class)->testConnection($credentials, $settings, $http);
    }
}
