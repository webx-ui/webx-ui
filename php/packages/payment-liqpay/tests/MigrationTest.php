<?php

declare(strict_types=1);

namespace WebxUi\Payment\LiqPay\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Payments\Models\PaymentMethod;

final class MigrationTest extends TestCase
{
    #[Test]
    public function the_method_comes_switched_off_after_the_ones_of_the_base(): void
    {
        $method = PaymentMethod::query()->where('key', 'liqpay-checkout')->firstOrFail();

        $this->assertSame('liqpay.checkout', $method->type);
        $this->assertFalse($method->enabled);
        $this->assertSame(['mode' => 'any'], $method->delivery_rule);
        $this->assertSame([], $method->settings);
        $this->assertSame((int) PaymentMethod::query()->max('position'), $method->position);
    }

    #[Test]
    public function a_second_run_leaves_the_method_as_the_shop_made_it(): void
    {
        PaymentMethod::query()->where('key', 'liqpay-checkout')->update(['enabled' => true]);

        $this->up();

        $this->assertSame(1, PaymentMethod::query()->where('key', 'liqpay-checkout')->count());
        $this->assertTrue(PaymentMethod::query()->where('key', 'liqpay-checkout')->firstOrFail()->enabled);
    }

    #[Test]
    public function it_rolls_back_and_comes_again(): void
    {
        $this->down();
        $this->assertFalse(PaymentMethod::query()->where('key', 'liqpay-checkout')->exists());

        $this->up();
        $this->assertTrue(PaymentMethod::query()->where('key', 'liqpay-checkout')->exists());
    }

    #[Test]
    public function a_method_with_an_attempt_survives_the_rollback(): void
    {
        Schema::withoutForeignKeyConstraints(static function (): void {
            DB::table('payments')->insert([
                'uuid' => '01JZZZZZZZZZZZZZZZZZZZZZZZ',
                'order_id' => 1,
                'method_key' => 'liqpay-checkout',
                'type' => 'liqpay.checkout',
                'amount' => 1000,
                'currency' => 'UAH',
                'status' => 'pending',
                'source' => 'test',
            ]);
        });

        $this->down();

        $this->assertTrue(PaymentMethod::query()->where('key', 'liqpay-checkout')->exists());
    }

    private function up(): void
    {
        (require __DIR__.'/../database/migrations/2026_11_04_000000_add_liqpay_checkout_method.php')->up();
    }

    private function down(): void
    {
        (require __DIR__.'/../database/migrations/2026_11_04_000000_add_liqpay_checkout_method.php')->down();
    }
}
