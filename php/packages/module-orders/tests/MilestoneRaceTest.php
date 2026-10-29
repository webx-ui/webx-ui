<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use WebxUi\Orders\Events\OrderPaid;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Orders;

/**
 * Two marks of «paid» at one moment, on a real server (§5.3): a gateway's callback and a manager's
 * click. sqlite has no `FOR UPDATE`; here a child process marks the order through its own
 * connection while the parent holds its row, and must come after it to the first mark — one
 * milestone, one event.
 *
 * Runs against MariaDB or MySQL named by `WEBX_TEST_MYSQL_URL`, like `CartLockTest`, and is
 * skipped without it.
 */
final class MilestoneRaceTest extends PlaceTestCase
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

        foreach (['orders_race', 'orders_race_child'] as $name) {
            $app['config']->set("database.connections.{$name}", [
                'driver' => 'mariadb',
                'url' => self::url(),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
            ]);
        }

        $app['config']->set('database.default', 'orders_race');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true])->run();
    }

    #[Test]
    public function a_mark_of_paid_that_comes_meanwhile_waits_and_finds_the_first(): void
    {
        $order = $this->place($this->cart(), ['awaitPayment' => true]);
        $flag = sys_get_temp_dir().'/webx-orders-race-'.getmypid();
        @unlink($flag);
        $events = 0;
        Event::listen(OrderPaid::class, static function () use (&$events): void {
            $events++;
        });

        $mark = DB::transaction(function () use ($order, $flag) {
            // The manager's request has the row; the gateway's comes now.
            Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $pid = pcntl_fork();

            if ($pid === 0) {
                config(['database.default' => 'orders_race_child']);

                try {
                    $answer = $this->app->make(Orders::class)->mark(Order::query()->findOrFail($order->id), Milestone::Paid, 'payment:gateway-a', reference: 'PAY-2');
                    file_put_contents($flag, $answer->outcome.' '.$answer->source);
                } catch (Throwable $e) {
                    file_put_contents($flag, 'failed: '.$e->getMessage());
                }

                // No shutdown: the parent's connection, copied into this process, stays untouched.
                posix_kill(posix_getpid(), SIGKILL);
            }

            usleep(1_500_000);
            clearstatcache();
            $this->assertFileDoesNotExist($flag, 'The gateway marked the order while the manager held it.');

            $mark = $this->app->make(Orders::class)->mark($order, Milestone::Paid, 'panel');

            return [$mark, $pid];
        });

        [$first, $pid] = $mark;
        pcntl_waitpid($pid, $status);

        $this->assertTrue($first->marked());
        $this->assertSame('already_marked panel', @file_get_contents($flag));
        @unlink($flag);
        $this->assertSame(1, $events);
        $this->assertSame(['panel'], OrderMilestone::query()->where('order_id', $order->id)->where('milestone', 'paid')->pluck('source')->all());
    }

    private static function url(): ?string
    {
        $url = getenv('WEBX_TEST_MYSQL_URL');

        return is_string($url) && $url !== '' ? $url : null;
    }
}
