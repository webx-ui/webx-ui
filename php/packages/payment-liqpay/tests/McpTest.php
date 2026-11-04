<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payment\LiqPay\PaymentLiqPayServiceProvider;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Tests\Mcp\McpCase;

/**
 * The satellite brings no tools (spec §14): an agent reaches the method by the base's. What is
 * LiqPay's own here is the names of its keys, which the base has to know to refuse them.
 */
final class McpTest extends McpCase
{
    private const string PRIVATE_KEY = 'sandbox_FAKEprivate0000000000000000000000';

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), PaymentLiqPayServiceProvider::class];
    }

    #[Test]
    public function a_private_key_is_refused_by_either_door_and_nothing_is_stored(): void
    {
        foreach (['test_private_key', 'live_private_key'] as $name) {
            $this->refused('payments_methods_save', ['key' => 'liqpay-checkout', 'values' => ['credentials' => [$name => self::PRIVATE_KEY]]], 'Gateway keys are set in the panel only.');
            $this->refused('payments_methods_save', ['key' => 'liqpay-checkout', 'values' => ['settings' => [$name => self::PRIVATE_KEY]]], 'Gateway keys are set in the panel only.');
        }

        $this->assertNull(PaymentMethod::query()->where('key', 'liqpay-checkout')->value('credentials'));
    }

    #[Test]
    public function an_answer_says_a_key_is_set_and_never_what_it_is(): void
    {
        PaymentMethod::query()->where('key', 'liqpay-checkout')->firstOrFail()
            ->forceFill(['credentials' => ['test_private_key' => self::PRIVATE_KEY]])->save();

        $methods = array_column($this->use('payments_methods_list')['methods'], null, 'key');

        $this->assertContains('test_private_key: set', $methods['liqpay-checkout']['credentials']);
        $this->assertStringNotContainsString(self::PRIVATE_KEY, $this->answered);
    }

    #[Test]
    public function a_hold_with_instalments_is_refused_here_as_in_the_panel(): void
    {
        $this->agent('payments_methods_save', ['key' => 'liqpay-checkout', 'values' => ['settings' => ['hold' => true, 'pay_moment_part' => true]]])
            ->assertHasErrors(['Hold works for card payments only']);
    }
}
