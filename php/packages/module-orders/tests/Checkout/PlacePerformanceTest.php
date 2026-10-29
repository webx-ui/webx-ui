<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Cart\AddResult;
use WebxUi\Cart\CartService;
use WebxUi\Orders\Checkout\CheckoutContexts;
use WebxUi\Orders\Models\Order;

/**
 * The query budget of the confirmation (spec §34, WEBX_UI_PERFORMANCE.md): the checks of the
 * steps and `Orders::place()` — which has its own budget — and nothing a line, so an order of four
 * lines and one of forty cost the same press of the button.
 */
final class PlacePerformanceTest extends CheckoutTestCase
{
    use CountsQueries;

    #[Test]
    public function the_confirmation_costs_the_same_with_four_lines_and_with_forty(): void
    {
        for ($ref = 10; $ref < 50; $ref++) {
            $this->products->lines[$ref] = self::product("Item {$ref}", 1000 + $ref);
        }

        $counts = [];

        foreach ([4, 40] as $lines) {
            $cart = $this->bundles($lines);
            $this->fill($cart, "buyer-{$lines}@example.test");
            $fields = $this->hidden($cart);
            $this->travel(5)->seconds();
            // The request counts the cart afresh, as a site does.
            $this->app->make(CheckoutContexts::class)->forget();

            $tally = $this->recordQueries(fn () => $this->visit($cart, '/checkout/place', $fields)->assertRedirect());

            $this->assertSame($lines, Order::query()->latest('id')->firstOrFail()->items()->count());
            // 30: the cart by its cookie, its lines and the draft — the one count of the request; the
            // order the key may have made already; the draft written (the agreement, the key); then
            // `Orders::place()` under its own budget (\WebxUi\Orders\Tests\PerformanceTest), the
            // checkout's draft cleared inside its transaction. Nothing a line.
            $this->assertLessThanOrEqual(30, $tally->count(), "{$lines} lines".PHP_EOL.$tally->describe());
            $counts[$lines] = $tally->count();
        }

        $this->assertSame($counts[4], $counts[40]);
    }

    /** A cart of bundles: a parent and a child each, `$lines` lines in all. */
    private function bundles(int $lines): AddResult
    {
        $items = [];

        for ($i = 0; $i < $lines; $i += 2) {
            $items[] = ['type' => 'test.product', 'ref' => 10 + $i, 'key' => "set{$i}"];
            $items[] = ['type' => 'test.product', 'ref' => 11 + $i, 'parent' => "set{$i}"];
        }

        $batches = array_chunk($items, 20);
        $cart = $this->cart($batches[0]);

        foreach (array_slice($batches, 1) as $batch) {
            $this->app->make(CartService::class)->add($cart->cart, $batch, 'en');
        }

        return $cart;
    }
}
