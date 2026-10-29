<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Checkout;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Cart\AddResult;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\GeneratedLine;
use WebxUi\Cart\Totals\LineSources;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Tests\Fakes\FakeSources;

/**
 * The query budget of the page (spec §34, WEBX_UI_PERFORMANCE.md): `GET /checkout` with delivery
 * and payment in their slots counts the cart once a request — the steps and the column take that
 * count — so a cart of three lines and one of ten, with a bundle and a gift, cost the same.
 */
final class PerformanceTest extends CheckoutTestCase
{
    use CountsQueries;
    use UsesFakeSlots;

    #[Test]
    public function the_page_costs_the_same_with_three_lines_and_with_ten(): void
    {
        $this->installSlots();

        for ($ref = 10; $ref < 20; $ref++) {
            $this->products->lines[$ref] = self::product("Item {$ref}", 1000 + $ref);
        }

        $counts = [];

        foreach ([3, 10] as $lines) {
            // One after the other: the gift's source, once registered, answers for every cart.
            $cart = $this->lines($lines);
            $this->fill($cart, "buyer-{$lines}@example.test");
            // The draft's amount shown is written by the first look; a site's next look writes nothing.
            $this->visit($cart, '/checkout')->assertOk();
            $this->assertCount($lines, $this->app->make(Calculator::class)->calculate($cart->cart, 'en', Purpose::Checkout)->lines);

            $page = fn () => $this->visit($cart, '/checkout')->assertOk()->assertSee('Item 10');
            $tally = $this->recordQueries($page);

            // 3: the cart by its cookie, its lines and the draft — the one count of the request. The
            // steps, the slots and the column work from that count; nothing a line, nothing a step.
            $this->assertLessThanOrEqual(3, $tally->count(), "{$lines} lines".PHP_EOL.$tally->describe());
            $this->assertNoRepeatedQueries($page, "{$lines} lines");
            $counts[$lines] = $tally->count();
        }

        $this->assertSame($counts[3], $counts[10]);
    }

    /**
     * A bundle with its children and, for more than three lines, plain lines with a gift under the
     * first: `$lines` lines of the page in all.
     */
    private function lines(int $lines): AddResult
    {
        $items = [
            ['type' => 'test.product', 'ref' => 10, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 11, 'parent' => 'set'],
            ['type' => 'test.product', 'ref' => 12, 'parent' => 'set'],
        ];

        for ($ref = 13; count($items) < $lines - 1; $ref++) {
            $items[] = ['type' => 'test.product', 'ref' => $ref];
        }

        $cart = $this->cart($items);

        if ($lines > 3) {
            $plain = CartItem::query()->where('cart_id', $cart->cart->id)->where('ref_id', 13)->sole()->id;
            $this->app->make(LineSources::class)->register(new FakeSources(static fn (): array => [
                new GeneratedLine('rule#7', 'test.product', 4, parentId: $plain, price: 1, label: 'Gift'),
            ]));
        }

        return $cart;
    }
}
