<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Models\Cart;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * The query budget of the cart's API (§13, WEBX_UI_PERFORMANCE.md): the cart by its token, its
 * lines, and each provider once a batch — so ten lines and forty cost the same.
 *
 * No cache group: a cart is one visitor's and changes with every click; caching it would only
 * add a write to every write. Its providers cache what is theirs (the catalogue's products). The
 * agent's tools are measured warm, the way the panel's lists are: the permissions of the caller
 * are read once a request, the carts are what is counted.
 */
final class PerformanceTest extends McpTestCase
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

    #[Test]
    public function the_agents_lists_cost_the_same_with_five_carts_and_with_twenty(): void
    {
        $admin = CmsUser::query()->create(['name' => 'Admin', 'email' => 'budget@example.test', 'password' => 'correct-horse-battery', 'is_super' => true, 'is_active' => true]);

        // [tool, arguments, budget] — every budget has the journal's line of the call (mcp_calls) in it.
        foreach ([5, 20] as $carts) {
            while (Cart::query()->count() < $carts) {
                $this->cartOf(3);
            }

            $id = (int) Cart::query()->value('id');

            foreach ([
                // The page and its count, then the lines, units and sums of the page at once.
                ['cart_list', [], 4],
                ['cart_list', ['contains' => ['type' => 'test.product', 'ref' => 1], 'min_units' => 2], 4],
                // The counts, the abandoned, the records in most carts; their names from the providers.
                ['cart_summary', [], 4],
                // As GET /api/cart: the cart, then its lines.
                ['cart_get', ['cart' => $id], 3],
            ] as [$tool, $arguments, $budget]) {
                $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));
                $call = static fn () => WebxServer::actingAs($admin, 'cms')->tool($bound, $arguments)->assertOk();
                $call();

                $this->assertQueriesWithin($budget, $call, "{$tool}, {$carts} carts");
                $this->assertNoRepeatedQueries($call, "{$tool}, {$carts} carts");
            }
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
