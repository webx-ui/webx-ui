<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use LogicException;
use WebxUi\Orders\Models\OrderStatus;

/**
 * The internal status a new order comes into (§5.2): the one the settings name — active and not
 * deleted — else the first of the first public group, and for an order that awaits payment the
 * one after it in that group, which is how the set a site starts with has «New» and «Awaiting
 * payment».
 */
final class InitialStatuses
{
    public function __construct(private readonly OrderSettings $settings) {}

    public function for(bool $awaitPayment): OrderStatus
    {
        $chosen = $this->settings->get($awaitPayment ? 'orders.initial_status_awaiting_payment' : 'orders.initial_status');
        $statuses = $this->active(is_numeric($chosen) ? (int) $chosen : null);

        if ($statuses === []) {
            throw new LogicException('Orders: there is no active status for a new order to come into.');
        }

        if (is_numeric($chosen) && $statuses[0]->id === (int) $chosen) {
            return $statuses[0];
        }

        $first = array_values(array_filter($statuses, static fn (OrderStatus $status): bool => $status->id !== (int) $chosen));
        $group = array_values(array_filter($first, static fn (OrderStatus $status): bool => $status->public_status_id === $first[0]->public_status_id));

        return $awaitPayment ? ($group[1] ?? $group[0]) : $group[0];
    }

    /**
     * The chosen status, when it is active, ahead of the active ones in the order the buyer sees
     * the groups — one query.
     *
     * @return list<OrderStatus>
     */
    private function active(?int $chosen): array
    {
        $query = OrderStatus::query()
            ->select('order_statuses.*')
            ->join('order_public_statuses as groups', 'groups.id', '=', 'order_statuses.public_status_id')
            ->whereNull('groups.deleted_at')
            ->where('order_statuses.is_active', true);

        if ($chosen !== null) {
            $query->orderByRaw('case when order_statuses.id = ? then 0 else 1 end', [$chosen]);
        }

        /** @var list<OrderStatus> */
        return $query
            ->orderBy('groups.position')
            ->orderBy('order_statuses.position')
            ->orderBy('order_statuses.id')
            ->limit(3)
            ->get()
            ->all();
    }
}
