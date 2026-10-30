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
        return $this->pick($awaitPayment, $this->active());
    }

    /**
     * Both of them — for an order that does not await payment and for one that does — out of one
     * query: the screens of the statuses ask for both at once.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        $active = $this->active();

        if ($active === []) {
            return [];
        }

        return array_values(array_unique([$this->pick(false, $active)->id, $this->pick(true, $active)->id]));
    }

    /**
     * @param  list<OrderStatus>  $active
     */
    private function pick(bool $awaitPayment, array $active): OrderStatus
    {
        if ($active === []) {
            throw new LogicException('Orders: there is no active status for a new order to come into.');
        }

        $chosen = $this->settings->get($awaitPayment ? 'orders.initial_status_awaiting_payment' : 'orders.initial_status');

        foreach ($active as $status) {
            if (is_numeric($chosen) && $status->id === (int) $chosen) {
                return $status;
            }
        }

        $group = array_values(array_filter($active, static fn (OrderStatus $status): bool => $status->public_status_id === $active[0]->public_status_id));

        return $awaitPayment ? ($group[1] ?? $group[0]) : $group[0];
    }

    /**
     * The active statuses of the live groups in the order the buyer sees them — one query, a dozen
     * rows.
     *
     * @return list<OrderStatus>
     */
    private function active(): array
    {
        /** @var list<OrderStatus> */
        return OrderStatus::query()
            ->select('order_statuses.*')
            ->join('order_public_statuses as groups', 'groups.id', '=', 'order_statuses.public_status_id')
            ->whereNull('groups.deleted_at')
            ->where('order_statuses.is_active', true)
            ->orderBy('groups.position')
            ->orderBy('order_statuses.position')
            ->orderBy('order_statuses.id')
            ->get()
            ->all();
    }
}
