<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebxUi\Cart\AddResult;
use WebxUi\Cart\CartCookie;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Models\CartItem;
use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Cart\Totals\AdjustmentSources;
use WebxUi\Cart\Totals\Benefit;
use WebxUi\Cart\Totals\BenefitSources;
use WebxUi\Cart\Totals\GeneratedLine;
use WebxUi\Cart\Totals\LineSources;
use WebxUi\Cart\Totals\Tender;
use WebxUi\Cart\Totals\TenderSources;
use WebxUi\Cart\Totals\Warning;
use WebxUi\Orders\Blocks\BlockDefinition;
use WebxUi\Orders\Blocks\BlockRefused;
use WebxUi\Orders\Blocks\OrderBlocks;
use WebxUi\Orders\CartNotOrderable;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Events\OrderPlaced;
use WebxUi\Orders\Events\OrderPlacing;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\OrderRefused;
use WebxUi\Orders\Orders;
use WebxUi\Orders\PayableChanged;
use WebxUi\Orders\Tests\Fakes\FakeSources;

final class PlaceTest extends PlaceTestCase
{
    /** What the rules give now; a test changes it after the order to see the order stay. */
    private int $discount = -500;

    /**
     * A bundle with a child, a plain line, a gift a rule puts under the plain line, a discount of
     * that line and one of the cart, a tender and a benefit.
     */
    private function richCart(): AddResult
    {
        $cart = $this->cart([
            ['type' => 'test.product', 'ref' => 2, 'qty' => 2, 'key' => 'set'],
            ['type' => 'test.product', 'ref' => 3, 'qty' => 2, 'parent' => 'set'],
            ['type' => 'test.product', 'ref' => 1, 'qty' => 3],
        ]);
        $plain = CartItem::query()->where('ref_id', 1)->sole()->id;

        $this->app->make(LineSources::class)->register(new FakeSources(static fn (): array => [
            new GeneratedLine('rule#7', 'test.product', 4, parentId: $plain, price: 1, label: 'Gift'),
        ]));
        $this->app->make(AdjustmentSources::class)->register(new FakeSources(fn (): array => [
            new Adjustment('rule#3', 'Three for less', $this->discount, $plain, ['kind' => 'rule']),
            new Adjustment('coupon#SPRING', 'Spring', -1000),
        ]));
        $this->app->make(TenderSources::class)->register(new FakeSources(static fn (): array => [new Tender('wallet', 'Certificate', 2000, ['batches' => [9]])]));
        $this->app->make(BenefitSources::class)->register(new FakeSources(static fn (): array => [
            new Benefit('shipping.paid_by_store', 'Free shipping', 'rule#4'),
            new Benefit('shipping.paid_by_store', 'Free shipping', 'coupon#SPRING'),
        ]));

        return $cart;
    }

    #[Test]
    public function the_order_is_the_cart_frozen_and_stays_so_when_prices_and_rules_change(): void
    {
        $order = $this->place($this->richCart(), ['comment' => '  Ring twice  ', 'userId' => 42]);

        // 2 × 15000 + 2 × 2 × 700 + 3 × 4999 + 1 = 47798; −500 −1000 = 46298; −2000 = 44298.
        $expected = [
            'number' => (string) (1000 + $order->id),
            'subtotal' => 47798,
            'adjustments_total' => -1500,
            'total' => 46298,
            'tenders_total' => 2000,
            'payable' => 44298,
            'currency' => 'EUR',
            'user_id' => 42,
            'customer_first_name' => 'Alex',
            'customer_last_name' => 'Doe',
            'customer_phone' => '+1 555 010-0000',
            'customer_phone_key' => '15550100000',
            'customer_email' => 'alex@example.test',
            'comment' => 'Ring twice',
            'awaiting_payment' => false,
        ];
        $lines = [
            // [position, ref, parent ref, qty, price, adjustments, total, source, label]
            [0, 2, null, 2, 15000, 0, 30000, null, null],
            [1, 3, 2, 4, 700, 0, 2800, null, null],
            [2, 1, null, 3, 4999, -500, 14497, null, null],
            [3, 4, 1, 1, 1, 0, 1, 'rule#7', 'Gift'],
        ];

        $this->assertSnapshot($order, $expected, $lines);
        $data = OrderData::of(Order::query()->findOrFail($order->id));

        // Everything changes behind the order's back.
        foreach ($this->products->lines as $ref => $line) {
            $this->products->lines[$ref] = self::product($line->name.' renamed', $line->price * 2);
        }

        $this->discount = -4000;

        $this->assertSnapshot(Order::query()->findOrFail($order->id), $expected, $lines);
        $this->assertEquals($data, OrderData::of(Order::query()->findOrFail($order->id)));
        $this->assertSame('Item A', $data->items[2]->name);
        $this->assertSame(['en' => 'Item A', 'ru' => 'Товар A'], OrderItem::query()->where('ref_id', 1)->sole()->name);
        $this->assertSame(['en' => 'Set B'], OrderItem::query()->where('ref_id', 2)->sole()->name);
    }

