<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Payment\LiqPay\LiqPayCheckoutType;
use WebxUi\Payment\LiqPay\LiqPayGateway;
use WebxUi\Payments\Contracts\DeclaresNotCardFields;
use WebxUi\Payments\DeliveryRule;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\Kind;
use WebxUi\Payments\Method;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\PaymentContext;

final class RegistrationTest extends TestCase
{
    #[Test]
    public function the_gateway_and_its_type_join_the_registries_of_the_base(): void
    {
        $gateway = $this->app->make(Gateways::class)->get('liqpay');
        $type = $this->app->make(MethodTypes::class)->get('liqpay.checkout');

        $this->assertInstanceOf(LiqPayGateway::class, $gateway);
        $this->assertInstanceOf(LiqPayCheckoutType::class, $type);
        $this->assertSame($gateway->key(), $type->provider());
        $this->assertSame(Kind::Online, $type->kind());
        $this->assertTrue($type->awaitsPayment());
        $this->assertSame(['UAH', 'USD', 'EUR'], $type->currencies());
    }

    #[Test]
    public function the_private_keys_are_credentials_and_the_public_ones_are_settings(): void
    {
        $gateway = new LiqPayGateway;

        $this->assertSame(['test_private_key', 'live_private_key'], array_column($gateway->credentials(), 'name'));
        $this->assertSame([true, true], array_column($gateway->credentials(), 'secret'));
        $this->assertSame(['mode', 'test_public_key', 'live_public_key', 'language', 'expires_minutes'], array_column($gateway->settings(), 'name'));
    }

    #[Test]
    public function the_journal_is_told_what_is_no_card_and_nothing_is_a_secret_of_its_own(): void
    {
        $gateway = new LiqPayGateway;

        $this->assertInstanceOf(DeclaresNotCardFields::class, $gateway);
        $this->assertContains('create_date', $gateway->notCardFields());
        $this->assertContains('payment_id', $gateway->notCardFields());
        $this->assertSame([], $gateway->secretHeaders());
        $this->assertSame([], $gateway->secretFields());
    }

    #[Test]
    public function its_mark_is_a_data_url(): void
    {
        $this->assertStringStartsWith('data:image/svg+xml;base64,', (string) (new LiqPayGateway)->icon());
    }

    #[Test]
    public function a_method_without_a_title_reads_the_word_of_the_type(): void
    {
        $type = new LiqPayCheckoutType;
        $context = new PaymentContext(1000, 'UAH', 'uk', null, Purpose::Order);

        $this->assertSame('LiqPay', $type->present($this->method([]), $context)->title);
        $this->assertSame(['uk' => 'LiqPay'], $type->snapshot($this->method([]), $context)->title);
    }

    #[Test]
    public function a_method_with_a_title_reads_its_own(): void
    {
        $type = new LiqPayCheckoutType;
        $context = new PaymentContext(1000, 'UAH', 'uk', null, Purpose::Order);
        $titles = ['uk' => 'Оплата картою'];

        $this->assertSame('Оплата картою', $type->present($this->method($titles), $context)->title);
        $this->assertSame($titles, $type->snapshot($this->method($titles), $context)->title);
    }

    /** @param  array<string, string>  $titles */
    private function method(array $titles): Method
    {
        return new Method('liqpay-checkout', 'liqpay.checkout', $titles, [], null, true, 1, ['mode' => DeliveryRule::ANY], null, null, [], false);
    }
}
