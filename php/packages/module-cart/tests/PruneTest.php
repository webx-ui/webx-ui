<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Support\Carbon;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;

final class PruneTest extends TestCase
{
    private function cartTouched(int $daysAgo): Cart
    {
        $cart = $this->app->make(CartService::class)->add(null, [['type' => 'test.product', 'ref' => 1]], 'en')->cart;
        $cart->timestamps = false;
        $cart->forceFill(['updated_at' => Carbon::now()->subDays($daysAgo)])->save();
        $cart->timestamps = true;

        return $cart;
    }

    #[Test]
    public function carts_untouched_for_the_days_go_with_their_lines(): void
    {
        $old = $this->cartTouched(61);
        $fresh = $this->cartTouched(5);

        $this->artisan('webx:cart:prune', ['--dry-run' => true])
            ->expectsOutputToContain('1 cart untouched for 60 days would be deleted.')
            ->assertSuccessful();
        $this->assertSame(2, Cart::query()->count());

        $this->artisan('webx:cart:prune')->assertSuccessful();

        $this->assertSame([$fresh->id], Cart::query()->pluck('id')->all());
        $this->assertSame(0, CartItem::query()->where('cart_id', $old->id)->count());

        $this->artisan('webx:cart:prune', ['--days' => 3])->assertSuccessful();
        $this->assertSame(0, Cart::query()->count());
    }

    #[Test]
    public function the_prune_is_on_the_schedule_daily(): void
    {
        $events = array_filter($this->app->make(Schedule::class)->events(), static fn (Event $event): bool => str_contains((string) $event->command, 'webx:cart:prune'));

        $this->assertCount(1, $events);
        $this->assertSame('0 0 * * *', array_values($events)[0]->expression);
    }

    #[Test]
    #[DefineEnvironment('withoutPrune')]
    public function the_prune_is_off_the_schedule_when_switched_off(): void
    {
        $events = array_filter($this->app->make(Schedule::class)->events(), static fn (Event $event): bool => str_contains((string) $event->command, 'webx:cart:prune'));

        $this->assertSame([], $events);
    }

    /** @param  Application  $app */
    protected function withoutPrune($app): void
    {
        $app['config']->set('webx-cart.prune_after_days', null);
    }

    #[Test]
    public function a_read_marks_activity_at_most_once_a_day(): void
    {
        $cart = $this->cartTouched(0);
        $stamp = $cart->updated_at?->getTimestamp();

        $this->travel(2)->hours();
        $cart->touchIfStale();
        $this->assertSame($stamp, $cart->fresh()?->updated_at?->getTimestamp());

        $this->travel(2)->days();
        $cart->touchIfStale();
        $this->assertGreaterThan($stamp, $cart->fresh()?->updated_at?->getTimestamp());
    }
}
