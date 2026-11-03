<?php

declare(strict_types=1);

namespace WebxUi\Payments\Panel;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\PaymentStatus;

/**
 * The journal of attempts' filters (spec §13.2) for the panel's list and the agent's
 * `payments_list` alike: the tab («In progress», «Failed», «Problems»), a method, a search by
 * uuid, the gateway's id or the order's number, and a period.
 */
final class AttemptQuery
{
    /** The tabs of the list: which statuses each shows; `problems` — any with a problem. */
    public const array TABS = [
        'pending' => [PaymentStatus::Created, PaymentStatus::Pending, PaymentStatus::Authorized],
        'failed' => [PaymentStatus::Failed, PaymentStatus::Cancelled, PaymentStatus::Expired],
    ];

    /**
     * @param  Builder<Payment>  $query
     * @param  array<string, mixed>  $params
     * @return Builder<Payment>
     */
    public static function filter(Builder $query, array $params): Builder
    {
        $tab = $params['tab'] ?? null;

        if (is_string($tab) && isset(self::TABS[$tab])) {
            $query->whereIn('status', array_map(static fn (PaymentStatus $status): string => $status->value, self::TABS[$tab]));
        } elseif ($tab === 'problems') {
            $query->whereNotNull('problem');
        }

        $status = $params['status'] ?? null;

        if (is_string($status) && PaymentStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }

        $method = $params['method'] ?? null;

        if (is_string($method) && $method !== '') {
            $query->where('method_key', $method);
        }

        $search = is_string($params['search'] ?? null) ? trim($params['search']) : '';

        if ($search !== '') {
            $query->where(static function (Builder $q) use ($search): void {
                $q->where('uuid', $search)
                    ->orWhere('gateway_ref', $search)
                    ->orWhereHas('order', static fn (Builder $order): Builder => $order->where('number', $search));
            });
        }

        foreach (['from' => '>=', 'to' => '<='] as $name => $operator) {
            $value = $params[$name] ?? null;

            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                $day = CarbonImmutable::parse($value);
                $query->where('created_at', $operator, $operator === '>=' ? $day->startOfDay() : $day->endOfDay());
            }
        }

        return $query;
    }
}
