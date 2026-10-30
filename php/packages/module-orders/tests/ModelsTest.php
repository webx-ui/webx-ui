<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\Models\StatusChange;

/**
 * The models read what the tables hold: money as integers, blocks and names as arrays, milestones
 * as the enum — and the keys between the tables do what §3 says.
 */
final class ModelsTest extends TestCase
{
    #[Test]
    public function an_order_reads_its_snapshot_and_its_relations(): void
    {
        $order = $this->order();
        $parent = $this->item($order, ['name' => ['en' => 'Set D', 'ru' => 'Набор D'], 'price' => 3000, 'qty' => 1]);
        $this->item($order, ['parent_id' => $parent->id, 'position' => 1, 'name' => ['en' => 'Item A'], 'price' => 0, 'qty' => 2]);
        OrderMilestone::query()->create(['order_id' => $order->id, 'milestone' => Milestone::Placed, 'at' => Carbon::now(), 'source' => 'checkout']);
        StatusChange::query()->create(['order_id' => $order->id, 'kind' => StatusChange::STATUS, 'to_status_id' => $order->status_id, 'source' => 'checkout']);

        $order = Order::query()->with(['status.publicStatus', 'items.children', 'milestones', 'history'])->findOrFail($order->id);

        $this->assertSame(12000, $order->payable);
        $this->assertSame('Buyer A', $order->customerName());
        $this->assertSame(['first_name' => 'Recipient', 'last_name' => null, 'phone' => '+800 1234 5679'], $order->recipient);
        $this->assertSame(['method' => 'store.pickup'], $order->delivery);
        $this->assertTrue($order->awaiting_payment);
        $this->assertSame('accepted', $order->status->publicStatus->code);
        $this->assertCount(2, $order->items);
        $set = $order->items->firstOrFail();
        $this->assertSame('Набор D', $set->nameIn('ru'));
        $this->assertSame('Set D', $set->nameIn('de'));
        $this->assertSame(2, $set->children->firstOrFail()->qty);
        $this->assertSame(Milestone::Placed, $order->milestones->firstOrFail()->milestone);
        $this->assertCount(1, $order->history);
    }

    #[Test]
    public function a_milestone_is_there_once(): void
    {
        $order = $this->order();
        OrderMilestone::query()->create(['order_id' => $order->id, 'milestone' => Milestone::Paid, 'at' => Carbon::now(), 'source' => 'panel']);

        $this->expectException(QueryException::class);

        OrderMilestone::query()->create(['order_id' => $order->id, 'milestone' => Milestone::Paid, 'at' => Carbon::now(), 'source' => 'payment:gateway-a']);
    }

    #[Test]
    public function an_order_keeps_a_status_that_was_deleted(): void
    {
        $order = $this->order();
        $order->status->publicStatus->delete();
        $order->status->delete();

        $order = Order::query()->with('status.publicStatus')->findOrFail($order->id);

        $this->assertTrue($order->status->trashed());
        $this->assertSame('New', $order->status->getTranslation('name', 'en'));
        $this->assertTrue($order->status->publicStatus->trashed());
    }

    #[Test]
    public function the_lines_go_with_the_order_and_the_history_outlives_a_status(): void
    {
        $order = $this->order();
        $this->item($order);
        StatusChange::query()->create(['order_id' => $order->id, 'kind' => StatusChange::NOTE, 'to_status_id' => 999, 'source' => 'api']);

        $order->delete();

        $this->assertSame(0, OrderItem::query()->count());
        $this->assertSame(0, StatusChange::query()->count());
    }

    private function order(): Order
    {
        $status = OrderStatus::query()->orderBy('position')->firstOrFail();
        $this->assertInstanceOf(PublicStatus::class, $status->publicStatus);

        return Order::query()->create([
            'number' => '1001',
            'locale' => 'en',
            'currency' => 'EUR',
            'status_id' => $status->id,
            'subtotal' => 13000,
            'adjustments_total' => -1000,
            'total' => 12000,
            'tenders_total' => 0,
            'payable' => 12000,
            'customer_first_name' => 'Buyer',
            'customer_last_name' => 'A',
            'customer_phone' => '+800 1234 5678',
            'customer_phone_key' => '80012345678',
            'customer_email' => 'buyer-a@example.com',
            'recipient' => ['first_name' => 'Recipient', 'last_name' => null, 'phone' => '+800 1234 5679'],
            'delivery' => ['method' => 'store.pickup'],
            'placed_at' => Carbon::now(),
            'awaiting_payment' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function item(Order $order, array $attributes = []): OrderItem
    {
        return OrderItem::query()->create([
            'order_id' => $order->id,
            'position' => 0,
            'type' => 'demo.item',
            'ref_id' => 1,
            'name' => ['en' => 'Item A'],
            'price' => 1000,
            'qty' => 1,
            'adjustments_total' => 0,
            'total' => 1000,
            'shippable' => true,
            ...$attributes,
        ]);
    }
}
