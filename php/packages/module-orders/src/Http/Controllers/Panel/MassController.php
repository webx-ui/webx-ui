<?php

declare(strict_types=1);

namespace WebxUi\Orders\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Panel\StatusBatch;
use WebxUi\Orders\Panel\StatusChoices;

/**
 * The selection of the list (spec §11.2): move to a status, cancel with a reason. The batch is
 * {@see StatusBatch}, the one the agent's `orders_set_status` runs too.
 *
 * `dry_run` is the preview the dialog shows before anything happens: how many orders would move,
 * how many buyers would see a new status, how many messages would go.
 */
final readonly class MassController
{
    /** As many as a page of the list can hold. */
    public const int MAX = 200;

    public function __construct(private StatusBatch $batch) {}

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

        // The preview comes before the reason is typed; the real move asks for it.
        $note = OrderController::previewNote($validated['note'] ?? null, $dryRun);
        $tally = $this->batch->move($this->find($validated['ids']), $target, 'panel', $note, $dryRun);

        return ApiResponse::data([...$tally, 'status' => $statuses->find($target->id, app()->getLocale())]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX],
            'ids.*' => ['integer'],
            'reason' => ['required', 'string', 'max:500'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $tally = $this->batch->cancel(
            $this->find($validated['ids']),
            'panel',
            (string) $validated['reason'],
            (bool) ($validated['dry_run'] ?? false),
        );

        // The status the cancelled come into (decision 88), named as the move's answer names it.
        $status = $tally['status_id'] !== null ? (new StatusChoices)->find($tally['status_id'], app()->getLocale()) : null;

        return ApiResponse::data([...$tally, 'status' => $status]);
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
