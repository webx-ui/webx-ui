<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Cart\Models\Cart;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderRefund;
use WebxUi\Orders\Models\OrderTender;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\OrdersServiceProvider;
use WebxUi\Users\Models\User;
use WebxUi\Users\UsersServiceProvider;

/**
 * The demo orders (spec §15): twelve, made through the service, in every public status and every
 * corner of §15 — and `--remove` takes them back whole. With visitors installed, three are the
 * visitor's, one of them a guest's linked by hand.
 */
final class DemoTest extends TestCase
{
    /** @param  Application  $app */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('webx-cart.currency', 'EUR');
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        $providers = parent::getPackageProviders($app);
        array_splice($providers, (int) array_search(OrdersServiceProvider::class, $providers, true), 0, [UsersServiceProvider::class]);

        return $providers;
    }

    protected function tearDown(): void
    {
        @unlink($this->app->make(DemoLedger::class)->path());

        parent::tearDown();
    }

    #[Test]
    public function it_seeds_twelve_orders_across_every_public_status(): void
    {
        $visitor = User::query()->create(['email' => 'anna.demo@example.com', 'first_name' => 'Anna', 'last_name' => 'Lane']);

        $this->artisan('webx:demo', ['--module' => 'orders'])->assertSuccessful();

        $orders = Order::query()->with('status.publicStatus')->orderBy('placed_at')->get();
        $this->assertCount(12, $orders);
        $this->assertEqualsCanonicalizing(
            ['accepted', 'in_progress', 'shipped', 'completed', 'cancelled'],
            $orders->map(static fn (Order $order): string => $order->status->publicStatus->code)->unique()->values()->all(),
        );

        // Days apart and never ahead of now; the clock is back where it was.
        $this->assertTrue($orders->first()?->placed_at->between(Carbon::now()->subDays(25), Carbon::now()->subDays(23)));
        $this->assertTrue($orders->last()?->placed_at->gt(Carbon::now()->subDay()));
        $this->assertSame(0, $orders->filter(static fn (Order $order): bool => $order->placed_at->isFuture())->count());
        $this->assertFalse(Carbon::hasTestNow());

        $this->assertSame(2, $orders->where('awaiting_payment', true)->count());
        $this->assertSame(1, $orders->whereNotNull('payment_failed_at')->count());
        $this->assertSame(1, $orders->filter(static fn (Order $order): bool => $order->paid_at !== null && $order->status->publicStatus->code === 'accepted')->count());

        $cancelled = $orders->whereNotNull('cancelled_at');
        $this->assertCount(2, $cancelled);
        $this->assertSame(1, $cancelled->whereNotNull('refunded_at')->count());
        $this->assertSame('BANK-0423', OrderRefund::query()->where('reference', 'BANK-0423')->value('reference'));

        // Received, with one unit of one line given back.
        $partial = $orders->whereNotNull('received_at')->first(static fn (Order $order): bool => $order->refunded_total > 0);
        $this->assertNotNull($partial);
        $this->assertNull($partial->refunded_at);
        $this->assertSame(1, (int) $partial->items()->sum('refunded_qty'));

        $this->assertSame(1, $orders->filter(static fn (Order $order): bool => $order->items()->whereNotNull('source')->exists() && $order->benefits()->exists())->count());
        // The gift costs one minor unit and the rule takes it back: whole money, as a shop has it.
        $this->assertSame(0, $orders->filter(static fn (Order $order): bool => $order->total % 100 !== 0)->count());
        $this->assertSame(1, OrderTender::query()->count());
        $this->assertSame(1, $orders->filter(static fn (Order $order): bool => $order->items()->whereNotNull('parent_id')->count() === 2)->count());

        $this->assertSame(3, $orders->where('user_id', $visitor->id)->count());
        $this->assertSame(1, StatusChange::query()->where('kind', StatusChange::VISITOR)->count());
        $this->assertSame(0, $orders->filter(static fn (Order $order): bool => $order->user_id === null && ! str_ends_with((string) $order->customer_email, '@example.com'))->count());

        // The carts did their part and are gone.
        $this->assertSame(0, Cart::query()->count());
    }

    #[Test]
    public function a_site_with_orders_of_its_own_gets_none(): void
    {
        $this->artisan('webx:demo', ['--module' => 'orders'])->assertSuccessful();
        @unlink($this->app->make(DemoLedger::class)->path());

        $this->artisan('webx:demo', ['--module' => 'orders'])->assertSuccessful();

        $this->assertSame(12, Order::query()->count());
    }

    #[Test]
    public function the_removal_leaves_nothing_behind(): void
    {
        $this->artisan('webx:demo', ['--module' => 'orders'])->assertSuccessful();
        $this->artisan('webx:demo', ['--remove' => true])->assertSuccessful();

        $this->assertSame(0, Order::query()->count());

        foreach (['order_items', 'order_adjustments', 'order_tenders', 'order_benefits', 'order_milestones', 'order_refunds', 'order_status_changes'] as $table) {
            $this->assertSame(0, $this->app['db']->table($table)->count(), $table);
        }
    }
}
