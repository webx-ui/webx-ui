<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Cart\CartService;

/**
 * The query budget of the cart's API (§13, WEBX_UI_PERFORMANCE.md): the cart by its token, its
 * lines, and each provider once a batch — so ten lines and forty cost the same.
 *
 * No cache group: a cart is one visitor's and changes with every click; caching it would only
 * add a write to every write. Its providers cache what is theirs (the catalogue's products).
 */
final class PerformanceTest extends TestCase
{
    use CountsQueries;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCredentials();

        for ($ref = 1; $ref <= 40; $ref++) {
            $this->products->lines[$ref] = self::product("Item {$ref}", 1000 + $ref);
        }
    }

    #[Test]
    public function reading_the_cart_costs_the_same_with_ten_lines_and_with_forty(): void
    {
        foreach ([10, 40] as $count) {
            $token = $this->cartOf($count);
            $read = fn () => $this->withCookie('webx_cart', $token)->getJson('/api/cart')->assertOk()->assertJsonPath('units', $count);

            // The cart by the hash of its token, then its lines.
            $this->assertQueriesWithin(2, $read, "{$count} lines");
            $this->assertNoRepeatedQueries($read, "{$count} lines");
        }
    }

    private function cartOf(int $count): string
    {
        $service = $this->app->make(CartService::class);
        $cart = null;
        $token = null;

        foreach (array_chunk(range(1, $count), 20) as $refs) {
            $result = $service->add($cart, array_map(static fn (int $ref): array => ['type' => 'test.product', 'ref' => $ref], $refs), 'en');
            $cart = $result->cart;
            $token ??= $result->token;
        }

        return (string) $token;
    }
}
