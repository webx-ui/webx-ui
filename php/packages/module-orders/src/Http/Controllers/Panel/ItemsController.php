<?php

declare(strict_types=1);

namespace WebxUi\Orders\Http\Controllers\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Cart\Money;
use WebxUi\Orders\CartNotOrderable;
use WebxUi\Orders\Checkout\CheckoutPreview;
use WebxUi\Orders\ItemsEdit;
use WebxUi\Orders\ItemsEditRefused;
use WebxUi\Orders\ItemsEditResult;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrderRefused;
use WebxUi\Orders\Orders;
use WebxUi\Orders\Panel\Consequences;
use WebxUi\Orders\Panel\OrderCard;
use WebxUi\Orders\Panel\StatusChoices;

/**
 * «Change the lines» of the card (spec §42.1): the editor's live totals are the service's own dry
 * run, the save is `Orders::editItems()` with `source = panel` — this class reads the form and
 * turns the answers and the refusals into the panel's words; the edit is the service's.
 *
 * The products are found by the route of «New order» (`new/products`): the same providers, the
 * same choices.
 */
final readonly class ItemsController
{
    public function __construct(
        private Orders $orders,
        private OrderCard $card,
        private Consequences $consequences,
        private Money $money,
    ) {}

    /**
     * What the edit would do: the lines «before → after», the totals, the balance after it and the
     * warnings.
     */
    public function preview(Request $request, int $order): JsonResponse
    {
        $found = self::find($order);
        $edit = $this->input($request);

        try {
            $result = $this->orders->editItems($found, $edit, dryRun: true);
        } catch (CartNotOrderable $refused) {
            throw ValidationException::withMessages(['add' => CheckoutPreview::notOrderable($refused)]);
        }

        return ApiResponse::data($this->consequences->itemsEdit($result, $found->currency, $this->money));
    }

    /**
     * The edit written. The answer is the card, and the edit's own answer beside it — what the
     * balance now says decides what the panel offers next (§42.3). An edit saved meanwhile is a
     * refusal under `revision`: the editor opens the order again.
     */
    public function update(Request $request, int $order): JsonResponse
    {
        $found = self::find($order);
        $edit = $this->input($request);

        try {
            $result = $this->orders->editItems($found, $edit);
        } catch (ItemsEditRefused $refused) {
            throw ValidationException::withMessages([
                $refused->result->field ?? 'items' => (string) __('webx-orders::panel.items-refusals.'.$refused->reason),
            ]);
        } catch (CartNotOrderable $refused) {
            throw ValidationException::withMessages(['add' => CheckoutPreview::notOrderable($refused)]);
        } catch (OrderRefused $refused) {
            throw ValidationException::withMessages(['items' => $refused->getMessage()]);
        }

        $reader = $request->user();

        return ApiResponse::data([
            ...$this->card->describe(
                $found->refresh(),
                new StatusChoices,
                app()->getLocale(),
                static fn (string $permission): bool => $reader instanceof HasPermissions && $reader->hasPermission($permission),
                $reader,
            ),
            'edit' => $this->consequences->itemsEdit($result, $found->currency, $this->money),
        ]);
    }

    private function input(Request $request): ItemsEdit
    {
        $validated = $request->validate([
            'revision' => ['required', 'integer', 'min:0'],
            'add' => ['nullable', 'array', 'list'],
            'add.*' => ['array:type,ref,qty,options'],
            'add.*.type' => ['required', 'string', 'max:64'],
            'add.*.ref' => ['required', 'integer'],
            'add.*.qty' => ['nullable', 'integer', 'min:1'],
            'add.*.options' => ['nullable', 'array'],
            'qty' => ['nullable', 'array'],
            'qty.*' => ['integer', 'min:0'],
            'remove' => ['nullable', 'array', 'list'],
            'remove.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:500'],
            'message' => ['nullable', 'string', 'max:1000'],
            'notify' => ['nullable', 'boolean'],
        ]);

        $qty = [];

        foreach ((array) ($validated['qty'] ?? []) as $id => $value) {
            if (! ctype_digit((string) $id)) {
                throw ValidationException::withMessages(["qty.{$id}" => (string) __('webx-orders::panel.items-refusals.'.ItemsEditResult::FOREIGN)]);
            }

            $qty[(int) $id] = (int) $value;
        }

        return new ItemsEdit(
            revision: (int) $validated['revision'],
            add: array_map(static fn (array $line): array => array_filter($line, static fn (mixed $value): bool => $value !== null), $validated['add'] ?? []),
            qty: $qty,
            remove: array_map(intval(...), $validated['remove'] ?? []),
            reason: $validated['reason'] ?? null,
            message: $validated['message'] ?? null,
            notify: (bool) ($validated['notify'] ?? true),
            source: 'panel',
        );
    }

    private static function find(int $id): Order
    {
        $order = Order::query()->find($id);

        if (! $order instanceof Order) {
            abort(404, (string) __('webx-orders::panel.errors.not-found', ['id' => $id]));
        }

        return $order;
    }
}
