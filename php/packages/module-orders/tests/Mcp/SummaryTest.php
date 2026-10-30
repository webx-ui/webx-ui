<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Mcp;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Orders;
use WebxUi\Orders\RefundRequest;

/**
 * The figures over the panel's list are the agent's `orders_summary` (decision 93): one class
 * counts both, so the route and the tool answer the same — and what they answer is right.
 */
final class SummaryTest extends McpTestCase
{
    #[Test]
    public function the_figures_over_the_list_are_the_agent_s_summary(): void
    {
        $orders = $this->app->make(Orders::class);
        $buyer = static fn (string $name): Contact => new Contact($name, 'Doe', '+800 1234 5678', 'buyer@example.com');

        // Twelve days ago: in the thirty days, not in the seven.
        $this->travelTo(Carbon::now()->subDays(12));
        $old = $this->order(['customer' => $buyer('Old')]);
        $orders->mark($old, Milestone::Paid, 'api');
        $this->travelBack();

        $paid = $this->order(['customer' => $buyer('Paid')]);
        $orders->mark($paid, Milestone::Paid, 'api');
        $orders->refund($paid, new RefundRequest(999, source: 'api'));
        $this->order(['customer' => $buyer('Waiting'), 'awaitPayment' => true]);

        $this->signIn(['orders.view']);
        $panel = $this->getJson($this->api('summary'))->assertOk()->json('data');

        $this->assertSame($panel, $this->ask('orders_summary'));

        [$day, $week, $month] = $panel['periods'];
        $this->assertSame([1, 7, 30], [$day['days'], $week['days'], $month['days']]);
        $this->assertSame(['currency' => 'EUR', 'placed' => 2, 'paid' => 1], array_intersect_key($day['by_currency'][0], array_flip(['currency', 'placed', 'paid'])));
        // Paid less given back, and the average of a paid order.
        $this->assertSame(4999 - 999, $day['by_currency'][0]['revenue']);
        $this->assertSame($this->money(4000), $day['by_currency'][0]['revenue_formatted']);
        $this->assertSame(4000, $day['by_currency'][0]['average']);
        $this->assertSame([3, 2, 4000 + 4999, intdiv(4000 + 4999, 2)], [
            $month['by_currency'][0]['placed'],
            $month['by_currency'][0]['paid'],
            $month['by_currency'][0]['revenue'],
            $month['by_currency'][0]['average'],
        ]);

        // What waits for payment now, whenever it was ordered.
        $this->assertSame([['currency' => 'EUR', 'orders' => 1, 'amount' => 4999, 'amount_formatted' => $this->money(4999)]], $panel['awaiting_by_currency']);
        $this->assertSame(1, $panel['awaiting_payment']);

        // A bar a day for thirty days, today last; twelve days back, the old order.
        $this->assertCount(30, $panel['daily']);
        $this->assertSame(['date' => Carbon::now()->toDateString(), 'orders' => 2], $panel['daily'][29]);
        $this->assertSame(['date' => Carbon::now()->subDays(12)->toDateString(), 'orders' => 1], $panel['daily'][17]);
    }

    #[Test]
    public function the_figures_stand_behind_the_right_to_read_orders(): void
    {
        $this->signIn(['orders.refund']);
        $this->getJson($this->api('summary'))->assertOk();

        $this->signIn(['pages.view']);
        $this->getJson($this->api('summary'))->assertForbidden();
    }
}
