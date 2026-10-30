<?php

declare(strict_types=1);

namespace WebxUi\Orders\Site;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use WebxUi\Cache\CacheGroups;
use WebxUi\Cart\Money;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Blocks\OrderBlocks;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderAdjustment;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\PublicStatus;

/**
 * What the buyer reads of an order (spec §13.1) — on the signed page and in «My orders» alike:
 * the number and the date, the public status with its description and the scale of the public
 * statuses in their order, the marks «Paid», «Received», «Cancelled», «Refunded», the lines, the
 * money, the delivery and the payment as their owners' one line, the contacts. The internal status
 * and the manager's note never.
 *
 * After an edit (§40.1): «Items changed» with the date over the lines, the lines as they are — one
 * taken out is not there — and a line of the balance under the money when it says more than «to
 * pay»: what is left to pay on a paid order, what the shop will give back. The edit's reason and
 * who made it never.
 *
 * Both sets of statuses come from the cache of the group `order-statuses` (§16), dropped when a
 * status is saved; an order costs its own queries and nothing a status more.
 */
final readonly class OrderPage
{
    /** The cache group of both sets of statuses as the site reads them. */
    public const string GROUP = 'order-statuses';

    public function __construct(
        private CacheGroups $groups,
        private Money $money,
        private OrderBlocks $blocks,
    ) {}

    /**
     * The order as the views print it, in the language of the page.
     *
     * @return array<string, mixed>
     */
    public function of(Order $order, string $locale): array
    {
        $order->loadMissing([
            'items' => static fn ($items) => $items->whereNull('removed_at')->orderBy('position')->orderBy('id'),
            'adjustments' => static fn ($adjustments) => $adjustments->whereNull('item_id')->orderBy('id'),
            'tenders',
        ]);
        $order->items->each->setRelation('order', $order);

        $money = fn (int $minor): string => $this->money->format($minor, $order->currency);
        $statuses = $this->statuses();
        $current = $statuses['groups'][$statuses['internal'][$order->status_id] ?? 0] ?? null;

        return [
            'number' => (string) $order->number,
            'placed_at' => $order->placed_at,
            'status' => $current === null ? null : [
                'code' => $current['code'],
                'name' => $current['name'],
                'description' => $current['description'],
            ],
            'scale' => $this->scale($statuses['groups'], $current, $order->cancelled_at !== null),
            'marks' => array_values(array_filter([
                $order->paid_at !== null ? ['key' => 'paid', 'label' => __('webx-orders::site.marks.paid')] : null,
                $order->received_at !== null ? ['key' => 'received', 'label' => __('webx-orders::site.marks.received')] : null,
                $order->cancelled_at !== null ? ['key' => 'cancelled', 'label' => __('webx-orders::site.marks.cancelled')] : null,
                $order->refunded_total > 0 ? ['key' => 'refunded', 'label' => __('webx-orders::site.marks.refunded', ['amount' => $money($order->refunded_total)])] : null,
            ])),
            'awaiting_payment' => $order->awaiting_payment && $order->paid_at === null && $order->cancelled_at === null,
            'changed_at' => $order->items_changed_at,
            'items' => $this->items($order->items, $locale, $money),
            'adjustments' => $order->adjustments->map(static fn (OrderAdjustment $adjustment): array => [
                'label' => $adjustment->label,
                'amount' => $money($adjustment->amount),
            ])->values()->all(),
            'totals' => [
                'subtotal' => $money($order->subtotal),
                'total' => $money($order->total),
                'tenders' => $order->tenders_total !== 0 ? $money($order->tenders_total) : null,
                'payable' => $money($order->payable),
            ],
            'balance' => $this->balance(Balance::of($order), $money),
            'blocks' => array_values(array_filter(array_map(function (string $key) use ($order, $locale): ?array {
                $value = $order->getAttribute($key);
                $line = is_array($value) ? $this->blocks->summary($key, $value, $locale) : null;

                return $line !== null && $line !== '' ? ['key' => $key, 'label' => __('webx-orders::site.blocks.'.$key), 'line' => $line] : null;
            }, OrderBlocks::KEYS))),
            'customer' => [
                'name' => $order->customerName(),
                'phone' => $order->customer_phone,
                'email' => $order->customer_email,
            ],
            'recipient' => $order->recipient === null ? null : [
                'name' => trim($order->recipient['first_name'].' '.($order->recipient['last_name'] ?? '')),
                'phone' => $order->recipient['phone'] ?? null,
            ],
            'comment' => $order->comment,
        ];
    }

    /**
     * The rows of «My orders»: number, date, public status, amount, how many lines — the items
     * counted by the query, the statuses read from the cache.
     *
     * @param  iterable<Order>  $orders
     * @return list<array<string, mixed>>
     */
    public function rows(iterable $orders): array
    {
        $statuses = $this->statuses();
        $rows = [];

        foreach ($orders as $order) {
            $group = $statuses['groups'][$statuses['internal'][$order->status_id] ?? 0] ?? null;
            $rows[] = [
                'number' => (string) $order->number,
                'placed_at' => $order->placed_at,
                'status' => $group['name'] ?? null,
                'status_code' => $group['code'] ?? null,
                'cancelled' => $order->cancelled_at !== null,
                'payable' => $this->money->format($order->payable, $order->currency),
                'lines' => (int) ($order->getAttribute('lines_count') ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Orders the visitor is still waiting for — neither received nor cancelled: the badge of the
     * menu and the warning before the account goes.
     *
     * @return Builder<Order>
     */
    public static function active(int $userId): Builder
    {
        return Order::query()->where('user_id', $userId)->whereNull('received_at')->whereNull('cancelled_at');
    }

    /**
     * Both sets in the language of the page: the public groups in their order with their words,
     * and each internal status's group — deleted ones too, an order may stand in one.
     *
     * @return array{groups: array<int, array{id: int, code: string, name: string, description: string|null, deleted: bool, cancels: bool}>, internal: array<int, int>}
     */
    private function statuses(): array
    {
        /** @var array{groups: array<int, array{id: int, code: string, name: string, description: string|null, deleted: bool, cancels: bool}>, internal: array<int, int>} */
        return $this->groups->remember('orders.site-statuses', [self::GROUP], static function (): array {
            $locale = app()->getLocale();
            $groups = [];

            $internal = OrderStatus::withTrashed()->get(['id', 'public_status_id', 'sets_milestone']);
            $cancelling = $internal->filter(static fn (OrderStatus $status): bool => $status->sets_milestone === Milestone::Cancelled)->pluck('public_status_id')->map(static fn (mixed $id): int => (int) $id)->all();

            foreach (PublicStatus::withTrashed()->orderBy('position')->orderBy('id')->get() as $group) {
                $description = trim((string) $group->getTranslation('description', $locale));
                $groups[$group->id] = [
                    'id' => $group->id,
                    'code' => $group->code,
                    'name' => (string) $group->getTranslation('name', $locale),
                    'description' => $description !== '' ? $description : null,
                    'deleted' => $group->deleted_at !== null,
                    'cancels' => in_array($group->id, $cancelling, true),
                ];
            }

            return [
                'groups' => $groups,
                'internal' => $internal->mapWithKeys(static fn (OrderStatus $status): array => [$status->id => (int) $status->public_status_id])->all(),
            ];
        });
    }

    /**
     * The public statuses in order, the ones behind the order's passed: a cancelled order has
     * no road ahead, and a deleted group, or the one of the cancellation, shows only while the
     * order stands in it — «Cancelled» is not a step every order is going to.
     *
     * @param  array<int, array{id: int, code: string, name: string, description: string|null, deleted: bool, cancels: bool}>  $groups
     * @param  array{id: int, code: string, name: string, description: string|null, deleted: bool, cancels: bool}|null  $current
     * @return list<array{name: string, state: string}>
     */
    private function scale(array $groups, ?array $current, bool $cancelled): array
    {
        $scale = [];
        $state = 'done';

        foreach ($groups as $group) {
            if (($group['deleted'] || $group['cancels']) && $group['id'] !== ($current['id'] ?? null)) {
                continue;
            }

            if ($group['id'] === ($current['id'] ?? null)) {
                $scale[] = ['name' => $group['name'], 'state' => 'current'];
                $state = 'ahead';

                continue;
            }

            if ($cancelled && $state === 'ahead') {
                continue;
            }

            $scale[] = ['name' => $group['name'], 'state' => $current === null ? 'ahead' : $state];
        }

        return $scale;
    }

    /**
     * The balance the buyer is told of (§40.1): left to pay once something was paid — before that
     * «to pay» says it — and money the shop owes back. Settled, nothing.
     *
     * @param  callable(int): string  $money
     * @return array{kind: string, label: string}|null
     */
    private function balance(Balance $balance, callable $money): ?array
    {
        return match ($balance->kind()) {
            'topup' => ['kind' => 'due', 'label' => (string) __('webx-orders::site.order.due', ['amount' => $money($balance->due)])],
            'over' => ['kind' => 'over', 'label' => (string) __('webx-orders::site.order.over', ['amount' => $money($balance->over)])],
            default => null,
        };
    }

    /**
     * The lines, the parts of a set under their set.
     *
     * @param  Collection<int, OrderItem>  $items
     * @param  callable(int): string  $money
     * @return list<array<string, mixed>>
     */
    private function items(Collection $items, string $locale, callable $money): array
    {
        $children = $items->whereNotNull('parent_id')->groupBy('parent_id');

        return $items->whereNull('parent_id')->map(static fn (OrderItem $item): array => [
            'name' => $item->nameIn($locale),
            'options' => $item->options_label,
            'sku' => $item->sku,
            'image' => $item->image,
            'qty' => $item->qty,
            'price' => $money($item->price),
            'total' => $money($item->total),
            'parts' => ($children[$item->id] ?? collect())->map(static fn (OrderItem $part): array => [
                'name' => $part->nameIn($locale),
                'qty' => $part->qty,
            ])->values()->all(),
        ])->values()->all();
    }
}
