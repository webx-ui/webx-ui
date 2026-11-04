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
 * A pending attempt the gateway says the buyer never began is not «we are checking»: nothing is
 * coming, so the page says what the order needs, as if the attempt were not there — «Payment not
 * completed» with «Pay again» for an order that was waiting for it. The gateway is still asked, so
 * a late payment from a tab left open shows at once.
 *
 * A paid order that owes more after an edit offers «Top up» by any online method (decision 29);
 * an order «on receipt» offers «Pay online now» (§8.5). After a failure, on «Payment not completed»
 * and with bank details the buyer may choose another online method.
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

        $notStarted = $last?->notStarted() === true;
        $failed = in_array($last?->status, [PaymentStatus::Failed, PaymentStatus::Cancelled, PaymentStatus::Expired], true);

        $state = match (true) {
            $order->cancelled_at !== null => 'cancelled',
            $due <= 0 => 'paid',
            $last?->status === PaymentStatus::Authorized => 'held',
            $last?->status === PaymentStatus::Pending && ! $notStarted => $last->updated_at !== null && $last->updated_at->diffInSeconds(now(), true) > self::CHECKING_SECONDS ? 'checking_long' : 'checking',
            $order->paid_at !== null => 'top_up',
            ($block['kind'] ?? null) === Kind::Offline->value && ($block['type'] ?? null) === BankTransfer::KEY => 'transfer',
            ($block['kind'] ?? null) === Kind::Offline->value => 'on_receipt',
            $notStarted => 'unfinished',
            default => 'pay',
        };

        $online = in_array($state, ['top_up', 'on_receipt'], true) ? $this->onlineOptions($order, $due) : [];
        // Another method than the order's own (§8.4); a top-up has its own list and keeps the block.
        $others = $state === 'unfinished' || $state === 'transfer' || ($state === 'pay' && $failed)
            ? array_values(array_filter($this->onlineOptions($order, $due), static fn (MethodOption $option): bool => $option->method->key !== ($block['method'] ?? null)))
            : [];

        return [
            'number' => (string) $order->number,
            'state' => $state,
            'due' => $this->money->format(max(0, $due), (string) $order->currency),
            'method' => $method !== null ? ($method->title($locale) ?? Words::pick(is_array($block['title'] ?? null) ? $block['title'] : null, $locale) ?? $method->key) : null,
            'attempts' => (in_array($state, ['pay', 'unfinished'], true) && $method !== null) || ($state === 'top_up' && $online !== []) ? $this->links->attempts($order) : null,
            // «Top up» posts its method to the attempts; «Pay online now» switches the method first.
            'online' => array_map(static fn (MethodOption $option): array => ['key' => $option->method->key, 'title' => $option->title], $online),
            'others' => array_map(static fn (MethodOption $option): array => ['key' => $option->method->key, 'title' => $option->title], $others),
            'switch' => ($state === 'on_receipt' && $online !== []) || $others !== [] ? $this->links->method($order) : null,
            // An earlier attempt the gateway would not close may still take money (§9.4), and so
            // may the one not begun: its page can be open in another tab.
            'wait' => $notStarted || ($state === 'pay' && $this->closings->open($order, $last?->id)),
            'failed' => $failed,
            'reason' => $failed ? $last?->failure_reason : null,
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
