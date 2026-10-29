<?php

declare(strict_types=1);

namespace WebxUi\Orders\Http\Controllers\Panel;

use Illuminate\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Visitors\Visitor;
use WebxUi\Admin\Visitors\Visitors;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Orders;
use WebxUi\Orders\Panel\OrderCard;
use WebxUi\Orders\Panel\StatusChoices;
use WebxUi\Orders\VisitorLink;
use WebxUi\Orders\VisitorRefused;
use WebxUi\Users\UserVisitors;

/**
 * The visitor of an order in the card (spec §13.3), registered only with `webx-ui/module-users`:
 * the search of the customers to link to — the order's own address first —, the link and the
 * unlink. Both answer a dry run first, which the dialog shows: whom it goes to, whom it belonged to.
 *
 * Every change is a call of `Orders` (decision 18); `BoundariesTest` holds this class to it.
 */
final readonly class VisitorController
{
    public function __construct(
        private Orders $orders,
        private OrderCard $card,
    ) {}

    /** Customers by a part of the address or the name; `users.view` stands in front as well. */
    public function search(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:190']]);
        $found = self::find($order);
        $term = trim((string) ($validated['search'] ?? ''));
        $visitors = Container::getInstance()->make(Visitors::class);
        $rows = $visitors instanceof UserVisitors && $term !== '' ? $visitors->search($term, 10) : [];
        $email = mb_strtolower(trim((string) $found->customer_email));
        // The account with the order's own address first (§13.3): most likely the buyer.
        usort($rows, static fn (Visitor $a, Visitor $b): int => (int) ($b->email === $email) <=> (int) ($a->email === $email));

        return ApiResponse::data(array_map(static fn (Visitor $visitor): array => [
            'id' => $visitor->id,
            'name' => $visitor->name(),
            'email' => $visitor->email,
            'phone' => $visitor->phone,
            'current' => $visitor->id === $found->user_id,
            'same_email' => $email !== '' && $visitor->email === $email,
        ], $rows));
    }

    public function link(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $found = self::find($order);

        try {
            $link = $this->orders->linkVisitor($found, (int) $validated['user_id'], 'panel', (bool) ($validated['dry_run'] ?? false));
        } catch (VisitorRefused $refused) {
            throw ValidationException::withMessages(['user_id' => (string) __('webx-orders::panel.refusals.'.$refused->reason)]);
        }

        return $this->answer($request, $found, $link);
    }

    public function unlink(Request $request, int $order): JsonResponse
    {
        $validated = $request->validate(['dry_run' => ['nullable', 'boolean']]);
        $found = self::find($order);

        return $this->answer($request, $found, $this->orders->unlinkVisitor($found, 'panel', (bool) ($validated['dry_run'] ?? false)));
    }

    /** A dry run answers what it does; the real call answers the card. */
    private function answer(Request $request, Order $order, VisitorLink $link): JsonResponse
    {
        if ($link->dryRun) {
            return ApiResponse::data($link->toArray());
        }

        $reader = $request->user();

        return ApiResponse::data($this->card->describe(
            $order->refresh(),
            new StatusChoices,
            app()->getLocale(),
            static fn (string $permission): bool => $reader instanceof HasPermissions && $reader->hasPermission($permission),
            $reader,
        ));
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
