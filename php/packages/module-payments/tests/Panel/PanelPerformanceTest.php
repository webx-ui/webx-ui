<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Panel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Testing\CountsQueries;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Payments;

/**
 * The lists of the panel (spec §18), warm, on four orders whose card gateway A declined: the methods, the
 * attempts, the exchange journal and the order's tab. None repeats a statement per row — the
 * orders' numbers, the titles of the methods, the events of the attempts are each one query — and
 * none grows with the rows: the budget is the same for one order and for four.
 */
final class PanelPerformanceTest extends PanelCase
{
    use CountsQueries;

    /** @return array<string, array{0: string, 1: int}> */
    public static function lists(): array
    {
        // The budgets count the panel's own work: the session, the administrator and the roles.
        return [
            'methods' => ['methods', 12],
            'groups' => ['groups', 12],
            'attempts' => ['attempts', 12],
            'exchange' => ['exchange', 12],
            'exchange of an order' => ['exchange?errors=1&kind=notify', 12],
        ];
    }

    #[Test]
    #[DataProvider('lists')]
    public function a_list_reads_each_thing_once_whatever_the_rows(string $path, int $budget): void
    {
        $this->orders(4);
        $this->signIn(['payments.view', 'orders.manage']);

        $this->getJson($this->api($path))->assertOk();
        $this->assertNoRepeatedQueries(fn () => $this->getJson($this->api($path))->assertOk(), $path);
        $this->assertQueriesWithin($budget, fn () => $this->getJson($this->api($path))->assertOk(), $path);
    }

    #[Test]
    public function the_tab_of_an_order_reads_its_attempts_and_events_by_one_query_each(): void
    {
        $order = $this->orders(4)[0];
        $this->signIn(['payments.view', 'orders.manage']);

        $this->getJson($this->api('orders/'.$order->id))->assertOk();
        $this->assertNoRepeatedQueries(fn () => $this->getJson($this->api('orders/'.$order->id))->assertOk());
        $this->assertQueriesWithin(16, fn () => $this->getJson($this->api('orders/'.$order->id))->assertOk());
    }

    /** @return list<Order> orders each with an attempt at gateway A the bank declined */
    private function orders(int $count): array
    {
        $orders = [];

        for ($i = 0; $i < $count; $i++) {
            [$order, $payment] = $this->attemptAtGateway('card');
            $this->server()->act($payment->uuid, 'decline');
            $this->app->make(Payments::class)->check($payment->refresh(), 'cli');
            $orders[] = $order;
        }

        return $orders;
    }
}
