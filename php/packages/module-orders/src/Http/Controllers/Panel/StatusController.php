<?php

declare(strict_types=1);

namespace WebxUi\Orders\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\OrderStatuses;
use WebxUi\Orders\Panel\StatusBoard;
use WebxUi\Orders\StatusPlan;
use WebxUi\Orders\StatusRefused;

/**
 * `/api/cms/orders/statuses` (spec §5.6, §11.4), behind `orders.statuses`: both levels of the
 * statuses with the orders in each, and every change of them — written, dragged, deleted softly,
 * brought back. A change that touches what buyers see answers its consequences on `dry_run`; the
 * service's refusal comes back under `status` in the panel's words, and nothing is written.
 *
 * Every write is a call of `OrderStatuses`; an order is never touched here.
 */
final readonly class StatusController
{
    public function __construct(
        private OrderStatuses $statuses,
        private StatusBoard $board,
    ) {}

    public function index(): JsonResponse
    {
        return $this->answer();
    }

    public function storePublic(Request $request): JsonResponse
    {
        $this->statuses->writePublic($this->publicInput($request));

        return $this->answer(201);
    }

    public function updatePublic(Request $request, int $status): JsonResponse
    {
        $this->statuses->writePublic($this->publicInput($request), PublicStatus::query()->findOrFail($status));

        return $this->answer();
    }

    /**
     * A public group deleted — only empty, or with its statuses moved to `move_to` first. A dry
     * run answers the statuses that keep it, and what moving them does.
     */
    public function destroyPublic(Request $request, int $status): JsonResponse
    {
        $validated = $request->validate([
            'move_to' => ['nullable', 'integer'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $group = PublicStatus::query()->findOrFail($status);
        $moveTo = isset($validated['move_to']) ? PublicStatus::withTrashed()->findOrFail((int) $validated['move_to']) : null;

        return $this->planned('delete-public', (bool) ($validated['dry_run'] ?? false), fn (bool $dryRun): StatusPlan => $this->statuses->removePublic($group, $moveTo, $dryRun));
    }

    public function restorePublic(int $status): JsonResponse
    {
        $this->statuses->restorePublic(PublicStatus::onlyTrashed()->findOrFail($status));

        return $this->answer();
    }

    public function store(Request $request): JsonResponse
    {
        return $this->planned('save', false, fn (): StatusPlan => $this->statuses->write($this->input($request)), 201);
    }

    /** An internal status written; moved to another group, the dry run counts whose buyers see it. */
    public function update(Request $request, int $status): JsonResponse
    {
        $found = OrderStatus::withTrashed()->findOrFail($status);

        return $this->planned('save', $request->boolean('dry_run'), fn (bool $dryRun): StatusPlan => $this->statuses->write($this->input($request), $found, $dryRun));
    }

    public function destroy(Request $request, int $status): JsonResponse
    {
        $found = OrderStatus::query()->findOrFail($status);

        return $this->planned('delete', $request->boolean('dry_run'), fn (bool $dryRun): StatusPlan => $this->statuses->remove($found, $dryRun));
    }

    public function restore(int $status): JsonResponse
    {
        $found = OrderStatus::onlyTrashed()->findOrFail($status);

        return $this->planned('restore', false, fn (): StatusPlan => $this->statuses->restore($found));
    }

    /** The order of both levels as dragged; a status dragged into another group is bound to it. */
    public function arrange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'publics' => ['required', 'array'],
            'publics.*' => ['integer'],
            'groups' => ['required', 'array'],
            'groups.*' => ['array'],
            'groups.*.*' => ['integer'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        /** @var list<int> $publics */
        $publics = array_values($validated['publics']);
        /** @var array<int, list<int>> $groups */
        $groups = $validated['groups'];

        return $this->planned('arrange', (bool) ($validated['dry_run'] ?? false), fn (bool $dryRun): StatusPlan => $this->statuses->arrange($publics, $groups, $dryRun));
    }

    /**
     * A dry run answers the consequences; a real change answers the screen afresh. A refusal is
     * a form's error under `status`.
     *
     * @param  'delete'|'delete-public'|'save'|'arrange'|'restore'  $change
     * @param  callable(bool): StatusPlan  $work
     */
    private function planned(string $change, bool $dryRun, callable $work, int $status = 200): JsonResponse
    {
        $locale = app()->getLocale();

        try {
            $plan = $work($dryRun);
        } catch (StatusRefused $refused) {
            throw ValidationException::withMessages(['status' => $this->board->refused($refused, $change, $locale)]);
        }

        return $dryRun ? ApiResponse::data($this->board->consequences($plan, $change, $locale)) : $this->answer($status);
    }

    private function answer(int $status = 200): JsonResponse
    {
        return ApiResponse::data($this->board->describe(app()->getLocale()), $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function input(Request $request): array
    {
        return $request->only(['public_status_id', 'name', 'color', 'sets_milestone', 'is_active']);
    }

    /**
     * @return array<string, mixed>
     */
    private function publicInput(Request $request): array
    {
        return $request->only(['code', 'name', 'description', 'notify_channels']);
    }
}
