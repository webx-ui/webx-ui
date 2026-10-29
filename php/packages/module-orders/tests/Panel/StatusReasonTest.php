<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Panel;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\OrderStatus;

/**
 * A status that cancels wants its reason in the panel too (§9.1): the dialog's preview still
 * answers the consequences before the reason is typed, the move itself without one is refused
 * under `note` — for one order and for a selection, before any of it moves.
 */
final class StatusReasonTest extends PanelTestCase
{
    private function cancelling(): OrderStatus
    {
        return OrderStatus::query()->where('sets_milestone', Milestone::Cancelled->value)->firstOrFail();
    }

    #[Test]
    public function one_order_moved_into_a_cancelling_status_wants_a_reason(): void
    {
        $order = $this->order();
        $this->signIn();
        $status = $this->cancelling()->id;

        $this->postJson($this->api($order->id).'/status', ['status_id' => $status, 'dry_run' => true])
            ->assertOk()
            ->assertJsonPath('data.milestone.key', 'cancelled');

        $this->postJson($this->api($order->id).'/status', ['status_id' => $status])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->assertNull($order->refresh()->cancelled_at);

        $this->postJson($this->api($order->id).'/status', ['status_id' => $status, 'note' => 'Out of stock'])->assertOk();
        $this->assertNotNull($order->refresh()->cancelled_at);
    }

    #[Test]
    public function a_selection_moved_into_a_cancelling_status_wants_a_reason_before_any_moves(): void
    {
        $orders = [$this->order(), $this->order()];
        $ids = array_map(static fn ($order): int => $order->id, $orders);
        $this->signIn();
        $status = $this->cancelling()->id;

        $this->postJson($this->api('status'), ['ids' => $ids, 'status_id' => $status, 'dry_run' => true])
            ->assertOk()
            ->assertJsonPath('data.cancels', 2);

        $this->postJson($this->api('status'), ['ids' => $ids, 'status_id' => $status])->assertUnprocessable()->assertJsonValidationErrors('note');

        foreach ($orders as $order) {
            $this->assertNull($order->refresh()->cancelled_at);
        }

        $this->postJson($this->api('status'), ['ids' => $ids, 'status_id' => $status, 'note' => 'Duplicate'])->assertOk()->assertJsonPath('data.moved', 2);
    }
}
