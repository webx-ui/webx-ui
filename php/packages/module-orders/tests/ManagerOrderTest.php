<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Cart\Totals\AdjustmentSources;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Events\OrderPlaced;
use WebxUi\Orders\Events\OrderPlacing;
use WebxUi\Orders\ManagerOrder;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\OrderRefused;
use WebxUi\Orders\Orders;
use WebxUi\Orders\Tests\Fakes\FakeSources;
use WebxUi\Orders\Tests\Panel\PanelTestCase;

/** §29.2 — the order a manager makes in the panel, over a cart of its own and `place()`. */
final class ManagerOrderTest extends PanelTestCase
{
    private const array LINES = [
        ['type' => 'test.product', 'ref' => 1, 'qty' => 2],
        ['type' => 'test.product', 'ref' => 3],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // A rule of the cart: the manager's order gets it as the checkout's does.
        $this->app->make(AdjustmentSources::class)->register(new FakeSources(static fn (): array => [
            new Adjustment('rule#3', 'Spring', -500),
        ]));
    }

    /** @param  list<array<string, mixed>>  $lines */
    private function input(array $lines = self::LINES, ?string $key = null, ?string $phone = '+800 1234 5678'): ManagerOrder
    {
        return new ManagerOrder(
            lines: $lines,
            locale: 'en',
            customer: new Contact('Alex', 'Doe', $phone, 'alex@example.test'),
            recipient: new Contact('Sam', 'Roe', '+800 1234 0001'),
            comment: '  Call before noon  ',
            idempotencyKey: $key,
        );
    }

    private function orders(): Orders
    {
        return $this->app->make(Orders::class);
    }

    /** @return list<list<mixed>> */
    private static function lines(Order $order): array
    {
        return OrderItem::query()->where('order_id', $order->id)->orderBy('position')->get()
            ->map(static fn (OrderItem $item): array => [$item->type, $item->ref_id, $item->sku, $item->price, $item->qty, $item->total])
            ->all();
    }

    #[Test]
    public function the_manager_s_order_is_the_order_the_checkout_makes_of_the_same_lines(): void
    {
        $this->listenFor(OrderPlaced::class);
        $checkout = $this->place($this->cart(self::LINES));
        $manager = $this->signIn();

        $order = $this->orders()->placeByManager($this->input());

        // The same snapshot, prices and rules, the next number.
        $this->assertSame(self::lines($checkout), self::lines($order));
        $this->assertSame(
            [$checkout->subtotal, $checkout->adjustments_total, $checkout->total, $checkout->payable],
            [$order->subtotal, $order->adjustments_total, $order->total, $order->payable],
        );
        $this->assertSame(2 * 4999 + 700 - 500, $order->payable);
        $this->assertSame((int) $checkout->number + 1, (int) $order->number);
        $this->assertSame(2, $this->times(OrderPlaced::class));

        // The contacts as typed, trimmed.
        $this->assertSame(['Alex', 'Doe', '+800 1234 5678', 'alex@example.test', 'Call before noon'], [
            $order->customer_first_name, $order->customer_last_name, $order->customer_phone, $order->customer_email, $order->comment,
        ]);
        $this->assertSame(['first_name' => 'Sam', 'last_name' => 'Roe', 'phone' => '+800 1234 0001'], $order->recipient);

        // From the panel, and by whom.
        $placed = OrderMilestone::query()->where('order_id', $order->id)->where('milestone', Milestone::Placed->value)->sole();
        $this->assertSame(['panel', $manager->id], [$placed->source, $placed->admin_id]);
        $first = StatusChange::query()->where('order_id', $order->id)->sole();
        $this->assertSame(['panel', $manager->id], [$first->source, $first->admin_id]);

        // The cart of its own is gone; the checkout's cart stays, emptied (decision 34).
        $this->assertSame([$checkout->cart_id], Cart::query()->pluck('id')->all());
        $this->assertSame(0, CartItem::query()->count());
    }

    #[Test]
    public function the_agent_s_order_is_in_the_history_as_the_agent_s(): void
    {
        $manager = $this->signIn();

        $order = $this->orders()->placeByManager($this->input(), 'mcp');

        $placed = OrderMilestone::query()->where('order_id', $order->id)->sole();
        $this->assertSame(['mcp', $manager->id], [$placed->source, $placed->admin_id]);
    }

    #[Test]
    public function a_refusal_at_any_step_leaves_neither_a_cart_nor_an_order(): void
    {
        $this->signIn();
        $refused = function (ManagerOrder $input, string $field): void {
            try {
                $this->orders()->placeByManager($input);
                $this->fail("No refusal under {$field}.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($field, $e->errors());
            }
        };

        // No lines, a line that is not there, a line not for sale, a wrong phone.
        $refused($this->input([]), 'items');
        $refused($this->input([['type' => 'test.product', 'ref' => 99]]), 'items.0.ref');
        $this->products->lines[3] = self::product('Part C', 700, available: false);
        $refused($this->input(), 'items.1.ref');
        $this->products->lines[3] = self::product('Part C', 700);
        $refused($this->input(phone: 'call me'), 'customer.phone');

        // Refused inside the order's transaction, after the cart was written.
        Event::listen(OrderPlacing::class, static fn () => throw new OrderRefused('test.refused', 'Not today.'));

        try {
            $this->orders()->placeByManager($this->input());
            $this->fail('No refusal of the listener.');
        } catch (OrderRefused) {
        }

        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, CartItem::query()->count());
        $this->assertSame(0, Order::query()->count());
    }

    #[Test]
    public function the_same_form_sent_twice_is_one_order(): void
    {
        $this->signIn();

        $first = $this->orders()->placeByManager($this->input(key: 'form-1'));
        $second = $this->orders()->placeByManager($this->input(key: 'form-1'));

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(0, Cart::query()->count());
    }

    #[Test]
    public function the_preview_counts_the_order_and_writes_nothing(): void
    {
        $this->signIn();

        $preview = $this->orders()->previewByManager($this->input());

        $this->assertTrue($preview->orderable());
        $this->assertSame(2 * 4999 + 700 - 500, $preview->state->payable);
        $this->assertCount(2, $preview->state->lines);
        $this->assertSame(0, Cart::query()->count());
        $this->assertSame(0, CartItem::query()->count());
        $this->assertSame(0, Order::query()->count());

        // The refusals are the order's.
        $this->expectException(ValidationException::class);
        $this->orders()->previewByManager($this->input([]));
    }
}
