<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payment\LiqPay\LiqPayCheckoutType;
use WebxUi\Payment\LiqPay\LiqPayGateway;
use WebxUi\Payments\Credentials;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Handoff;
use WebxUi\Payments\Method;
use WebxUi\Payments\Online\GatewayCalls;
use WebxUi\Payments\StartRequest;

/** The seven switches of a method and what they make of `paytypes` (spec §6, §6.1). */
final class PaytypesTest extends TestCase
{
    use Recorded;

    private const array ALL_OFF = [
        'pay_card' => false,
        'pay_apay' => false,
        'pay_gpay' => false,
        'pay_privat24' => false,
        'pay_qr' => false,
        'pay_moment_part' => false,
        'pay_paypart' => false,
    ];

    /** @return array<string, array{0: array<string, bool>, 1: string}> */
    public static function switches(): array
    {
        return [
            'a method never saved has the defaults' => [[], 'card,apay,gpay'],
            'one switch' => [[...self::ALL_OFF, 'pay_moment_part' => true], 'moment_part'],
            'the order of the table, not of the settings' => [
                ['pay_paypart' => true, 'pay_qr' => true, 'pay_privat24' => true, 'pay_card' => true, 'pay_apay' => false, 'pay_gpay' => false, 'pay_moment_part' => true],
                'card,privat24,qr,moment_part,paypart',
            ],
            'all of them' => [array_map(static fn (): bool => true, self::ALL_OFF), 'card,apay,gpay,privat24,qr,moment_part,paypart'],
        ];
    }

    /** @param  array<string, bool>  $settings */
    #[Test]
    #[DataProvider('switches')]
    public function the_switches_make_the_list_in_the_order_of_the_table(array $settings, string $expected): void
    {
        $this->assertSame($expected, $this->sent(self::liqpayMethod($settings))['paytypes']);
    }

    #[Test]
    public function with_nothing_on_the_field_is_not_sent_and_the_page_shows_the_cabinets_choice(): void
    {
        $this->assertArrayNotHasKey('paytypes', $this->sent(self::liqpayMethod(self::ALL_OFF)));
    }

    #[Test]
    public function two_methods_of_the_type_keep_their_switches_and_their_keys(): void
    {
        $cards = self::liqpayMethod(['test_public_key' => 'sandbox_first']);
        $instalments = new Method(
            key: 'liqpay-checkout-2',
            type: LiqPayCheckoutType::KEY,
            titles: ['en' => 'Instant instalments'],
            descriptions: [],
            icon: null,
            enabled: true,
            position: 1,
            deliveryRule: ['mode' => 'any'],
            minAmount: null,
            maxAmount: null,
            settings: ['mode' => 'test', 'test_public_key' => 'sandbox_second', ...self::ALL_OFF, 'pay_moment_part' => true],
            hasCredentials: true,
        );

        $first = $this->handoff($cards, Credentials::of(['test_private_key' => 'sandbox_private_one']));
        $second = $this->handoff($instalments, Credentials::of(['test_private_key' => 'sandbox_private_two']));

        $this->assertSame(['sandbox_first', 'card,apay,gpay'], [self::data($first)['public_key'], self::data($first)['paytypes']]);
        $this->assertSame(['sandbox_second', 'moment_part'], [self::data($second)['public_key'], self::data($second)['paytypes']]);
        $this->assertSame(self::signed($first->fields['data'], key: 'sandbox_private_one'), $first->fields['signature']);
        $this->assertSame(self::signed($second->fields['data'], key: 'sandbox_private_two'), $second->fields['signature']);
    }

    #[Test]
    public function instalments_say_they_need_the_service_in_the_cabinet(): void
    {
        $fields = array_column($this->app->make(LiqPayCheckoutType::class)->settings(), null, 'name');

        $this->assertSame('Enable the service in the LiqPay cabinet first', __($fields['pay_paypart']['help']));
        $this->assertSame($fields['pay_paypart']['help'], $fields['pay_moment_part']['help']);
    }

    #[Test]
    public function a_hold_takes_the_instalments_away_in_the_form(): void
    {
        $fields = array_column($this->app->make(LiqPayCheckoutType::class)->settings(), null, 'name');

        foreach (['pay_moment_part', 'pay_paypart'] as $name) {
            $this->assertSame('hold', $fields[$name]['off_with']);
            $this->assertSame('Hold works for card payments only', __($fields[$name]['off_help']));
        }

        $this->assertArrayNotHasKey('off_with', $fields['pay_card']);
    }

    /** @return array<string, array{0: array<string, bool>, 1: list<string>}> */
    public static function refusals(): array
    {
        return [
            'instant instalments' => [['pay_moment_part' => true], ['pay_moment_part']],
            'pay in parts' => [['pay_paypart' => true], ['pay_paypart']],
            'both' => [['pay_moment_part' => true, 'pay_paypart' => true], ['pay_moment_part', 'pay_paypart']],
        ];
    }

    /**
     * @param  array<string, bool>  $on
     * @param  list<string>  $fields
     */
    #[Test]
    #[DataProvider('refusals')]
    public function a_hold_with_instalments_is_refused_by_field(array $on, array $fields): void
    {
        $refused = $this->validate(['hold' => true, 'pay_card' => true, ...$on]);

        $this->assertSame($fields, array_keys($refused));
        $this->assertSame(LiqPayGateway::line('paytypes.hold_cards_only'), $refused[$fields[0]]);
    }

    #[Test]
    public function a_hold_with_no_switch_on_is_refused_too(): void
    {
        $this->assertSame(['pay_card' => LiqPayGateway::line('paytypes.hold_needs_choice')], $this->validate(['hold' => true, ...self::ALL_OFF]));
    }

    #[Test]
    public function a_hold_on_cards_is_fine_and_so_are_instalments_without_a_hold(): void
    {
        $this->assertSame([], $this->validate(['hold' => true, ...self::ALL_OFF, 'pay_card' => true]));
        $this->assertSame([], $this->validate(['hold' => false, 'pay_moment_part' => true, 'pay_paypart' => true]));
        $this->assertSame([], $this->validate(self::ALL_OFF));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    private function validate(array $settings): array
    {
        return $this->app->make(LiqPayCheckoutType::class)->validateSettings($settings);
    }

    /** @return array<string, mixed> */
    private function sent(Method $method): array
    {
        return self::data($this->handoff($method, self::testKey()));
    }

    /** @return array<string, mixed> */
    private static function data(Handoff $handoff): array
    {
        return json_decode((string) base64_decode($handoff->fields['data'], true), true, flags: JSON_THROW_ON_ERROR);
    }

    private function handoff(Method $method, Credentials $credentials): Handoff
    {
        $type = $this->app->make(LiqPayCheckoutType::class);
        $http = $this->app->make(GatewayCalls::class)->http($type, $method, $credentials, ExchangeKind::Start, 'test');

        return $type->start($method, $credentials, new StartRequest(
            uuid: 'attempt-1',
            orderNumber: '1024',
            amount: 1234,
            currency: 'UAH',
            description: 'Order 1024',
            returnUrl: 'https://shop.example.com/pay/return/attempt-1',
            notifyUrl: 'https://shop.example.com/payments/notify/'.$method->key,
            locale: 'en',
        ), $http);
    }
}
