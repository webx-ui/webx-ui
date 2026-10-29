<?php

declare(strict_types=1);

namespace WebxUi\Orders\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Orders;
use WebxUi\Orders\Panel\StatusChoices;
use WebxUi\Orders\Refusal;
use WebxUi\Orders\StatusMove;

/**
 * The selection of the list (spec §11.2): move to a status, cancel with a reason. Each order is
 * its own call of `Orders` — its own lock, its own events — and one that refuses does not stop
 * the rest: the answer says how many moved and which refused, and why.
 *
 * `dry_run` is the preview the dialog shows before anything happens: how many orders would move,
 * how many buyers would see a new status, how many messages would go.
 */
final readonly class MassController
{
    /** As many as a page of the list can hold. */
    private const int MAX = 200;

    public function __construct(private Orders $orders) {}

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX],
            'ids.*' => ['integer'],
            'status_id' => ['required', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $dryRun = (bool) ($validated['dry_run'] ?? false);
        $statuses = new StatusChoices;
        $target = $statuses->model((int) $validated['status_id']) ?? throw ValidationException::withMessages([
            'status_id' => (string) __('webx-orders::panel.errors.no-status'),
        ]);

        $tally = ['orders' => 0, 'moved' => 0, 'unchanged' => 0, 'public_changed' => 0, 'notifications' => 0, 'milestones' => 0, 'refused' => []];

        foreach ($this->find($validated['ids']) as $order) {
            $tally['orders']++;

            try {
                $move = $this->orders->moveTo($order, $target, 'panel', $validated['note'] ?? null, $dryRun);
            } catch (Refusal $refused) {
                $tally['refused'][] = $this->refusal($order, $refused->reason);

                continue;
            }

            // A dry run answers a refusal instead of throwing it.
            if ($move->refusal !== null) {
                $tally['refused'][] = $this->refusal($order, $move->refusal);

                continue;
            }

            $this->count($tally, $move);
        }

        return ApiResponse::data([...$tally, 'dry_run' => $dryRun, 'status' => $statuses->find($target->id, app()->getLocale())]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX],
            'ids.*' => ['integer'],
            'reason' => ['required', 'string', 'max:500'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $dryRun = (bool) ($validated['dry_run'] ?? false);
        $tally = ['orders' => 0, 'cancelled' => 0, 'unchanged' => 0, 'paid' => 0, 'refused' => []];

        foreach ($this->find($validated['ids']) as $order) {
            $tally['orders']++;

            try {
                $mark = $this->orders->cancel($order, 'panel', (string) $validated['reason'], $dryRun);
            } catch (Refusal $refused) {
                $tally['refused'][] = $this->refusal($order, $refused->reason);

                continue;
            }

            $mark->marked() ? $tally['cancelled']++ : $tally['unchanged']++;

            if ($mark->marked() && $mark->wasPaid) {
                $tally['paid']++;
            }
        }

        return ApiResponse::data([...$tally, 'dry_run' => $dryRun]);
    }

    /**
     * @param  array{orders: int, moved: int, unchanged: int, public_changed: int, notifications: int, milestones: int, refused: list<array<string, mixed>>}  $tally
     */
    private function count(array &$tally, StatusMove $move): void
    {
        if (! $move->moved) {
            $tally['unchanged']++;

            return;
        }

        $tally['moved']++;
        $tally['public_changed'] += $move->publicChanged ? 1 : 0;
        $tally['notifications'] += count($move->notifications);
        $tally['milestones'] += $move->milestone !== null ? 1 : 0;
    }

    /**
     * @return array{id: int, number: string|null, reason: string, message: string}
     */
    private function refusal(Order $order, string $reason): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'reason' => $reason,
            'message' => (string) __('webx-orders::panel.refusals.'.$reason),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<Order>
     */
    private function find(array $ids): array
    {
        return Order::query()->whereKey(array_map(intval(...), $ids))->orderBy('id')->get()->all();
    }
}
