<?php

declare(strict_types=1);

namespace WebxUi\Orders\Panel;

use Illuminate\Contracts\Validation\Factory as Validation;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Orders;
use WebxUi\Orders\Refusal;
use WebxUi\Orders\StatusMove;

/**
 * A selection of orders moved to a status or cancelled (spec §11.2, §12) — the panel's selection
 * and the agent's `numbers` alike, so the preview of one is the preview of the other. Each order is
 * its own call of `Orders` — its own lock, its own events — and one that refuses does not stop the
 * rest: the answer says how many moved and which refused, and why.
 */
final readonly class StatusBatch
{
    public function __construct(
        private Orders $orders,
        private Validation $validation,
    ) {}

    /**
     * @param  list<Order>  $orders
     * @return array{orders: int, moved: int, unchanged: int, public_changed: int, notifications: int, milestones: int, cancels: int, refused: list<array{id: int, number: string|null, reason: string, message: string}>, dry_run: bool}
     */
    public function move(array $orders, OrderStatus $target, string $source, ?string $note, bool $dryRun): array
    {
        // A status that cancels wants its reason (§9.1): asked once for the whole selection,
        // before any order moves — not refused order by order halfway through it.
        if ($target->sets_milestone === Milestone::Cancelled) {
            $this->validation->make(['note' => $note], ['note' => ['required', 'string']])->validate();
        }

        $tally = ['orders' => 0, 'moved' => 0, 'unchanged' => 0, 'public_changed' => 0, 'notifications' => 0, 'milestones' => 0, 'cancels' => 0, 'refused' => []];

        foreach ($orders as $order) {
            $tally['orders']++;

            try {
                $move = $this->orders->moveTo($order, $target, $source, $note, $dryRun);
            } catch (Refusal $refused) {
                $tally['refused'][] = self::refusal($order, $refused->reason);

                continue;
            }

            // A dry run answers a refusal instead of throwing it.
            if ($move->refusal !== null) {
                $tally['refused'][] = self::refusal($order, $move->refusal);

                continue;
            }

            self::count($tally, $move);
        }

        return [...$tally, 'dry_run' => $dryRun];
    }

    /**
     * @param  list<Order>  $orders
     * @return array{orders: int, cancelled: int, unchanged: int, paid: int, refused: list<array{id: int, number: string|null, reason: string, message: string}>, dry_run: bool}
     */
    public function cancel(array $orders, string $source, string $reason, bool $dryRun): array
    {
        // A cancellation is not refused — a cancelled order is silence — so `refused` stays empty;
        // the key keeps the answer the shape of the move's.
        $tally = ['orders' => 0, 'cancelled' => 0, 'unchanged' => 0, 'paid' => 0, 'refused' => []];

        foreach ($orders as $order) {
            $tally['orders']++;
            $mark = $this->orders->cancel($order, $source, $reason, $dryRun);

            $mark->marked() ? $tally['cancelled']++ : $tally['unchanged']++;

            if ($mark->marked() && $mark->wasPaid) {
                $tally['paid']++;
            }
        }

        return [...$tally, 'dry_run' => $dryRun];
    }

    /**
     * @param  array{orders: int, moved: int, unchanged: int, public_changed: int, notifications: int, milestones: int, cancels: int, refused: list<array<string, mixed>>}  $tally
     */
    private static function count(array &$tally, StatusMove $move): void
    {
        if (! $move->moved) {
            $tally['unchanged']++;

            return;
        }

        $tally['moved']++;
        $tally['public_changed'] += $move->publicChanged ? 1 : 0;
        $tally['notifications'] += count($move->notifications);
        $tally['milestones'] += $move->milestone !== null ? 1 : 0;
        $tally['cancels'] += $move->milestone === Milestone::Cancelled ? 1 : 0;
    }

    /**
     * @return array{id: int, number: string|null, reason: string, message: string}
     */
    private static function refusal(Order $order, string $reason): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'reason' => $reason,
            'message' => (string) __('webx-orders::panel.refusals.'.$reason),
        ];
    }
}
