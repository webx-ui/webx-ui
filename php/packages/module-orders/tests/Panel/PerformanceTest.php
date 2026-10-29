<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Orders;

/**
 * The budget of the panel's list and card (spec §16, WEBX_UI_PERFORMANCE.md): no cache — the data
 * is alive — so the count is held instead. A page is its query with the first line as a subquery,
 * the tab counts in one, and the statuses in two whatever the number of rows; a page of three
 * orders and one of twenty ask the same.
 */
final class PerformanceTest extends PanelTestCase
{
    use CountsQueries;

    #[Test]
    public function a_page_of_three_and_a_page_of_twenty_cost_the_same(): void
    {
        $counts = [];

        foreach ([3, 17] as $more) {
            for ($i = 0; $i < $more; $i++) {
                $this->order(['customer' => new Contact("Buyer {$i}", 'A', '+800 1234 '.str_pad((string) $i, 4, '0', STR_PAD_LEFT))], [
                    ['type' => 'test.product', 'ref' => 1],
                    ['type' => 'test.product', 'ref' => 3],
                ]);
            }

            // Somebody fresh each time: a second request of the same one has their roles already.
            $this->signIn(['orders.view']);

            // The sign-in and its roles, the page and its total, the tab counts with the initial
            // status of «New», the two sets of statuses.
            $counts[] = $this->assertNoRepeatedQueries(
                fn () => $this->recordQueries(fn () => $this->getJson($this->api().'?per_page=50')->assertOk())->count(),
                'A row of the list asks a question of its own.',
            );
        }

        $this->assertSame($counts[0], $counts[1], 'A longer page asks more questions.');
        $this->assertLessThanOrEqual(10, $counts[1]);
    }

    #[Test]
    public function the_card_costs_the_same_with_a_long_history(): void
    {
        $this->signIn(['orders.view']);
        $orders = $this->app->make(Orders::class);
        $short = $this->order();
        $long = $this->order([], [['type' => 'test.product', 'ref' => 1], ['type' => 'test.product', 'ref' => 3], ['type' => 'test.product', 'ref' => 4]]);

        foreach (['Taken into work', 'Waiting for a call', 'Being packed', 'Handed to delivery'] as $name) {
            $orders->moveTo($long, $this->internal($name), 'panel');
            $orders->note($long, "Moved to {$name}", 'panel');
        }

        $counts = array_map(
            fn ($order): int => $this->recordQueries(fn () => $this->getJson($this->api($order->id))->assertOk())->count(),
            [$short, $long],
        );

        $this->assertSame($counts[0], $counts[1]);
    }
}
