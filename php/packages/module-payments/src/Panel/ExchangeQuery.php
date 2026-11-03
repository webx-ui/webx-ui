<?php

declare(strict_types=1);

namespace WebxUi\Payments\Panel;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use WebxUi\Orders\Models\Order;
use WebxUi\Payments\Exchange\Direction;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Gateways;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Models\PaymentGatewayEvent;
use WebxUi\Payments\Models\PaymentMethod;
use WebxUi\Payments\Words;

/**
 * The exchange journal's list (spec §13.2, §20.7) for the panel's screen and the agent's
 * `payments_exchange_list` alike: the filters — gateway, method, attempt (uuid) or order (number,
 * or the id the tab links with), kind, direction, bad signatures, errors, period — and a keyset
 * page newest first. A line never reads a body column ({@see EventRows::LINE}).
 */
final class ExchangeQuery
{
    public const int PER_PAGE = 50;

    /**
     * One page: the events older than `before`, newest first, and the id to ask the next page
     * with. Nothing — the attempt or the order asked for does not exist.
     *
     * @param  array<string, mixed>  $params
     * @return array{rows: Collection<int, PaymentGatewayEvent>, next: int|null}
     */
    public static function page(array $params, int $perPage = self::PER_PAGE): array
    {
        $query = EventRows::query();

        if (! self::filter($query, $params)) {
            return ['rows' => new Collection, 'next' => null];
        }

        $before = self::int($params['before'] ?? null);
        $perPage = max(10, min(100, $perPage));

        $rows = $query
            ->when($before > 0, static fn (Builder $q): Builder => $q->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit($perPage + 1)
            ->get();

        $page = $rows->take($perPage)->values();

        return ['rows' => $page, 'next' => $rows->count() > $perPage ? (int) $page->last()?->id : null];
    }

    /**
     * The filters of §20.7; false — the attempt or the order asked for does not exist, so nothing
     * can match.
     *
     * @param  Builder<PaymentGatewayEvent>  $query
     * @param  array<string, mixed>  $params
     */
    public static function filter(Builder $query, array $params): bool
    {
        foreach (['gateway' => 'gateway', 'method_key' => 'method', 'kind' => 'kind', 'direction' => 'direction'] as $column => $name) {
            $value = self::text($params[$name] ?? null);

            if ($value !== null) {
                $query->where($column, $value);
            }
        }

        $payment = self::text($params['payment'] ?? null);

        if ($payment !== null) {
            $id = Payment::query()->where('uuid', $payment)->value('id');

            if ($id === null) {
                return false;
            }

            $query->where('payment_id', $id);
        }

        // The tab of an order links here by its id; a person types the number.
        $orderId = $params['order_id'] ?? null;
        $order = self::text($params['order'] ?? null);

        if (is_int($orderId) || (is_string($orderId) && ctype_digit($orderId))) {
            $query->where('order_id', (int) $orderId);
        } elseif ($order !== null) {
            $id = Order::query()->where('number', $order)->value('id');

            if ($id === null) {
                return false;
            }

            $query->where('order_id', $id);
        }

        if (self::flag($params['signature'] ?? null)) {
            $query->where('signature', 'invalid');
        }

        if (self::flag($params['errors'] ?? null)) {
            $query->where(static function (Builder $q): void {
                $q->whereIn('outcome', EventRows::ERRORS)
                    ->orWhere('response_status', '>=', 400)
                    ->orWhereNotNull('transport_error')
                    ->orWhere('signature', 'invalid');
            });
        }

        foreach (['from' => '>=', 'to' => '<='] as $name => $operator) {
            $value = self::text($params[$name] ?? null);

            if ($value !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
                $day = CarbonImmutable::parse($value);
                $query->where('created_at', $operator, $operator === '>=' ? $day->startOfDay() : $day->endOfDay());
            }
        }

        return true;
    }

    /** @return array<string, mixed> what the filters offer */
    public static function filters(Gateways $gateways, string $locale): array
    {
        return [
            'gateways' => array_values(array_map(static fn ($gateway): array => ['value' => $gateway->key(), 'label' => $gateway->title($locale)], $gateways->all())),
            'methods' => PaymentMethod::query()->orderBy('position')->get(['key', 'title'])
                ->map(static fn (PaymentMethod $method): array => ['value' => $method->key, 'label' => Words::pick($method->title, $locale) ?? $method->key])
                ->values()
                ->all(),
            'kinds' => array_map(static fn (ExchangeKind $kind): string => $kind->value, ExchangeKind::cases()),
            'directions' => array_map(static fn (Direction $direction): string => $direction->value, Direction::cases()),
        ];
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** `1`, `true`, `on` — as a query string says it — or a JSON true. */
    private static function flag(mixed $value): bool
    {
        return $value === true || (is_string($value) && filter_var($value, FILTER_VALIDATE_BOOLEAN)) || $value === 1;
    }

    private static function int(mixed $value): int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : 0;
    }
}