    /**
     * @param  array<string, mixed>  $expected
     * @param  list<array{int, int, int|null, int, int, int, int, string|null, string|null}>  $lines
     */
    private function assertSnapshot(Order $order, array $expected, array $lines): void
    {
        foreach ($expected as $column => $value) {
            $this->assertSame($value, $order->{$column}, $column);
        }

        $items = OrderItem::query()->where('order_id', $order->id)->orderBy('position')->get();
        $byId = $items->keyBy('id');
        $this->assertCount(count($lines), $items);

        foreach ($lines as $index => [$position, $ref, $parentRef, $qty, $price, $adjustments, $total, $source, $label]) {
            $item = $items[$index];
            $this->assertSame([$position, $ref, $qty, $price, $adjustments, $total, $source, $label], [
                $item->position, $item->ref_id, $item->qty, $item->price, $item->adjustments_total, $item->total, $item->source, $item->label,
            ], "line {$index}");
            $this->assertSame($parentRef, $item->parent_id !== null ? $byId[$item->parent_id]->ref_id : null, "parent of line {$index}");
        }

        $plain = $items->firstWhere('ref_id', 1);
        $this->assertSame(
            [['rule#3', 'Three for less', -500, $plain?->id, ['kind' => 'rule']], ['coupon#SPRING', 'Spring', -1000, null, null]],
            $order->adjustments()->get()->map(fn ($a): array => [$a->source, $a->label, $a->amount, $a->item_id, $a->meta])->all(),
        );
        $this->assertSame([['wallet', 'Certificate', 2000, ['batches' => [9]]]], $order->tenders()->get()->map(fn ($t): array => [$t->source, $t->label, $t->amount, $t->meta])->all());
        // Two sources of one benefit are one fact.
        $this->assertSame([['shipping.paid_by_store', 'rule#4']], $order->benefits()->get()->map(fn ($b): array => [$b->key, $b->source])->all());
    }

    #[Test]
    public function the_order_comes_into_the_initial_status_with_its_milestone_and_the_first_line_of_history(): void
    {
        $order = $this->place($this->cart());

        $this->assertSame('New', $order->status->getTranslation('name', 'en'));
        $this->assertSame([Milestone::Placed], $order->milestones()->get()->map(fn ($m) => $m->milestone)->all());
        $this->assertSame('checkout', $order->milestones()->sole()->source);
        $line = StatusChange::query()->sole();
        $this->assertSame([StatusChange::STATUS, null, $order->status_id, $order->status->public_status_id, 'checkout'], [
            $line->kind, $line->from_status_id, $line->to_status_id, $line->to_public_id, $line->source,
        ]);

        // An order that awaits payment: its own status, and the milestone beside «placed».
        $awaiting = $this->place($this->cart(), ['awaitPayment' => true, 'source' => 'api']);
        $this->assertTrue($awaiting->awaiting_payment);
        $this->assertSame('Awaiting payment', $awaiting->status->getTranslation('name', 'en'));
        $this->assertSame(['placed', 'awaiting_payment'], $awaiting->milestones()->get()->map(fn ($m) => $m->milestone->value)->all());

        // The settings name another one.
        $taken = $order->status->newQuery()->get()->first(fn ($status) => $status->getTranslation('name', 'en') === 'Taken into work');
        config(['webx-orders.initial_status' => $taken?->id]);
        $this->assertSame($taken?->id, $this->place($this->cart())->status_id);
    }

