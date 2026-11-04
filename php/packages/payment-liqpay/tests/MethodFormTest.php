<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use Illuminate\Support\Env;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payment\LiqPay\LiqPayCheckoutType;
use WebxUi\Payments\Method;
use WebxUi\Payments\MethodCredentials;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Panel\MethodForm;

/** What the panel of the base makes of the type: the satellite draws nothing itself (spec §14). */
final class MethodFormTest extends TestCase
{
    private const string VARIABLE = 'WEBX_PAYMENTS_LIQPAY_CHECKOUT_TEST_PRIVATE_KEY';

    protected function tearDown(): void
    {
        putenv(self::VARIABLE);
        unset($_ENV[self::VARIABLE], $_SERVER[self::VARIABLE]);

        parent::tearDown();
    }

    #[Test]
    public function the_list_shows_the_method_switched_off_in_test_mode_without_keys(): void
    {
        $row = $this->app->make(MethodForm::class)->row($this->method());

        $this->assertFalse($row['enabled']);
        $this->assertSame('LiqPay', $row['title']);
        $this->assertSame('liqpay', $row['gateway']);
        $this->assertSame('test', $row['mode']);
        $this->assertSame('missing', $row['credentials']);
        $this->assertSame(['no_credentials'], $row['warnings']);
    }

    #[Test]
    public function the_form_draws_the_switches_and_the_settings_in_words(): void
    {
        $form = $this->app->make(MethodForm::class)->describe($this->method());
        $fields = array_column($form['settings']['fields'], null, 'name');

        $this->assertSame(
            [...array_keys(LiqPayCheckoutType::PAYTYPES), 'mode', 'test_public_key', 'live_public_key', 'language', 'expires_minutes'],
            array_keys($fields),
        );
        $this->assertSame('switch', $fields['pay_card']['type']);
        $this->assertTrue($fields['pay_card']['default']);
        $this->assertFalse($fields['pay_qr']['default']);
        $this->assertSame('Instant instalments', $fields['pay_moment_part']['label']);
        $this->assertSame('Enable the service in the LiqPay cabinet first', $fields['pay_paypart']['help']);
        $this->assertSame('Enable the service in the LiqPay cabinet first', $fields['pay_moment_part']['help']);
        $this->assertSame('With every switch off the page shows all that is switched on in the LiqPay cabinet.', $fields['pay_card']['help']);
        $this->assertNotNull($fields['mode']['help']);
        $this->assertSame($fields['test_public_key']['help'], $fields['live_public_key']['help']);
        $this->assertNotNull($fields['expires_minutes']['help']);
        $this->assertSame(['test', 'live'], array_column($fields['mode']['options'], 'value'));
        $this->assertSame(60, $fields['expires_minutes']['default']);
        $this->assertSame(['test_private_key', 'live_private_key'], array_column($form['credentials'], 'name'));
    }

    #[Test]
    public function instalments_go_away_with_the_hold_and_say_why(): void
    {
        $fields = array_column($this->app->make(MethodForm::class)->describe($this->method())['settings']['fields'], null, 'name');

        foreach (['pay_moment_part', 'pay_paypart'] as $name) {
            $this->assertSame('hold', $fields[$name]['off_with']);
            $this->assertSame('Hold works for card payments only', $fields[$name]['off_help']);
        }

        $this->assertNull($fields['pay_card']['off_with']);
    }

    #[Test]
    public function the_private_key_comes_from_the_environment_under_the_name_of_the_spec(): void
    {
        $this->assertSame(self::VARIABLE, MethodCredentials::variable('liqpay-checkout', 'test_private_key'));

        // Only a fake value here: the keys of the sandbox are for the lab, never for a test.
        putenv(self::VARIABLE.'=sandbox_FAKEprivate0000000000000000000000');
        Env::enablePutenv();

        $method = $this->method();
        $keys = $this->app->make(MethodCredentials::class)->for(Method::of($method));

        $this->assertSame('sandbox_FAKEprivate0000000000000000000000', $keys->get('test_private_key'));
        $this->assertSame('environment', $this->app->make(MethodForm::class)->row($method)['credentials']);
    }

    #[Test]
    public function a_hold_refuses_instalments_and_a_page_without_a_choice(): void
    {
        $type = new LiqPayCheckoutType;
        $cards = 'webx-payment-liqpay::liqpay.paytypes.hold_cards_only';

        $this->assertSame([], $type->validateSettings(['pay_moment_part' => true, 'pay_paypart' => true]));
        $this->assertSame([], $type->validateSettings(['hold' => true]));
        $this->assertSame(['pay_moment_part' => $cards], $type->validateSettings(['hold' => true, 'pay_moment_part' => true]));
        $this->assertSame(
            ['pay_moment_part' => $cards, 'pay_paypart' => $cards],
            $type->validateSettings(['hold' => true, 'pay_moment_part' => true, 'pay_paypart' => true]),
        );
        $this->assertSame(
            ['pay_card' => 'webx-payment-liqpay::liqpay.paytypes.hold_needs_choice'],
            $type->validateSettings(['hold' => true, 'pay_card' => false, 'pay_apay' => false, 'pay_gpay' => false]),
        );
    }

    private function method(): PaymentMethod
    {
        return PaymentMethod::query()->where('key', 'liqpay-checkout')->firstOrFail();
    }
}
