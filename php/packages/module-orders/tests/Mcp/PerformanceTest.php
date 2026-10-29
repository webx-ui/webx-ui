<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Mcp;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Orders;

/**
 * The query budget of the agent's lists (spec §12, §16): `orders_list`, `orders_summary` and
 * `orders_statuses_list` ask the same with three orders and with twenty. No cache — the orders
 * are alive, as in the panel — so the count is held instead: the page with its first line as a
 * subquery and its statuses in two, the summary in `GROUP BY`s over indexes, the board in three.
 * Measured warm: the caller and their roles are read once a request.
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
}
