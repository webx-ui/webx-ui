<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Checkout\Slots\ShippingChoice;
use WebxUi\Payments\Availability;
use WebxUi\Payments\DeliveryRule;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\Method;
use WebxUi\Payments\MethodCredentials;
use WebxUi\Payments\Methods;
use WebxUi\Payments\MethodTypes;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\Testing\FakeGateway;
use WebxUi\Payments\Testing\FakeGatewayServer;
use WebxUi\Payments\Tests\Fakes\FakeOnlineType;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * Which methods a buyer is offered (spec §7): the rule of the delivery by its table, the amount,
 * the currency, a type gone, an online method without its keys — and the reasons the manager reads.
 */
final class MethodsTest extends TestCase
{
    /** @return iterable<string, array{array<string, mixed>, ShippingChoice|null, bool}> */
    public static function rules(): iterable
    {
        $pickup = new ShippingChoice('store-pickup', 'store', false, true);
        $cod = new ShippingChoice('carrier-a-branch', 'carrier-a', true, false);
        $plain = new ShippingChoice('carrier-a-courier', 'carrier-a', false, false);
        $methods = ['mode' => 'methods', 'methods' => ['store-pickup']];
        $except = ['mode' => 'except', 'methods' => ['store-pickup']];

        yield 'any, pick-up' => [['mode' => 'any'], $pickup, true];
        yield 'any, no delivery' => [['mode' => 'any'], null, true];
        yield 'on hand, pick-up' => [['mode' => 'on_hand'], $pickup, true];
        yield 'on hand, cash on delivery' => [['mode' => 'on_hand'], $cod, true];
        yield 'on hand, a carrier without it' => [['mode' => 'on_hand'], $plain, false];
        yield 'on hand, no delivery: as agreed' => [['mode' => 'on_hand'], null, true];
        yield 'methods, in the list' => [$methods, $pickup, true];
        yield 'methods, not in it' => [$methods, $plain, false];
        yield 'methods, no delivery' => [$methods, null, false];
        yield 'except, in the list' => [$except, $pickup, false];
        yield 'except, not in it' => [$except, $plain, true];
        yield 'except, no delivery' => [$except, null, true];
        yield 'a broken rule offers nothing' => [['mode' => 'whatever'], $pickup, false];
    }

    /** @param  array<string, mixed>  $rule */
    #[Test]
    #[DataProvider('rules')]
    public function the_rule_of_the_delivery_follows_its_table(array $rule, ?ShippingChoice $shipping, bool $offered): void
    {
        $this->assertSame($offered, DeliveryRule::fromArray($rule)->allows($shipping));

        PaymentMethod::query()->where('key', BankTransfer::METHOD)->update(['enabled' => true, 'delivery_rule' => json_encode($rule)]);

        $this->assertSame($offered ? [BankTransfer::METHOD] : [], $this->offered(self::context(shipping: $shipping)));
    }

    #[Test]
    public function the_built_in_methods_are_there_switched_off(): void
    {
        $this->assertSame([], $this->offered(self::context()));
        $this->assertSame(['on-receipt' => 'store.on_receipt', 'bank-transfer' => 'store.bank_transfer'], PaymentMethod::query()->orderBy('position')->pluck('type', 'key')->all());
        $this->assertSame(['mode' => 'on_hand'], PaymentMethod::query()->where('key', 'on-receipt')->value('delivery_rule'));
    }

    #[Test]
    public function the_amount_the_currency_and_nothing_to_pay(): void
    {
        PaymentMethod::query()->where('key', BankTransfer::METHOD)->update(['enabled' => true, 'min_amount' => 1000, 'max_amount' => 5000]);
        $methods = $this->app->make(Methods::class);
        $transfer = $methods->find(BankTransfer::METHOD);
        $this->assertInstanceOf(Method::class, $transfer);

        $this->assertSame('amount_below_min', $methods->check($transfer, self::context(999))->reason);
        $this->assertTrue($methods->check($transfer, self::context(1000))->available);
        $this->assertTrue($methods->check($transfer, self::context(5000))->available);
        $this->assertSame('amount_above_max', $methods->check($transfer, self::context(5001))->reason);
        $this->assertSame('nothing_to_pay', $methods->check($transfer, self::context(0))->reason);
        $this->assertSame('Nothing to pay', $methods->check($transfer, self::context(0))->message('en'));
    }

