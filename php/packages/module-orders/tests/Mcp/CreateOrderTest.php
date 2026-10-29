<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Mcp;

use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Orders\Events\OrderPlacing;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\OrderRefused;

/** `orders_create` (§29.2): `Orders::placeByManager()` for the agent. */
final class CreateOrderTest extends McpTestCase
{
    /** @return array<string, mixed> */
    private static function arguments(array $with = []): array
    {
        return [
            'lines' => [['type' => 'test.product', 'ref' => 1, 'qty' => 2], ['type' => 'test.product', 'ref' => 3]],
            'customer' => ['first_name' => 'Alex', 'last_name' => 'Doe', 'phone' => '+800 1234 5678'],
            'comment' => 'Call before noon',
            ...$with,
        ];
    }

    /** @return list<int> how many rows the order and its cart left */
    private static function rows(): array
    {
        return [Cart::query()->count(), CartItem::query()->count(), Order::query()->count(), OrderMilestone::query()->count(), StatusChange::query()->count()];
    }

    #[Test]
    public function the_agent_places_an_order_and_is_in_its_history(): void
    {
        $answer = $this->ask('orders_create', self::arguments());
        $order = Order::query()->sole();

        $this->assertFalse($answer['dry_run']);
        $this->assertSame($order->number, $answer['order']['number']);
        $this->assertSame(2 * 4999 + 700, $answer['order']['totals']['payable']);
        $this->assertSame(['Alex', 'Doe'], [$answer['order']['customer']['first_name'], $answer['order']['customer']['last_name']]);
        $this->assertSame(['mcp', $this->caller()->id], [
            OrderMilestone::query()->sole()->source,
            OrderMilestone::query()->sole()->admin_id,
        ]);
        $this->assertSame(0, Cart::query()->count());
    }

    #[Test]
    public function a_dry_run_shows_the_lines_and_the_totals_and_writes_nothing(): void
    {
        $preview = $this->ask('orders_create', self::arguments(['dry_run' => true]));

        $this->assertTrue($preview['dry_run']);
        $this->assertTrue($preview['orderable']);
        $this->assertNull($preview['refusal']);
        $this->assertSame(['Item A', 'Part C'], array_column($preview['totals']['lines'], 'name'));
        $this->assertSame(2 * 4999 + 700, $preview['totals']['payable']);
        $this->assertArrayHasKey('warnings', $preview['totals']);
        $this->assertSame([0, 0, 0, 0, 0], self::rows());

        // A dry run is refused as the call is.
        $this->agent('orders_create', self::arguments(['lines' => [], 'dry_run' => true]))->assertHasErrors(['items']);
        $this->agent('orders_create', self::arguments(['customer' => ['first_name' => 'Alex', 'phone' => 'call me'], 'dry_run' => true]))->assertHasErrors(['customer.phone']);
        $this->assertSame([0, 0, 0, 0, 0], self::rows());
    }

    #[Test]
    public function a_refusal_leaves_neither_a_cart_nor_an_order(): void
    {
        $this->agent('orders_create', self::arguments(['lines' => [['type' => 'test.product', 'ref' => 99]]]))->assertHasErrors(['items.0.ref']);
        $this->agent('orders_create', self::arguments(['lines' => [['type' => 'test.product', 'ref' => 1, 'price' => 1]]]))->assertHasErrors(['price']);
        $this->agent('orders_create', self::arguments(['locale' => 'xx']))->assertHasErrors(['xx']);

        Event::listen(OrderPlacing::class, static fn () => throw new OrderRefused('test.refused', 'Not today.'));
        $this->agent('orders_create', self::arguments())->assertHasErrors(['Not today.']);

        $this->assertSame([0, 0, 0, 0, 0], self::rows());
    }

    #[Test]
    public function the_same_key_is_one_order(): void
    {
        $first = $this->ask('orders_create', self::arguments(['key' => 'call-17']));
        $second = $this->ask('orders_create', self::arguments(['key' => 'call-17']));

        $this->assertSame($first['order']['number'], $second['order']['number']);
        $this->assertSame(1, Order::query()->count());
    }

    #[Test]
    public function without_orders_manage_it_is_refused(): void
    {
        foreach ([['orders.view'], ['orders.view', 'orders.refund']] as $permissions) {
            $this->agent('orders_create', self::arguments(), $this->manager($permissions))->assertHasErrors();
            $this->agent('orders_create', self::arguments(['dry_run' => true]), $this->manager($permissions))->assertHasErrors();
        }

        $this->assertSame([0, 0, 0, 0, 0], self::rows());
    }
}
