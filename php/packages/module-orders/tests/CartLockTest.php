<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Orders\Events\OrderPlacing;
use WebxUi\Orders\Models\OrderItem;

/**
 * The lock of the cart's row, on a real server (§6.2): sqlite has no `FOR UPDATE`, and a second
 * connection is what the other tab of the buyer is. A child process adds a line while the order
 * is being made; it must wait for the order and land in the emptied cart, not vanish with the
 * lines the order took.
 *
 * Runs against MariaDB or MySQL named by `WEBX_TEST_MYSQL_URL`
 * (`mariadb://user:password@host:3306/database` — a database of its own, wiped by the test), and
 * is skipped without it.
 */
final class CartLockTest extends PlaceTestCase
{
    protected function setUp(): void
    {
        if (self::url() === null || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('WEBX_TEST_MYSQL_URL and pcntl are needed: the lock is a server\'s.');
        }

        parent::setUp();
    }

    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Two connections to one database: the order's, and the one the child process opens.
        foreach (['orders_lock', 'orders_lock_child'] as $name) {
            $app['config']->set("database.connections.{$name}", [
                'driver' => 'mariadb',
                'url' => self::url(),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ]);
        }

        $app['config']->set('database.default', 'orders_lock');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true])->run();
    }

    #[Test]
    public function a_line_added_while_the_order_is_made_waits_for_it_and_stays_in_the_cart(): void
    {
        $cart = $this->cart([['type' => 'test.product', 'ref' => 1]]);
        $flag = sys_get_temp_dir().'/webx-orders-lock-'.getmypid();
        @unlink($flag);
        $child = null;

        Event::listen(OrderPlacing::class, function () use ($cart, $flag, &$child): void {
            $pid = pcntl_fork();

            if ($pid === 0) {
                // The other tab: its own connection, the same line again — it would add to the
                // line the order is taking.
                config(['database.default' => 'orders_lock_child']);

                try {
                    $this->app->make(CartService::class)->add(Cart::query()->findOrFail($cart->cart->id), [['type' => 'test.product', 'ref' => 1, 'qty' => 2]], 'en');
                    file_put_contents($flag, 'added');
                } catch (Throwable $e) {
                    file_put_contents($flag, 'failed: '.$e->getMessage());
                }

                // No shutdown: the parent's connection, copied into this process, stays untouched.
                posix_kill(posix_getpid(), SIGKILL);
            }

            $child = $pid;

            // Long enough for the child to come to the lock — where it has to be still.
            usleep(1_500_000);
            clearstatcache();
            $this->assertFileDoesNotExist($flag, 'The other tab wrote while the order held the cart.');
        });

        $order = $this->place($cart);

        $this->assertIsInt($child);
        pcntl_waitpid($child, $status);
        $this->assertSame('added', @file_get_contents($flag));
        @unlink($flag);

        $this->assertSame([[1, 1]], OrderItem::query()->where('order_id', $order->id)->get()->map(fn (OrderItem $item): array => [$item->ref_id, $item->qty])->all());
        $this->assertSame([[1, 2]], CartItem::query()->where('cart_id', $cart->cart->id)->get()->map(fn (CartItem $item): array => [$item->ref_id, $item->qty])->all());
    }

    private static function url(): ?string
    {
        $url = getenv('WEBX_TEST_MYSQL_URL');

        return is_string($url) && $url !== '' ? $url : null;
    }
}