    #[Test]
    public function a_type_gone_and_an_online_method_without_keys_are_not_offered(): void
    {
        $this->app->make(MethodTypes::class)->register(new FakeOnlineType);
        PaymentMethod::query()->create(['key' => 'gateway-a-card', 'type' => 'gateway-a.card', 'title' => ['en' => 'Card'], 'enabled' => true, 'position' => 1]);
        PaymentMethod::query()->create(['key' => 'gone', 'type' => 'gateway-z.card', 'title' => ['en' => 'Gone'], 'enabled' => true, 'position' => 2]);

        $reasons = fn (): array => array_map(static fn (Availability $a): ?string => $a->reason, $this->app->make(Methods::class)->explain(self::context()));

        // An online type without its gateway: the satellite forgot to register it (§6.2).
        $this->assertSame(['gateway-a-card' => 'gateway_missing', 'gone' => 'type_missing'], $reasons());

        $this->app->make(Gateways::class)->register(new FakeGateway($this->app->make(FakeGatewayServer::class)));
        $this->assertSame(['gateway-a-card' => 'no_credentials', 'gone' => 'type_missing'], $reasons());
        $this->assertSame('currency', $this->app->make(Methods::class)->explain(self::context(currency: 'USD'))['gateway-a-card']->reason);

        PaymentMethod::query()->where('key', 'gateway-a-card')->firstOrFail()->update(['credentials' => ['secret_key' => self::SECRET]]);

        $this->assertSame(['gateway-a-card'], $this->offered(self::context()));
    }

    #[Test]
    public function the_keys_may_come_from_the_environment_and_are_stronger_there(): void
    {
        $this->app->make(MethodTypes::class)->register(new FakeOnlineType);
        $this->app->make(Gateways::class)->register(new FakeGateway($this->app->make(FakeGatewayServer::class)));
        PaymentMethod::query()->create(['key' => 'gateway-a-card', 'type' => 'gateway-a.card', 'title' => ['en' => 'Card'], 'enabled' => true, 'position' => 1]);
        $variable = MethodCredentials::variable('gateway-a-card', 'secret_key');
        $this->assertSame('WEBX_PAYMENTS_GATEWAY_A_CARD_SECRET_KEY', $variable);

        $this->assertSame([], $this->offered(self::context()));

        putenv($variable.'=from-the-environment-0001');

        try {
            $this->assertSame(['gateway-a-card'], $this->offered(self::context()));

            PaymentMethod::query()->where('key', 'gateway-a-card')->firstOrFail()->update(['credentials' => ['secret_key' => self::SECRET]]);
            $method = $this->app->make(Methods::class)->find('gateway-a-card');
            $this->assertNotNull($method);
            $this->assertSame('from-the-environment-0001', $this->app->make(MethodCredentials::class)->for($method)->get('secret_key'));
        } finally {
            putenv($variable);
        }

        $this->assertSame(self::SECRET, $this->app->make(MethodCredentials::class)->for($method)->get('secret_key'));
    }

    #[Test]
    public function the_cache_holds_no_key_and_follows_every_save(): void
    {
        $methods = $this->app->make(Methods::class);
        $this->assertNull($methods->find(OnReceipt::METHOD));

        PaymentMethod::query()->where('key', OnReceipt::METHOD)->firstOrFail()->update(['enabled' => true, 'credentials' => ['secret_key' => self::SECRET]]);

        $this->assertInstanceOf(Method::class, $methods->find(OnReceipt::METHOD));
        $this->assertStringNotContainsString(self::SECRET, serialize($methods->enabled()));
    }

    #[Test]
    public function the_registry_refuses_what_is_not_a_type_key_or_comes_twice(): void
    {
        $types = $this->app->make(MethodTypes::class);
        $this->assertSame(['store.on_receipt', 'store.bank_transfer'], $types->keys());

        $this->expectException(LogicException::class);
        $types->register(new OnReceipt);
    }

    private static function context(int $payable = 4999, ?ShippingChoice $shipping = null, string $currency = 'EUR'): PaymentContext
    {
        return new PaymentContext($payable, $currency, 'en', $shipping, Purpose::Checkout);
    }

    /** @return list<string> */
    private function offered(PaymentContext $context): array
    {
        return array_map(static fn ($option): string => $option->key(), $this->app->make(Methods::class)->available($context));
    }
}
