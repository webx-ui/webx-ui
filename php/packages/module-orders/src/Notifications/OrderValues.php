<?php

declare(strict_types=1);

namespace WebxUi\Orders\Notifications;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use WebxUi\Cart\Money;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Data\ItemData;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Data\RevisionData;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\Panel\OrderList;
use WebxUi\Orders\Site\OrderLinks;

/**
 * The values of an order for its templates (spec §10.2) — a snapshot of what the event carried:
 * the job renders later, but from the order as it was then. Money is a string in the currency of
 * the order, a date is in its language.
 *
 * Knows nothing of the notifications module: an array of strings and lists, so a test, the
 * listener and an agent's preview read the same.
 */
final readonly class OrderValues
{
    /** The page of the order for the buyer (§13.1), when the site has it. */
    public const string PUBLIC_ROUTE = OrderLinks::PAGE;

    public function __construct(
        private Container $container,
        private Money $money,
    ) {}

    /**
     * @param  array<string, string|list<array<string, string>>>  $extra  the values of one event: `reason`, `reference`, `refund_amount`, the edit's
     * @return array<string, string|list<array<string, string>>>
     */
    public function of(OrderData $order, array $extra = []): array
    {
        $locale = $order->locale;
        $money = fn (int $minor): string => $this->money->format($minor, $order->currency);
        $nonZero = fn (int $minor): string => $minor === 0 ? '' : $money($minor);
        $placed = $order->milestones['placed'] ?? null;
        $public = PublicStatus::withTrashed()->find($order->publicStatus->id);
        $list = $this->container->make(OrderList::class);

        return [
            'number' => $order->number,
            'order_url' => self::panelUrl($order->id),
            'public_url' => self::publicUrl($order->number, $locale),
            'placed_at' => $placed === null ? '' : (string) Carbon::parse($placed)->locale($locale)->isoFormat('L LT'),
            'customer_name' => $order->customer->name(),
            'customer_first_name' => $order->customer->firstName,
            'customer_last_name' => (string) $order->customer->lastName,
            'customer_phone' => (string) $order->customer->phone,
            'customer_email' => (string) $order->customer->email,
            'recipient_name' => (string) $order->recipient?->name(),
            'recipient_first_name' => (string) $order->recipient?->firstName,
            'recipient_last_name' => (string) $order->recipient?->lastName,
            'recipient_phone' => (string) $order->recipient?->phone,
            'items' => array_map(static fn (ItemData $item): array => [
                'name' => $item->name,
                'options' => (string) $item->optionsLabel,
                'qty' => (string) $item->qty,
                'price' => $money($item->price),
                'total' => $money($item->total),
                // A child of a set: `{{#child}}` indents it, nothing else tells it apart.
                'child' => $item->parentId === null ? '' : (string) trans('webx-orders::notifications.yes', [], $locale),
            ], $order->items),
            'subtotal' => $money($order->subtotal),
            'discount' => $nonZero($order->adjustmentsTotal),
            'total' => $money($order->total),
            'tenders' => $nonZero(-$order->tendersTotal),
            'payable' => $money($order->payable),
            'delivery' => (string) $list->summary('delivery', $order->delivery, $locale),
            'payment' => (string) $list->summary('payment', $order->payment, $locale),
            'comment' => (string) $order->comment,
            'public_status' => $order->publicStatus->name,
            'public_status_code' => $order->publicStatus->code,
            'public_status_description' => (string) $public?->getTranslation('description', $locale),
            'status' => $order->status->name,
            ...$extra,
        ];
    }

    /**
     * The values of `orders.items_changed` (§40.2): what came and went, the total it was, the
     * balance after and the manager's words to the buyer. Never the edit's `reason` — that one is
     * the shop's, like the manager's note.
     *
     * @return array{changes: list<array<string, string>>, previous_total: string, balance: string, balance_kind: string, amount_due: string, amount_over: string, message: string}
     */
    public function edit(RevisionData $revision, Balance $balance, string $locale): array
    {
        $yes = (string) trans('webx-orders::notifications.yes', [], $locale);

        return [
            'changes' => array_map(static fn (array $change): array => [
                'name' => $change['name'],
                'options' => (string) $change['options_label'],
                'qty_from' => (string) $change['qty_from'],
                'qty_to' => (string) $change['qty_to'],
                // Sections for a template: `{{#added}}` and `{{#removed}}` tell a line that came or went.
                'added' => $change['op'] === 'add' ? $yes : '',
                'removed' => $change['op'] === 'remove' ? $yes : '',
            ], $revision->changes),
            'previous_total' => $this->money->format($revision->before['total'], $balance->currency),
            ...$this->balance($balance),
            'message' => (string) $revision->message,
        ];
    }

    /**
     * The balance in words a template can branch on (§36.1): `balance` is the money either way,
     * `amount_due` / `amount_over` the one that applies and empty the other.
     *
     * @return array{balance: string, balance_kind: string, amount_due: string, amount_over: string}
     */
    public function balance(Balance $balance): array
    {
        $format = fn (int $minor): string => $minor > 0 ? $this->money->format($minor, $balance->currency) : '';

        return [
            'balance' => $this->money->format(max($balance->due, $balance->over), $balance->currency),
            'balance_kind' => match (true) {
                $balance->due > 0 => 'due',
                $balance->over > 0 => 'over',
                default => 'none',
            },
            'amount_due' => $format($balance->due),
            'amount_over' => $format($balance->over),
        ];
    }

    /**
     * The order in the panel, from `app.url` and not from the request: the job that sends it
     * runs in a worker.
     */
    public static function panelUrl(int $id): string
    {
        $path = trim((string) config('webx-admin.path', 'cms'), '/');

        return rtrim((string) config('app.url'), '/')."/{$path}/orders/{$id}";
    }

    /**
     * The signed page of the order for the buyer (§13.1, decision 32), or empty while the site has
     * no such page — a template shows its link under `{{#public_url}}`.
     */
    public static function publicUrl(string $number, string $locale): string
    {
        return app(OrderLinks::class)->page($number, $locale);
    }

    public function format(int $minor, string $currency): string
    {
        return $this->money->format($minor, $currency);
    }
}
