<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Blocks\BlockDefinition;
use WebxUi\Orders\Blocks\BlockRefused;
use WebxUi\Orders\Blocks\OrderBlocks;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Events\OrderBlockUpdated;
use WebxUi\Orders\Events\OrderDetailsChanged;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\OrderDetails;
use WebxUi\Orders\Orders;

/** What may change in an order after it is placed (§5.3, §6.4, §8): a block, a note, the details. */
final class DetailsTest extends PlaceTestCase
{
    private function orders(): Orders
    {
        return $this->app->make(Orders::class);
    }

    private function registerDelivery(): void
    {
        $this->app->make(OrderBlocks::class)->register(new BlockDefinition(
            'delivery',
            'delivery',
            static fn (array $value): array => isset($value['method']) ? [] : ['the method is missing'],
        ));
    }

    #[Test]
    public function a_patch_of_a_block_is_merged_checked_by_its_owner_and_written_with_a_line_and_an_event(): void
    {
        $this->registerDelivery();
        $events = [];
        Event::listen(OrderBlockUpdated::class, static function (OrderBlockUpdated $event) use (&$events): void {
            $events[] = $event;
        });
        $order = $this->place($this->cart(), ['blocks' => ['delivery' => ['method' => 'carrier-a.branch', 'point' => ['id' => 12, 'town' => 'Town D']]]]);

        $this->orders()->patchBlock($order, 'delivery', ['waybill' => 'WB-100', 'point' => ['town' => 'Town E']], 'delivery:carrier-a');

        $expected = ['method' => 'carrier-a.branch', 'point' => ['id' => 12, 'town' => 'Town E'], 'waybill' => 'WB-100'];
        $this->assertSame($expected, $order->delivery);
        $this->assertSame($expected, Order::query()->findOrFail($order->id)->delivery);
        $line = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::BLOCK)->sole();
        $this->assertSame(['delivery:carrier-a', 'delivery'], [$line->source, $line->note]);
        $this->assertCount(1, $events);
        $this->assertSame(['delivery', ['waybill' => 'WB-100', 'point' => ['town' => 'Town E']]], [$events[0]->block, $events[0]->patch]);
        $this->assertSame('WB-100', $events[0]->order->delivery['waybill'] ?? null);

        // The same again changes nothing: silence.
        $this->orders()->patchBlock($order, 'delivery', ['waybill' => 'WB-100'], 'delivery:carrier-a');
        $this->assertCount(1, $events);
        $this->assertSame(1, StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::BLOCK)->count());
    }

    #[Test]
    public function a_block_nobody_registered_or_its_owner_refuses_is_not_written(): void
    {
        $this->registerDelivery();
        Event::fake([OrderBlockUpdated::class]);
        $order = $this->place($this->cart());

        foreach ([['payment', ['method' => 'cash']], ['delivery', ['waybill' => 'WB-1']]] as [$block, $patch]) {
            try {
                $this->orders()->patchBlock($order, $block, $patch, 'panel');
                $this->fail("The block «{$block}» was written.");
            } catch (BlockRefused $refusal) {
                $this->assertSame($block, $refusal->block);
            }
        }

        $this->assertNull(Order::query()->findOrFail($order->id)->delivery);
        $this->assertNull(Order::query()->findOrFail($order->id)->payment);
        $this->assertFalse(StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::BLOCK)->exists());
        Event::assertNotDispatched(OrderBlockUpdated::class);
    }

    #[Test]
    public function a_note_of_a_module_is_a_line_of_the_history(): void
    {
        $order = $this->place($this->cart());

        $this->orders()->note($order, '  The parcel came back  ', 'delivery:carrier-a');

        $line = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::NOTE)->sole();
        $this->assertSame(['delivery:carrier-a', 'The parcel came back', null], [$line->source, $line->note, $line->admin_id]);

        $this->expectException(InvalidArgumentException::class);
        $this->orders()->note($order, '   ', 'delivery:carrier-a');
    }

    #[Test]
    public function details_change_what_was_named_and_go_to_the_journal_the_timeline_and_an_event(): void
    {
        $events = [];
        Event::listen(OrderDetailsChanged::class, static function (OrderDetailsChanged $event) use (&$events): void {
            $events[] = $event;
        });
        $order = $this->place($this->cart(), ['recipient' => new Contact('Sam', 'Roe', '+1 555 010-0001'), 'comment' => 'Ring twice']);

        $this->orders()->updateDetails($order, (new OrderDetails)
            ->customer(new Contact(' Alex ', 'Doe', '+1 555 010-0009', 'ALEX@example.test'))
            ->recipient(null)
            ->managerNote('  Call after six  '), 'panel');

        $fresh = Order::query()->findOrFail($order->id);
        $this->assertSame(['+1 555 010-0009', '15550100009', null, 'Call after six', 'Ring twice'], [
            $fresh->customer_phone, $fresh->customer_phone_key, $fresh->recipient, $fresh->manager_note, $fresh->comment,
        ]);
        $this->assertSame('Call after six', $order->manager_note);

        // The name and the e-mail came the same after normalising: not changes.
        $fields = ['customer_phone', 'recipient', 'manager_note'];
        $this->assertCount(1, $events);
        $this->assertSame($fields, $events[0]->fields);
        $this->assertSame([
            'customer_phone' => '+1 555 010-0000',
            'recipient' => ['first_name' => 'Sam', 'last_name' => 'Roe', 'phone' => '+1 555 010-0001'],
            'manager_note' => null,
        ], $events[0]->before);

        $line = StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::DETAILS)->sole();
        $this->assertSame(['panel', implode(',', $fields)], [$line->source, $line->note]);

        $journal = DB::table('cms_history')->where('subject_type', 'orders.order')->where('subject_id', $order->id)->sole();
        $this->assertSame('updated', $journal->event);
        $changes = array_column(json_decode((string) $journal->changes, true), null, 'field');
        $this->assertSame(['+1 555 010-0000', '+1 555 010-0009'], [$changes['customer_phone']['from'], $changes['customer_phone']['to']]);
        $this->assertSame(['Sam Roe, +1 555 010-0001', null], [$changes['recipient']['from'], $changes['recipient']['to']]);

        // What it already is: silence.
        $this->orders()->updateDetails($order, (new OrderDetails)->managerNote('Call after six')->comment('Ring twice'), 'panel');
        $this->assertCount(1, $events);
    }

    #[Test]
    public function details_the_order_cannot_keep_are_refused_like_a_form_and_nothing_is_written(): void
    {
        Event::fake([OrderDetailsChanged::class]);
        $order = $this->place($this->cart());

        foreach ([
            'customer.email' => (new OrderDetails)->customer(new Contact('Alex', null, null, 'not-an-e-mail')),
            'customer.phone' => (new OrderDetails)->customer(new Contact('Alex')),
            'recipient.first_name' => (new OrderDetails)->recipient(new Contact(' ')),
            'manager_note' => (new OrderDetails)->managerNote(str_repeat('a', 5001)),
        ] as $field => $patch) {
            try {
                $this->orders()->updateDetails($order, $patch, 'panel');
                $this->fail("{$field} was taken.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($field, $e->errors());
            }
        }

        $this->assertSame('alex@example.test', Order::query()->findOrFail($order->id)->customer_email);
        $this->assertFalse(StatusChange::query()->where('order_id', $order->id)->where('kind', StatusChange::DETAILS)->exists());
        $this->assertSame(0, DB::table('cms_history')->where('subject_type', 'orders.order')->count());
        Event::assertNotDispatched(OrderDetailsChanged::class);
    }
}
