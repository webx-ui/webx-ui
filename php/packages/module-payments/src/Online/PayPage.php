<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use WebxUi\Cart\Money;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Orders;
use WebxUi\Orders\Site\OrderLinks;
use WebxUi\Payments\Instructions;
use WebxUi\Payments\Kind;
use WebxUi\Payments\Method;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Words;

/**
 * What the page of payment of an order shows (spec §8.4), from the order and its last attempt —
 * the amount is always what the order owes now (decision 29), never one kept in the link. While
 * the last attempt is pending, the page asks the gateway itself, at most every 15 s, and refreshes
 * every 5 s for two minutes without JavaScript; after that — «you may close the page» and «Check
 * again», never a second «Pay»: that is how a payment gets made twice.
 */
final readonly class PayPage
{
    /** «We are checking» refreshes itself this long, then says the page may be closed (§8.4). */
    public const int CHECKING_SECONDS = 120;

    public const int REFRESH_SECONDS = 5;

    public function __construct(
        private Orders $orders,
        private Methods $methods,
        private GatewayCalls $calls,
        private PaymentLinks $links,
        private OrderLinks $orderLinks,
        private Money $money,
        private Checks $checks,
    ) {}

    /** @return array<string, mixed> */
    public function of(Order $order, ?string $error = null, ?string $status = null): array
    {
        $locale = (string) $order->locale;
        $block = is_array($order->payment) ? $order->payment : [];
        $due = $this->orders->balance($order)->due;
        $last = Payment::query()->where('order_id', $order->id)->orderByDesc('id')->first();

        // «We are checking»: the page asks the gateway itself, at most every 15 s (§8.4).
        if ($last?->status === PaymentStatus::Pending) {
            $this->checks->recheck($last, 'pay-page');
            $order->refresh();
            $due = $this->orders->balance($order)->due;
        }
        $method = is_string($block['method'] ?? null) ? $this->online((string) $block['method']) : null;

        $state = match (true) {
            $order->cancelled_at !== null => 'cancelled',
            $due <= 0 => 'paid',
            $last?->status === PaymentStatus::Authorized => 'held',
            $last?->status === PaymentStatus::Pending => $last->updated_at !== null && $last->updated_at->diffInSeconds(now(), true) > self::CHECKING_SECONDS ? 'checking_long' : 'checking',
            ($block['kind'] ?? null) === Kind::Offline->value && ($block['type'] ?? null) === BankTransfer::KEY => 'transfer',
            ($block['kind'] ?? null) === Kind::Offline->value => 'on_receipt',
            default => 'pay',
        };

        return [
            'number' => (string) $order->number,
            'state' => $state,
            'due' => $this->money->format(max(0, $due), (string) $order->currency),
            'method' => $method !== null ? ($method->title($locale) ?? Words::pick(is_array($block['title'] ?? null) ? $block['title'] : null, $locale) ?? $method->key) : null,
            'attempts' => $state === 'pay' && $method !== null ? $this->links->attempts($order) : null,
            'failed' => in_array($last?->status, [PaymentStatus::Failed, PaymentStatus::Cancelled, PaymentStatus::Expired], true),
            'reason' => $last !== null && in_array($last->status, [PaymentStatus::Failed, PaymentStatus::Cancelled, PaymentStatus::Expired], true) ? $last->failure_reason : null,
            'error' => $error,
            'status' => $status,
            'refresh' => $state === 'checking' ? self::REFRESH_SECONDS : null,
            'check' => $state === 'checking_long' ? $this->links->page($order) : null,
            'instructions' => $state === 'transfer' ? Instructions::of($block, (string) $order->number, $locale) : null,
            'order' => $this->orderLinks->page((string) $order->number, $locale) ?: null,
        ];
    }

    /** The order's own method, if it is an enabled online one whose type is installed. */
    public function online(string $key): ?Method
    {
        $method = $this->methods->find($key);

        return $method !== null && $this->calls->type($method->type) !== null ? $method : null;
    }
}
