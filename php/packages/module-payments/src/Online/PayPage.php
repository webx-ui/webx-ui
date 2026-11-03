<?php

declare(strict_types=1);

namespace WebxUi\Payments\Online;

use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Orders;
use WebxUi\Orders\Site\OrderLinks;
use WebxUi\Payments\Instructions;
use WebxUi\Payments\Kind;
use WebxUi\Payments\Method;
use WebxUi\Payments\MethodOption;
use WebxUi\Payments\Methods;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\PaymentContext;
use WebxUi\Payments\PaymentStatus;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Words;

/**
 * What the page of payment of an order shows (spec §8.4), from the order and its last attempt —
 * the amount is always what the order owes now (decision 29), never one kept in the link. While
 * the last attempt is pending, the page asks the gateway itself, at most every 15 s, and refreshes
 * every 5 s for two minutes without JavaScript; after that — «you may close the page» and «Check
 * again», never a second «Pay»: that is how a payment gets made twice.
 *
 * A paid order that owes more after an edit offers «Top up» by any online method (decision 29);
 * an order «on receipt» offers «Pay online now» (§8.5).
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
        private Closings $closings,
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
            $order->paid_at !== null => 'top_up',
            ($block['kind'] ?? null) === Kind::Offline->value && ($block['type'] ?? null) === BankTransfer::KEY => 'transfer',
            ($block['kind'] ?? null) === Kind::Offline->value => 'on_receipt',
            default => 'pay',
        };

        $online = in_array($state, ['top_up', 'on_receipt'], true) ? $this->onlineOptions($order, $due) : [];

        return [
            'number' => (string) $order->number,
            'state' => $state,
            'due' => $this->money->format(max(0, $due), (string) $order->currency),
            'method' => $method !== null ? ($method->title($locale) ?? Words::pick(is_array($block['title'] ?? null) ? $block['title'] : null, $locale) ?? $method->key) : null,
            'attempts' => ($state === 'pay' && $method !== null) || ($state === 'top_up' && $online !== []) ? $this->links->attempts($order) : null,
            // «Top up» posts its method to the attempts; «Pay online now» switches the method first.
            'online' => array_map(static fn (MethodOption $option): array => ['key' => $option->method->key, 'title' => $option->title], $online),
            'switch' => $state === 'on_receipt' && $online !== [] ? $this->links->method($order) : null,
            // An earlier attempt the gateway would not close may still take money (§9.4).
            'wait' => $state === 'pay' && $this->closings->open($order, $last?->id),
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

    /**
     * The online methods offered for what the order owes now (§8.5) — a top-up or «Pay online
     * now». The delivery is the order's, so a rule by delivery has nothing to ask: the buyer's
     * delivery is settled.
     *
     * @return list<MethodOption>
     */
    public function onlineOptions(Order $order, ?int $due = null): array
    {
        $context = new PaymentContext($due ?? $this->orders->balance($order)->due, (string) $order->currency, (string) $order->locale, null, Purpose::Order);

        return array_values(array_filter(
            $this->methods->available($context),
            fn (MethodOption $option): bool => $option->kind === Kind::Online && $this->calls->type($option->method->type) !== null,
        ));
    }

    /** The order's own method, if it is an enabled online one whose type is installed. */
    public function online(string $key): ?Method
    {
        $method = $this->methods->find($key);

        return $method !== null && $this->calls->type($method->type) !== null ? $method : null;
    }
}
