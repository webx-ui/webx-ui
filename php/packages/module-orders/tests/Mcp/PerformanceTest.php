<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Mcp;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Orders\Checkout\Models\CheckoutDraft;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Orders;

/**
 * The query budget of the agent's lists (spec §12, §16): `orders_list`, `orders_summary` and
 * `orders_statuses_list` ask the same with three orders and with twenty. No cache — the orders
 * are alive, as in the panel — so the count is held instead: the page with its first line as a
 * subquery and its statuses in two, the summary in `GROUP BY`s over indexes, the board in three.
 * Measured warm: the caller and their roles are read once a request.
 *
 * The checkout's (§32): the trial confirmation of a cart of three lines and of ten, the funnel over
 * three drafts and over twenty — the same count each.
 */
final class PerformanceTest extends McpTestCase
{
    use CountsQueries;

    #[Test]
    public function the_agents_lists_cost_the_same_with_three_orders_and_with_twenty(): void
    {
        $caller = $this->caller();
        $orders = $this->app->make(Orders::class);
        $count = 0;

        foreach ([3, 20] as $total) {
            for (; $count < $total; $count++) {
                $order = $this->order(['customer' => new Contact("Buyer {$count}", 'A', '+800 1234 '.str_pad((string) $count, 4, '0', STR_PAD_LEFT))], [
                    ['type' => 'test.product', 'ref' => 1],
                    ['type' => 'test.product', 'ref' => 3],
                ]);
                $orders->moveTo($order, $this->internal($count % 2 === 0 ? 'Being packed' : 'Handed to delivery'), 'api');
            }

            // [tool, arguments, budget] — every budget has the journal's line of the call (mcp_calls) in it.
            foreach ([
                // The count, the page, the two sets of statuses, the status of «New», the tab counts.
                ['orders_list', ['per_page' => 50], 7],
                // And the public codes turned into ids.
                ['orders_list', ['per_page' => 50, 'search' => 'buyer', 'public' => ['shipped']], 8],
                // The orders by status, both initial statuses, the two sets, the flags, the periods.
                ['orders_summary', [], 7],
                // The board: both sets with the deleted ones, the orders by status, the initial ones.
                ['orders_statuses_list', [], 5],
            ] as [$tool, $arguments, $budget]) {
                $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));
                $call = static fn () => WebxServer::actingAs($caller, 'cms')->tool($bound, $arguments)->assertOk();
                $call();

                $this->assertQueriesWithin($budget, $call, "{$tool}, {$total} orders");
                $this->assertNoRepeatedQueries($call, "{$tool}, {$total} orders");
            }
        }
    }

    #[Test]
    public function the_checkout_s_trial_and_funnel_cost_the_same_for_a_small_shop_and_a_busy_one(): void
    {
        $caller = $this->caller();
        $counts = [];

        for ($ref = 10; $ref < 20; $ref++) {
            $this->products->lines[$ref] = self::product("Item {$ref}", 1000 + $ref);
        }

        foreach ([3, 20] as $size) {
            $cart = $this->cart(array_map(static fn (int $ref): array => ['type' => 'test.product', 'ref' => $ref], range(10, 9 + min($size, 10))));
            CheckoutDraft::query()->create([
                'cart_id' => $cart->cart->id,
                'customer' => ['first_name' => 'Buyer', 'phone' => '+800 1234 5678', 'email' => 'buyer@example.test'],
                'last_step' => 'contacts',
            ]);

            for ($more = CheckoutDraft::query()->count(); $more < $size; $more++) {
                CheckoutDraft::query()->create(['cart_id' => $this->cart()->cart->id, 'last_step' => $more % 3 === 0 ? 'confirm' : 'contacts', 'payable_changes' => $more % 2]);
            }

            // [tool, arguments, budget] — the journal's line of the call (mcp_calls) in each.
            foreach ([
                // The cart, its lines and its draft; the count for the order reads the lines again,
                // as `Orders::place()` does under its lock — what it answers is that count's.
                ['orders_checkout_preview', ['cart_id' => $cart->cart->id], 5],
                // One pass over the drafts of the month, every period in it.
                ['orders_checkout_funnel', [], 2],
            ] as [$tool, $arguments, $budget]) {
                $bound = new RegistryTool($this->app->make(ToolRegistry::class)->tool($tool));
                $call = static fn () => WebxServer::actingAs($caller, 'cms')->tool($bound, $arguments)->assertOk();
                $call();

                $tally = $this->recordQueries($call);

                $this->assertLessThanOrEqual($budget, $tally->count(), "{$tool}, {$size}".PHP_EOL.$tally->describe());
                $counts[$tool][$size] = $tally->count();
            }
        }

        $this->assertSame($counts['orders_checkout_preview'][3], $counts['orders_checkout_preview'][20]);
        $this->assertSame($counts['orders_checkout_funnel'][3], $counts['orders_checkout_funnel'][20]);
    }
}
