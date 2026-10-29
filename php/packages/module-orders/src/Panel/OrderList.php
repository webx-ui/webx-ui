<?php

declare(strict_types=1);

namespace WebxUi\Orders\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use WebxUi\Cart\Money;
use WebxUi\Orders\Blocks\OrderBlocks;
use WebxUi\Orders\InitialStatuses;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderRefund;

/**
 * The query behind «Orders» (spec §11.2): searched by number, phone in any spelling, e-mail and
 * name; filtered by both statuses, the milestones, the period, a guest or a visitor and the
 * methods of the blocks; four ready views over all of it. A page is one query, its statuses two
 * whatever the number of rows, and the preview of what was bought rides on the same query as a
 * subquery of the first line — never a query a row (§16).
 */
final class OrderList
{
    /** What the list may be sorted by, newest first unless `-` says otherwise. */
    public const array SORTS = ['placed_at', 'total', 'number'];

    /** The tabs over the list (§11.2). */
    public const array VIEWS = ['new', 'awaiting', 'failed', 'all'];

    public function __construct(
        private readonly InitialStatuses $initial,
        private readonly OrderBlocks $blocks,
        private readonly Money $money,
    ) {}

    /**
     * The shape of the filters, as the panel and the agent send them — one set of rules for both.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'view' => ['nullable', Rule::in(self::VIEWS)],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'public' => ['nullable', 'string', 'max:255'],
            'paid' => ['nullable', 'boolean'],
            'received' => ['nullable', 'boolean'],
            'cancelled' => ['nullable', 'boolean'],
            'refunded' => ['nullable', 'boolean'],
            'awaiting' => ['nullable', 'boolean'],
            'failed' => ['nullable', 'boolean'],
            'visitor' => ['nullable', Rule::in(['guest', 'visitor'])],
            // One visitor's orders: the tab «Orders» of the visitor's card (§13.2).
            'user' => ['nullable', 'integer', 'min:1'],
            'delivery' => ['nullable', 'string', 'max:64'],
            'payment' => ['nullable', 'string', 'max:64'],
            // Where the order came from (§29): `checkout`, `quick`, `api`, `mcp`…
            'source' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'sort' => ['nullable', 'string', 'max:32'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function page(array $filters, int $perPage, int $page = 1): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage, page: $page);
    }

    /**
     * How many orders each tab holds under the same search and filters — one query.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function counts(array $filters): array
    {
        $query = $this->filtered(Order::query(), [...$filters, 'view' => 'all'])->toBase();
        $new = $this->initialStatus();
        $row = $query->selectRaw(
            'count(*) as total,'
            .' sum(case when status_id = ? then 1 else 0 end) as fresh,'
            .' sum(case when awaiting_payment = ? and cancelled_at is null then 1 else 0 end) as awaiting,'
            .' sum(case when payment_failed_at is not null and awaiting_payment = ? and cancelled_at is null then 1 else 0 end) as failed',
            [$new ?? 0, true, true],
        )->first();

        return [
            'all' => (int) ($row->total ?? 0),
            'new' => (int) ($row->fresh ?? 0),
            'awaiting' => (int) ($row->awaiting ?? 0),
            'failed' => (int) ($row->failed ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters  `view`, `search`, `status`, `public`, `paid`, `received`, `cancelled`, `refunded`, `awaiting`, `failed`, `visitor`, `delivery`, `payment`, `source`, `from`, `to`, `sort`
     * @return Builder<Order>
     */
    public function query(array $filters): Builder
    {
        $query = Order::query()
            ->select('orders.*')
            ->addSelect([
                // The first line of the order, its name in every language: the preview of what
                // was bought, without loading the lines.
                'first_item_name' => OrderItem::query()
                    ->select('name')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->whereNull('parent_id')
                    ->orderBy('position')
                    ->orderBy('id')
                    ->limit(1),
                // Where it came from: the milestone «placed» keeps it, one row an order by its unique key.
                'placed_source' => self::placed()->select('source'),
            ])
            ->withCount(['items as roots_count' => static fn (Builder $items) => $items->whereNull('parent_id')]);

        $this->filtered($query, $filters);

        $sort = (string) ($filters['sort'] ?? '-placed_at');
        $column = ltrim($sort, '-');
        $column = in_array($column, self::SORTS, true) ? $column : 'placed_at';
        $direction = str_starts_with($sort, '-') || $sort === '' ? 'desc' : 'asc';

        return $query->orderBy($column, $direction)->orderBy('id', $direction);
    }

