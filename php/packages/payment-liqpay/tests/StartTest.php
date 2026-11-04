<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payment\LiqPay\LiqPayCheckoutType;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\GatewayUnavailable;
use WebxUi\Payments\Handoff;
use WebxUi\Payments\Method;
use WebxUi\Payments\Online\GatewayCalls;
use WebxUi\Payments\StartRequest;

/** The form for the page of LiqPay (spec §7): signed here, no request, the term always there. */
final class StartTest extends TestCase
{
    use Recorded;

    private const string UUID = '01jbexample0000000000000000';

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function the_form_goes_to_the_page_with_the_fields_of_the_table_in_their_order(): void
    {
        $handoff = $this->start(request: $this->request(expiresAt: CarbonImmutable::parse('2026-11-04 14:30:00', 'Europe/Kyiv')));

        $this->assertTrue($handoff->isPost());
        $this->assertSame('https://www.liqpay.ua/api/3/checkout', $handoff->url);
        $this->assertSame(['data', 'signature'], array_keys($handoff->fields));
        $this->assertNull($handoff->gatewayRef, 'LiqPay gives a payment_id only when the buyer sends the card.');
        $this->assertSame([
            'version' => 7,
            'public_key' => self::PUBLIC_KEY,
            'action' => 'pay',
            'amount' => '12.34',
            'currency' => 'UAH',
            'description' => 'Order 1024',
            'order_id' => self::UUID,
            'result_url' => 'https://shop.example.com/pay/return/'.self::UUID,
            'server_url' => 'https://shop.example.com/payments/notify/liqpay-checkout',
            'language' => 'en',
            'expired_date' => '2026-11-04 12:30:00',
            'paytypes' => 'card,apay,gpay',
        ], $this->sent($handoff));
    }

    #[Test]
    public function the_signature_is_the_one_of_version_7_on_the_string_data(): void
    {
        $handoff = $this->start();

        $this->assertSame(self::signed($handoff->fields['data']), $handoff->fields['signature']);
    }

    #[Test]
    public function nothing_is_asked_of_liqpay_and_the_private_key_is_not_in_the_form(): void
    {
        Http::fake();

        $handoff = $this->start();

        Http::assertNothingSent();
        $this->assertStringNotContainsString(self::PRIVATE_KEY, (string) json_encode($this->sent($handoff)));
        $this->assertStringNotContainsString(self::PRIVATE_KEY, (string) json_encode($handoff->fields));
    }

    #[Test]
    public function a_method_without_a_term_still_sends_sixty_minutes_and_tells_the_base(): void
    {
        CarbonImmutable::setTestNow('2026-11-04 10:00:00');

        $handoff = $this->start();

        $this->assertSame('2026-11-04 11:00:00', $this->sent($handoff)['expired_date']);
        $this->assertSame('2026-11-04 11:00:00', $handoff->expiresAt?->setTimezone('UTC')->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function the_term_of_the_method_is_what_the_base_is_told(): void
    {
        $expiresAt = CarbonImmutable::parse('2026-11-04 10:15:00', 'UTC');

        $this->assertTrue($expiresAt->equalTo($this->start(request: $this->request(expiresAt: $expiresAt))->expiresAt));
    }

    #[Test]
    public function a_hold_opens_with_its_action(): void
    {
        $this->assertSame('hold', $this->sent($this->start(request: $this->request(hold: true)))['action']);
    }

    /** @return array<string, array{0: string|null, 1: string, 2: string}> */
    public static function languages(): array
    {
        return [
            'auto, a Ukrainian order' => ['auto', 'uk', 'uk'],
            'auto, an English order' => ['auto', 'en', 'en'],
            'auto, any other order' => ['auto', 'ru', 'en'],
            'never set' => [null, 'uk', 'uk'],
            'always Ukrainian' => ['uk', 'en', 'uk'],
            'always English' => ['en', 'uk', 'en'],
        ];
    }

    #[Test]
    #[DataProvider('languages')]
    public function the_page_speaks_ukrainian_or_english(?string $setting, string $locale, string $expected): void
    {
        $method = self::liqpayMethod($setting === null ? [] : ['language' => $setting]);

        $this->assertSame($expected, $this->sent($this->start($method, $this->request(locale: $locale)))['language']);
    }

    #[Test]
    public function the_key_of_the_live_mode_goes_in_the_live_mode(): void
    {
        $method = self::liqpayMethod(['mode' => 'live', 'live_public_key' => 'live-public']);

        $handoff = $this->start($method, credentials: Credentials::of(['live_private_key' => 'live-private']));

        $this->assertSame('live-public', $this->sent($handoff)['public_key']);
        $this->assertSame(self::signed($handoff->fields['data'], key: 'live-private'), $handoff->fields['signature']);
    }

    #[Test]
    public function without_the_keys_of_the_mode_there_is_no_form(): void
    {
        $this->expectException(GatewayUnavailable::class);

        $this->start(credentials: Credentials::none());
    }

    private function start(?Method $method = null, ?StartRequest $request = null, ?Credentials $credentials = null): Handoff
    {
        $method ??= self::liqpayMethod();
        $credentials ??= self::testKey();
        $type = $this->app->make(LiqPayCheckoutType::class);
        $http = $this->app->make(GatewayCalls::class)->http($type, $method, $credentials, ExchangeKind::Start, 'test');

        return $type->start($method, $credentials, $request ?? $this->request(), $http);
    }

    private function request(string $locale = 'en', bool $hold = false, ?CarbonImmutable $expiresAt = null): StartRequest
    {
        return new StartRequest(
            uuid: self::UUID,
            orderNumber: '1024',
            amount: 1234,
            currency: 'UAH',
            description: 'Order 1024',
            returnUrl: 'https://shop.example.com/pay/return/'.self::UUID,
            notifyUrl: 'https://shop.example.com/payments/notify/liqpay-checkout',
            locale: $locale,
            hold: $hold,
            expiresAt: $expiresAt,
        );
    }

    /** @return array<string, mixed> `data` of the form, its fields in the order they went */
    private function sent(Handoff $handoff): array
    {
        return json_decode((string) base64_decode($handoff->fields['data'], true), true, flags: JSON_THROW_ON_ERROR);
    }
}
