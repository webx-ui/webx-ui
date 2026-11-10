<?php

declare(strict_types=1);

namespace WebxUi\Orders\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Orders\FieldPlan;
use WebxUi\Orders\FieldRefused;
use WebxUi\Orders\Models\OrderField;
use WebxUi\Orders\OrderFields;
use WebxUi\Orders\Panel\FieldBoard;

/**
 * `/api/cms/orders/checkout-fields` (WEBX_UI_ORDER_FIELDS.md §7.3), behind
 * `orders.checkout.settings` and only while the checkout is on: the extra fields of the checkout,
 * written, dragged and deleted.
 *
 * Every write is a call of `OrderFields`, the one the agent's tools make too. A dry run answers
 * what the buyer will see because of the change; a real one answers the list afresh. A refusal is
 * a form's error under the input it is about — `key`, `options`, `parent` — so the dialog shows it
 * there, and nothing is written.
 */
final readonly class FieldController
{
    public function __construct(
        private OrderFields $fields,
        private FieldBoard $board,
    ) {}

    public function index(): JsonResponse
    {
        return $this->answer();
    }

    public function store(Request $request): JsonResponse
    {
        $input = $this->input($request);

        return $this->planned($request->boolean('dry_run'), fn (bool $dryRun): FieldPlan => $this->fields->write($input, null, $dryRun), $input, 201);
    }

    public function update(Request $request, int $field): JsonResponse
    {
        $found = OrderField::query()->findOrFail($field);
        $input = $this->input($request);

        return $this->planned($request->boolean('dry_run'), fn (bool $dryRun): FieldPlan => $this->fields->write($input, $found, $dryRun), $input);
    }

    public function destroy(Request $request, int $field): JsonResponse
    {
        $found = OrderField::query()->findOrFail($field);

        return $this->planned($request->boolean('dry_run'), fn (bool $dryRun): FieldPlan => $this->fields->remove($found, $dryRun));
    }

    /** The order as dragged: every key once, places in their order, a checkbox's fields after it. */
    public function arrange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keys' => ['required', 'array'],
            'keys.*' => ['string'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        /** @var list<string> $keys */
        $keys = array_values($validated['keys']);

        return $this->planned((bool) ($validated['dry_run'] ?? false), fn (bool $dryRun): FieldPlan => $this->fields->arrange($keys, $dryRun));
    }

    /**
     * @param  callable(bool): FieldPlan  $work
     * @param  array<string, mixed>  $input
     */
    private function planned(bool $dryRun, callable $work, array $input = [], int $status = 200): JsonResponse
    {
        $locale = app()->getLocale();
        $place = is_string($input['place'] ?? null) && $input['place'] !== '' ? $input['place'] : null;

        try {
            $plan = $work($dryRun);
        } catch (FieldRefused $refused) {
            throw ValidationException::withMessages([$refused->plan->field ?? 'key' => $this->board->refused($refused, $locale)]);
        }

        return $dryRun ? ApiResponse::data($this->board->consequences($plan, $locale, $place)) : $this->answer($status);
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
        return $request->only(['key', 'type', 'place', 'label', 'hint', 'options', 'required', 'active', 'parent']);
    }
}