    /**
     * @template T of Builder<Order>
     *
     * @param  T  $query
     * @param  array<string, mixed>  $filters
     * @return T
     */
    private function filtered(Builder $query, array $filters): Builder
    {
        $term = trim((string) ($filters['search'] ?? ''));

        if ($term !== '') {
            $this->search($query, $term);
        }

        match ((string) ($filters['view'] ?? 'all')) {
            'new' => $query->where('status_id', $this->initialStatus() ?? 0),
            // A cancelled order still carries the flag (§5.1): it is not awaiting anything.
            'awaiting' => $query->where('awaiting_payment', true)->whereNull('cancelled_at'),
            'failed' => $query->where('awaiting_payment', true)->whereNull('cancelled_at')->whereNotNull('payment_failed_at'),
            default => null,
        };

        $status = self::ids($filters['status'] ?? null);

        if ($status !== []) {
            $query->whereIn('status_id', $status);
        }

        $public = self::ids($filters['public'] ?? null);

        if ($public !== []) {
            $query->whereIn('status_id', static fn (QueryBuilder $statuses) => $statuses
                ->select('id')
                ->from('order_statuses')
                ->whereIn('public_status_id', $public));
        }

        foreach (['paid' => 'paid_at', 'received' => 'received_at', 'cancelled' => 'cancelled_at'] as $key => $column) {
            $flag = self::flag($filters[$key] ?? null);

            if ($flag !== null) {
                $flag ? $query->whereNotNull($column) : $query->whereNull($column);
            }
        }

        $awaiting = self::flag($filters['awaiting'] ?? null);

        if ($awaiting !== null) {
            $awaiting
                ? $query->where('awaiting_payment', true)->whereNull('cancelled_at')
                : $query->where(static fn (Builder $not) => $not->where('awaiting_payment', false)->orWhereNotNull('cancelled_at'));
        }

        $failed = self::flag($filters['failed'] ?? null);

        if ($failed !== null) {
            $failed ? $query->whereNotNull('payment_failed_at') : $query->whereNull('payment_failed_at');
        }

        $refunded = self::flag($filters['refunded'] ?? null);

        if ($refunded !== null) {
            $exists = static fn (QueryBuilder $refunds) => $refunds
                ->selectRaw('1')
                ->from((new OrderRefund)->getTable())
                ->whereColumn('order_refunds.order_id', 'orders.id');
            $refunded ? $query->whereExists($exists) : $query->whereNotExists($exists);
        }

        $visitor = $filters['visitor'] ?? null;

        if ($visitor === 'guest') {
            $query->whereNull('user_id');
        } elseif ($visitor === 'visitor') {
            $query->whereNotNull('user_id');
        }

        if (is_numeric($filters['user'] ?? null)) {
            $query->where('user_id', (int) $filters['user']);
        }

        foreach (OrderBlocks::KEYS as $block) {
            $method = $filters[$block] ?? null;

            if (is_string($method) && $method !== '') {
                $query->where("{$block}->method", $method);
            }
        }

        $source = $filters['source'] ?? null;

        if (is_string($source) && $source !== '') {
            $query->whereExists(self::placed()->toBase()->selectRaw('1')->where('source', $source));
        }

        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            $day = $filters[$key] ?? null;

            if (is_string($day) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1) {
                $bound = Carbon::parse($day);
                $query->where('placed_at', $operator, $key === 'from' ? $bound->startOfDay() : $bound->endOfDay());
            }
        }

