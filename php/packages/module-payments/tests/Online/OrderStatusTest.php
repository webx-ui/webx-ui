<?php

declare(strict_types=1);

namespace WebxUi\Payments\Tests\Online;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Orders;
use WebxUi\Payments\Payments;
use WebxUi\Payments\PaymentStatus;

/**
 * The money is in, so the order leaves «Awaiting payment» for the status after payment — the one
 * of a new order unless the settings name another — whether a gateway said so or a manager marked
 * it. An order a manager moved on by hand stays where it is.
 */
final class OrderStatusTest extends OnlineCase
{
    private function statusOf(Order $order): string
    {
        return (string) $order->refresh()->status?->getTranslation('name', 'en');
    }

    #[Test]
    public function the_gateway_takes_the_order_out_of_awaiting_payment(): void
    {
        [$order, $payment] = $this->attemptAtGateway();
        $this->assertSame('Awaiting payment', $this->statusOf($order));

        $this->server()->act($payment->uuid, 'pay');
        $this->travel(6)->minutes();
        $this->artisan('webx:payments:poll')->assertSuccessful();

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame('New', $this->statusOf($order));
        $this->assertFalse($order->awaiting_payment);
    }

    #[Test]
    public function a_manual_mark_takes_the_order_out_of_awaiting_payment_too(): void
    {
        $order = $this->onlineOrder();
        $this->assertSame('Awaiting payment', $this->statusOf($order));

        $this->app->make(Payments::class)->markPaid($order, 'panel', 'PD-0001');

        $this->assertSame('New', $this->statusOf($order));
    }

    #[Test]
    public function an_order_a_manager_moved_on_stays_where_it_is(): void
    {
        [$order, $payment] = $this->attemptAtGateway();
        $taken = OrderStatus::query()->get()->first(fn (OrderStatus $status): bool => $status->getTranslation('name', 'en') === 'Taken into work');
        $this->assertNotNull($taken);
        $this->app->make(Orders::class)->moveTo($order, $taken, 'panel');

        $this->server()->act($payment->uuid, 'pay');
        $this->travel(6)->minutes();
        $this->artisan('webx:payments:poll')->assertSuccessful();

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame('Taken into work', $this->statusOf($order));
    }
}