    #[Test]
    public function the_number_is_made_of_the_id_by_the_settings(): void
    {
        config(['webx-orders.number' => ['prefix' => 'A-', 'start' => 0, 'pad' => 6]]);

        $order = $this->place($this->cart());

        $this->assertSame('A-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT), $order->number);
    }

    #[Test]
    public function a_repeat_with_the_key_is_the_same_order_and_the_key_finds_it(): void
    {
        Event::fake([OrderPlaced::class]);
        $cart = $this->cart();

        $first = $this->place($cart, ['idempotencyKey' => 'form-token-1']);
        // The buyer clicks again, the cart already empty: the answer is the order, not «empty».
        $again = $this->place($cart, ['idempotencyKey' => 'form-token-1']);

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(hash('sha256', 'form-token-1'), $first->idempotency_key);
        $this->assertSame($first->id, $this->app->make(Orders::class)->findByIdempotencyKey('form-token-1')?->id);
        $this->assertNull($this->app->make(Orders::class)->findByIdempotencyKey('form-token-2'));
        $this->assertNull($this->app->make(Orders::class)->findByIdempotencyKey(''));
        Event::assertDispatchedTimes(OrderPlaced::class, 1);
    }

    #[Test]
    public function the_cart_is_emptied_and_its_row_and_cookie_live_on(): void
    {
        $cart = $this->cart([['type' => 'test.product', 'ref' => 1, 'key' => 'set'], ['type' => 'test.product', 'ref' => 3, 'parent' => 'set']]);
        $cart->cart->forceFill(['declined_rules' => ['rule#1']])->save();

        $this->place($cart);

        $this->assertSame(0, CartItem::query()->count());
        $kept = $this->app->make(CartCookie::class)->find($cart->token);
        $this->assertNotNull($kept);
        $this->assertSame($cart->cart->id, $kept->id);
        $this->assertNull($kept->declined_rules);
    }

    #[Test]
    public function a_refusal_of_order_placing_takes_everything_back(): void
    {
        Event::fake([OrderPlaced::class]);
        $cart = $this->cart();
        $seen = null;

        Event::listen(OrderPlacing::class, static function (OrderPlacing $event) use (&$seen): void {
            // Written already, with its number: what the wallet spends against.
            $seen = [$event->order->number, $event->order->items()->count(), $event->state->payable];

            throw new OrderRefused('wallet.spent', 'The certificate is already used.');
        });

        try {
            $this->place($cart, ['idempotencyKey' => 'k']);
            $this->fail('The order was made.');
        } catch (OrderRefused $e) {
            $this->assertSame('wallet.spent', $e->reason);
            $this->assertSame('The certificate is already used.', $e->getMessage());
        }

        $this->assertNotNull($seen);
        $this->assertSame(1, $seen[1]);
        $this->assertSame(4999, $seen[2]);

        foreach (['orders', 'order_items', 'order_milestones', 'order_status_changes'] as $table) {
            $this->assertSame(0, $this->app['db']->table($table)->count(), $table);
        }

        $this->assertSame(1, CartItem::query()->count());
        $this->assertNull($this->app->make(Orders::class)->findByIdempotencyKey('k'));
        Event::assertNotDispatched(OrderPlaced::class);
    }

    #[Test]
    public function order_placed_carries_the_snapshot_after_the_commit(): void
    {
        $events = [];
        Event::listen(OrderPlaced::class, static function (OrderPlaced $event) use (&$events): void {
            $events[] = $event;
        });

        $order = $this->place($this->cart([['type' => 'test.product', 'ref' => 1, 'qty' => 2]]), ['awaitPayment' => true]);

        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertTrue($event->awaitingPayment);
        $this->assertSame('checkout', $event->source);
        $this->assertNull($event->adminId);
        $this->assertSame($order->id, $event->order->id);
        $this->assertSame($order->number, $event->order->number);
        $this->assertSame('Alex Doe', $event->order->customer->name());
        $this->assertSame(9998, $event->order->payable);
        $this->assertSame('accepted', $event->order->publicStatus->code);
        $this->assertSame(['placed', 'awaiting_payment'], array_keys($event->order->milestones));
        // A listener in a queue gets it serialised, and the same.
        $this->assertEquals($event->order, unserialize(serialize($event->order)));
    }

    #[Test]
    public function a_payable_the_buyer_did_not_see_is_refused_with_the_fresh_state(): void
    {
        $cart = $this->cart();

        try {
            $this->place($cart, ['expectedPayable' => 4000]);
            $this->fail('The order was made.');
        } catch (PayableChanged $e) {
            $this->assertSame(4999, $e->state->payable);
            $this->assertSame(4000, $e->expected);
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, CartItem::query()->count());
        $this->assertSame(4999, $this->place($cart, ['expectedPayable' => 4999])->payable);
    }

    #[Test]
    public function a_cart_that_cannot_be_ordered_says_why_and_names_the_lines(): void
    {
        $cart = $this->cart([['type' => 'test.product', 'ref' => 1], ['type' => 'test.product', 'ref' => 3, 'qty' => 5]]);
        $this->products->lines[1] = self::product('Item A', 4999, available: false);
        $this->products->lines[3] = self::product('Part C', 700, maxQty: 2);

        $refusal = $this->refusal(fn () => $this->place($cart));
        $this->assertSame(CartNotOrderable::LINES, $refusal->reason);
        $this->assertSame([Warning::LINE_UNAVAILABLE, Warning::LINE_QTY_LIMITED], array_map(fn (Warning $w): string => $w->code, $refusal->warnings));

        // A price that moved is the payable's business, not a refusal of the line.
        $this->products->lines[1] = self::product('Item A', 3999);
        $this->products->lines[3] = self::product('Part C', 700);
        $this->assertSame(3999 + 5 * 700, $this->place($cart)->payable);

        $this->assertSame(CartNotOrderable::EMPTY, $this->refusal(fn () => $this->place($cart))->reason);

        $other = $this->cart();
        config(['webx-cart.currency' => 'USD']);
        $this->assertSame(CartNotOrderable::CURRENCY, $this->refusal(fn () => $this->place($other))->reason);
        $this->assertSame(1, Order::query()->count());
    }

    private function refusal(callable $place): CartNotOrderable
    {
        try {
            $place();
        } catch (CartNotOrderable $e) {
            return $e;
        }

        $this->fail('The order was made.');
    }

    #[Test]
    public function a_source_that_fails_fails_the_order_not_a_line_of_it(): void
    {
        $cart = $this->cart();
        $this->app->make(AdjustmentSources::class)->register(new FakeSources(static fn (): array => throw new RuntimeException('rules are down')));

        try {
            $this->place($cart);
            $this->fail('The order was made of a chain with a link missing.');
        } catch (RuntimeException $e) {
            $this->assertSame('rules are down', $e->getMessage());
        }

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(1, CartItem::query()->count());
    }

    #[Test]
    public function the_contacts_need_a_first_name_and_a_way_to_reach_the_buyer(): void
    {
        $cart = $this->cart();
        $errors = function (Contact $customer, ?Contact $recipient = null) use ($cart): array {
            try {
                $this->place($cart, ['customer' => $customer, 'recipient' => $recipient]);
            } catch (ValidationException $e) {
                return array_keys($e->errors());
            }

            $this->fail('The order was made.');
        };

        $this->assertSame(['customer.first_name', 'customer.phone', 'customer.email'], $errors(new Contact('  ')));
        $this->assertSame(['customer.phone'], $errors(new Contact('Alex', phone: 'call me')));
        $this->assertSame(['customer.email'], $errors(new Contact('Alex', email: 'not an address')));
        $this->assertSame(['recipient.first_name'], $errors(new Contact('Alex', email: 'a@example.test'), new Contact('')));
        $this->assertSame(0, Order::query()->count());

        // A phone alone will do, and the recipient is kept apart from the buyer.
        $order = $this->place($cart, ['customer' => new Contact('Alex', phone: '+1 (555) 010-0000'), 'recipient' => new Contact('Sam', 'Roe', '+1 555 010-0001')]);
        $this->assertNull($order->customer_email);
        $this->assertSame(['first_name' => 'Sam', 'last_name' => 'Roe', 'phone' => '+1 555 010-0001'], $order->recipient);
    }

    #[Test]
    public function a_block_is_taken_only_as_its_owner_registered_it(): void
    {
        $cart = $this->cart();
        $delivery = ['method' => 'store.pickup', 'title' => ['en' => 'Pickup'], 'point' => ['id' => 3]];

        try {
            $this->place($cart, ['blocks' => ['delivery' => $delivery]]);
            $this->fail('A block nobody registered went in.');
        } catch (BlockRefused $e) {
            $this->assertSame('delivery', $e->block);
        }

        $blocks = $this->app->make(OrderBlocks::class);
        $blocks->register(new BlockDefinition('delivery', 'delivery', static fn (array $value): array => isset($value['method']) ? [] : ['method is required']));

        try {
            $this->place($cart, ['blocks' => ['delivery' => ['title' => []]]]);
            $this->fail('A block its owner refuses went in.');
        } catch (BlockRefused $e) {
            $this->assertSame(['method is required'], $e->errors);
        }

        $this->assertSame(0, Order::query()->count());
        $order = $this->place($cart, ['blocks' => ['delivery' => $delivery]]);
        $this->assertSame($delivery, $order->fresh()?->delivery);
        $this->assertNull($order->payment);

        $this->expectException(\InvalidArgumentException::class);
        $blocks->register(new BlockDefinition('gift_wrap', 'gifts', static fn (): array => []));
    }
}