        return $query;
    }

    /**
     * By the number, the e-mail, the phone in any spelling — its digits against the key the
     * order keeps (§3) — and the name in either order: «Anna Petrova», «Petrova Anna» and
     * «Petrova» find the same buyer, each word against either half of the name.
     *
     * @param  Builder<Order>  $query
     */
    private function search(Builder $query, string $term): void
    {
        $like = '%'.self::escape($term).'%';
        $digits = preg_replace('/\D+/', '', $term) ?? '';
        $words = array_values(array_filter(preg_split('/\s+/u', $term) ?: []));

        $query->where(static function (Builder $any) use ($like, $digits, $words): void {
            $any->where('number', 'like', $like)
                ->orWhere('customer_email', 'like', mb_strtolower($like));

            if (strlen($digits) >= 3) {
                $any->orWhere('customer_phone_key', 'like', '%'.$digits.'%');
            }

            $any->orWhere(static function (Builder $name) use ($words): void {
                foreach ($words as $word) {
                    $one = '%'.self::escape($word).'%';
                    $name->where(static fn (Builder $either) => $either->where('customer_first_name', 'like', $one)->orWhere('customer_last_name', 'like', $one));
                }
            });
        });
    }

    /**
     * One row of the list (§11.2): what the table draws, the money already written in the
     * currency of the order.
     *
     * @return array<string, mixed>
     */
    public function row(Order $order, StatusChoices $statuses, string $locale): array
    {
        $first = $order->getAttribute('first_item_name');
        $names = is_string($first) ? json_decode($first, true) : null;
        $name = is_array($names) ? (string) ($names[$locale] ?? $names[$order->locale] ?? reset($names) ?: '') : null;

        $source = $order->getAttribute('placed_source');

        return [
            'id' => $order->id,
            'number' => $order->number,
            'placed_at' => $order->placed_at->toAtomString(),
            'customer' => [
                'name' => $order->customerName(),
                'phone' => $order->customer_phone,
                'email' => $order->customer_email,
            ],
            'guest' => $order->user_id === null,
            'source' => is_string($source) ? $source : null,
            'items' => ['first' => $name, 'more' => max(0, (int) $order->getAttribute('roots_count') - 1)],
            'total' => $this->money->format($order->total, $order->currency),
            'payable' => $this->money->format($order->payable, $order->currency),
            'status' => $statuses->find($order->status_id, $locale),
            'awaiting_payment' => $order->awaiting_payment && $order->cancelled_at === null,
            'payment_failed' => $order->payment_failed_at !== null && $order->paid_at === null,
            'paid_at' => $order->paid_at?->toAtomString(),
            'received_at' => $order->received_at?->toAtomString(),
            'cancelled_at' => $order->cancelled_at?->toAtomString(),
            'refunded' => $order->refunded_total > 0 || $order->refunded_at !== null,
            'delivery' => $this->summary('delivery', $order->delivery, $locale),
            'payment' => $this->summary('payment', $order->payment, $locale),
        ];
    }

    /**
     * The methods the orders were placed with, for the filters: only those a block has, read off
     * the orders themselves — a method switched off since still filters its orders.
     *
     * @return array<string, list<string>>
     */
    public function methods(): array
    {
        $methods = [];

        foreach (OrderBlocks::KEYS as $block) {
            $methods[$block] = Order::query()
                ->whereNotNull($block)
                ->distinct()
                ->orderBy('method')
                ->pluck("{$block}->method as method")
                ->filter(static fn (mixed $method): bool => is_string($method) && $method !== '')
                ->map(strval(...))
                ->values()
                ->all();
        }

        return $methods;
    }

    /**
     * The block's own line, by its owner (§6.4); without one, its method as it is stored.
     *
     * @param  array<string, mixed>|null  $value
     */
    public function summary(string $key, ?array $value, string $locale): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        $line = $this->blocks->summary($key, $value, $locale);

        if ($line !== null && $line !== '') {
            return $line;
        }

        $method = $value['method'] ?? null;

        return is_string($method) && $method !== '' ? $method : null;
    }

    /**
     * The milestone «placed» of the order in the outer query (`order_milestones`, unique `(order_id, milestone)`).
     *
     * @return Builder<OrderMilestone>
     */
    private static function placed(): Builder
    {
        return OrderMilestone::query()
            ->whereColumn('order_milestones.order_id', 'orders.id')
            ->where('milestone', Milestone::Placed->value);
    }

    /** The status a new order comes into, for the tab «New»; null when there is none to come into. */
    public function initialStatus(): ?int
    {
        try {
            return $this->initial->for(false)->id;
        } catch (\LogicException) {
            return null;
        }
    }

    /**
     * @return list<int>
     */
    private static function ids(mixed $value): array
    {
        $values = is_array($value) ? $value : (is_scalar($value) ? explode(',', (string) $value) : []);

        return array_values(array_unique(array_map(intval(...), array_filter($values, static fn (mixed $one): bool => is_numeric($one)))));
    }

    private static function flag(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private static function escape(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
    }
}
