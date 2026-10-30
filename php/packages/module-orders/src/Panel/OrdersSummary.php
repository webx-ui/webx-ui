<?php

declare(strict_types=1);

namespace WebxUi\Orders\Panel;

use Illuminate\Support\Carbon;
use WebxUi\Cart\Money;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrderStatuses;

/**
 * The orders at a glance (spec §11.2, §12): one count for the agent's `orders_summary` and for
 * the figures over the panel's list, so the two never tell different stories.
 *
 * Counted by `GROUP BY` and conditional sums over the indexed columns (`status_id`,
 * `awaiting_payment`, `placed_at`) — no order is loaded, and the number of queries does not grow
 * with the orders. Money is per currency: a shop that sells in two never adds them up.
 */
final readonly class OrdersSummary
{
    /** The periods, in days back from now. */
    public const array PERIODS = [1, 7, 30];

    /** How many days the line of orders by day covers. */
    public const int DAYS = 30;

    public function __construct(
        private OrderStatuses $statuses,
        private Money $money,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function describe(string $locale): array
    {
        $counts = $this->statuses->counts();
        $initial = $this->statuses->initial();
        $public = [];
        $internal = [];

        foreach ((new StatusChoices)->grouped($locale) as $group) {
            $inGroup = 0;

            foreach ($group['statuses'] as $status) {
                $orders = $counts[$status['id']] ?? 0;
                $inGroup += $orders;

                if ($orders > 0 || ! $status['deleted']) {
                    $internal[] = ['id' => $status['id'], 'name' => $status['name'], 'public' => $group['code'], 'deleted' => $status['deleted'], 'orders' => $orders];
                }
            }

            $public[] = ['code' => $group['code'], 'name' => $group['name'], 'deleted' => $group['deleted'], 'orders' => $inGroup];
        }

        // The flags and what awaits payment by currency, in one pass: grouped by the currency,
        // summed back up for the counts that do not care about money.
        $waiting = $initial === [] ? '0 = 1' : 'status_id in ('.implode(',', array_fill(0, count($initial), '?')).')';
        $flags = Order::query()->toBase()->selectRaw(
            'currency,'
            .' sum(case when awaiting_payment = ? and cancelled_at is null then 1 else 0 end) as awaiting,'
            .' sum(case when awaiting_payment = ? and cancelled_at is null then payable else 0 end) as awaiting_amount,'
            .' sum(case when awaiting_payment = ? and cancelled_at is null and payment_failed_at is not null then 1 else 0 end) as failed,'
            ." sum(case when paid_at is not null and cancelled_at is null and {$waiting} then 1 else 0 end) as paid_waiting",
            [true, true, true, ...$initial],
        )->groupBy('currency')->orderBy('currency')->get();

        return [
            'public' => $public,
            'internal' => $internal,
            'awaiting_payment' => (int) $flags->sum('awaiting'),
            'payment_failed' => (int) $flags->sum('failed'),
            'paid_status_waits' => (int) $flags->sum('paid_waiting'),
            // What waits for payment now, by currency: how many orders and what is left to pay on
            // them — not a period, the money is waited for today whenever it was ordered.
            'awaiting_by_currency' => $flags
                ->filter(static fn (object $row): bool => (int) $row->awaiting > 0)
                ->map(fn (object $row): array => [
                    'currency' => (string) $row->currency,
                    'orders' => (int) $row->awaiting,
                    ...$this->amount('amount', (int) $row->awaiting_amount, (string) $row->currency),
                ])->values()->all(),
            'periods' => $this->periods(),
            'daily' => $this->daily(),
        ];
    }

    /**
     * For the last 1, 7 and 30 days, by currency: orders placed, paid, the revenue — what was paid
     * less what was given back — and the average of a paid order.
     *
     * @return list<array{days: int, by_currency: list<array<string, mixed>>}>
     */
    private function periods(): array
    {
        $now = Carbon::now();
        $select = ['currency'];
        $bindings = [];
        $since = [];

        foreach (self::PERIODS as $days) {
            $since[$days] = $from = $now->copy()->subDays($days);
            $select[] = "sum(case when placed_at >= ? then 1 else 0 end) as placed_{$days}";
            $select[] = "sum(case when placed_at >= ? and paid_at is not null then 1 else 0 end) as paid_{$days}";
            $select[] = "sum(case when placed_at >= ? and paid_at is not null then payable - refunded_total else 0 end) as revenue_{$days}";
            array_push($bindings, $from, $from, $from);
        }

        $rows = Order::query()->toBase()
            ->selectRaw(implode(', ', $select), $bindings)
            ->where('placed_at', '>=', $since[max(self::PERIODS)])
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $periods = [];

        foreach (self::PERIODS as $days) {
            $periods[] = [
                'days' => $days,
                'by_currency' => $rows->map(function (object $row) use ($days): array {
                    $currency = (string) $row->currency;
                    $paid = (int) $row->{"paid_{$days}"};
                    $revenue = (int) $row->{"revenue_{$days}"};

                    return [
                        'currency' => $currency,
                        'placed' => (int) $row->{"placed_{$days}"},
                        'paid' => $paid,
                        ...$this->amount('revenue', $revenue, $currency),
                        ...$this->amount('average', $paid > 0 ? intdiv($revenue, $paid) : 0, $currency),
                    ];
                })->values()->all(),
            ];
        }

        return $periods;
    }

    /**
     * Orders placed each day of the last thirty, today last, a day without orders as a zero — the
     * line over the list.
     *
     * @return list<array{date: string, orders: int}>
     */
    private function daily(): array
    {
        $today = Carbon::now()->startOfDay();
        $first = $today->copy()->subDays(self::DAYS - 1);
        $counts = Order::query()->toBase()
            ->selectRaw('date(placed_at) as day, count(*) as orders')
            ->where('placed_at', '>=', $first)
            ->groupByRaw('date(placed_at)')
            ->pluck('orders', 'day')
            ->mapWithKeys(static fn (mixed $orders, mixed $day): array => [substr((string) $day, 0, 10) => (int) $orders])
            ->all();

        $days = [];

        for ($day = $first->copy(); $day->lte($today); $day->addDay()) {
            $key = $day->toDateString();
            $days[] = ['date' => $key, 'orders' => $counts[$key] ?? 0];
        }

        return $days;
    }

    /**
     * @return array<string, int|string>
     */
    private function amount(string $key, int $minor, string $currency): array
    {
        return [$key => $minor, $key.'_formatted' => $this->money->format($minor, $currency)];
    }
}
