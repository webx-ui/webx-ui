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
use WebxUi\Payments\MethodOption;
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
 *
 * A pending attempt the gateway says the buyer never began is not «we are checking»: nothing is
 * coming, so the page says what the order needs, as if the attempt were not there — «Payment not
 * completed» with «Pay again» for an order that was waiting for it. The gateway is still asked, so
 * a late payment from a tab left open shows at once.
 *
 * A pending attempt the bank declined and the gateway keeps open for another card is told as it
 * is: «The bank declined the card» with its reason and the same two ways on. The page does not
 * refresh itself: the gateway is asked when the buyer opens the page again or presses «Check again».
 *
 * A paid order that owes more after an edit offers «Top up» by any online method (decision 29);
 * an order «on receipt» offers «Pay online now» (§8.5). An unpaid order may go over to any method
 * it could be placed with, offline ones too: «Choose another method», the list of the checkout's
 * step behind one button (groups spec §6.8), which for an order «on receipt» stands open. The order's
 * own page is where such a buyer already is, so it offers that list too: `offer()`.
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
        $declined = $last?->declined() === true;
        $failed = in_array($last?->status, [PaymentStatus::Failed, PaymentStatus::Cancelled, PaymentStatus::Expired], true);

        $state = match (true) {
            $order->cancelled_at !== null => 'cancelled',
            $due <= 0 => 'paid',
            $last?->status === PaymentStatus::Authorized => 'held',
            $last?->status === PaymentStatus::Pending && ! $notStarted && ! $declined => $last->updated_at !== null && $last->updated_at->diffInSeconds(now(), true) > self::CHECKING_SECONDS ? 'checking_long' : 'checking',
            $order->paid_at !== null => 'top_up',
            ($block['kind'] ?? null) === Kind::Offline->value && ($block['type'] ?? null) === BankTransfer::KEY => 'transfer',
            ($block['kind'] ?? null) === Kind::Offline->value => 'on_receipt',
            $declined => 'declined',
            $notStarted => 'unfinished',
            default => 'pay',
        };

        $online = in_array($state, ['top_up', 'on_receipt'], true) ? $this->onlineOptions($order, $due) : [];
        // A top-up has its own list and keeps the block.
        $others = in_array($state, ['pay', 'unfinished', 'declined', 'transfer', 'on_receipt'], true) ? $this->others($order, $due) : [];

        return [
            'number' => (string) $order->number,
            'state' => $state,
            'due' => $this->money->format(max(0, $due), (string) $order->currency),
            'method' => $method !== null ? ($method->title($locale) ?? Words::pick(is_array($block['title'] ?? null) ? $block['title'] : null, $locale) ?? $method->key) : null,
            'attempts' => (in_array($state, ['pay', 'unfinished', 'declined'], true) && $method !== null) || ($state === 'top_up' && $online !== []) ? $this->links->attempts($order) : null,
            // «Top up» posts its method to the attempts. Of an order «on receipt» it only says the
            // list of `others` may be paid online now, and a theme's older copy has its buttons by it.
            'online' => array_map(static fn (MethodOption $option): array => ['key' => $option->method->key, 'title' => $option->title], $online),
            'others' => self::listed($others),
            'switch' => $others !== [] ? $this->links->method($order) : null,
            // An earlier attempt the gateway would not close may still take money (§9.4), and so
            // may the one not begun: its page can be open in another tab.
            'wait' => $notStarted || $declined || ($state === 'pay' && $this->closings->open($order, $last?->id)),
            'failed' => $failed,
            'reason' => $failed ? $last?->failure_reason : $last?->declineReason(),
            'error' => $error,
            'status' => $status,
            'refresh' => $state === 'checking' ? self::REFRESH_SECONDS : null,
            'check' => $state === 'checking_long' || $state === 'declined' ? $this->links->page($order) : null,
            'instructions' => $state === 'transfer' ? Instructions::of($block, (string) $order->number, $locale) : null,
            'order' => $this->orderLinks->page((string) $order->number, $locale) ?: null,
        ];
    }

    /**
     * What the buyer may do on the order's own page while it awaits an online payment (§8.4): null
     * for any other order — the shop's words say it then. Reads the rows, never asks the gateway:
     * the page of an order is not a poll.
     *
     * @return array{checking: bool, page: string, attempts: string|null, switch: string|null, others: list<array{key: string, title: string, option: MethodOption}>}|null
     */
    public function prompt(Order $order): ?array
    {
        if (! $this->payableOnline($order)) {
            return null;
        }

        $block = is_array($order->payment) ? $order->payment : [];
        $page = $this->links->page($order);
        $last = Payment::query()->where('order_id', $order->id)->orderByDesc('id')->first();
        $checking = $last?->status === PaymentStatus::Pending && ! $last->notStarted() && ! $last->declined();
        $others = $checking ? [] : $this->others($order, $this->orders->balance($order)->due);
        $method = is_string($block['method'] ?? null) ? $this->online((string) $block['method']) : null;

        return [
            'checking' => $checking,
            'page' => $page,
            'attempts' => ! $checking && $method !== null ? $this->links->attempts($order) : null,
            'switch' => $others !== [] ? $this->links->method($order) : null,
            'others' => self::listed($others),
        ];
    }

    /**
     * «Pay online now» on the order's own page (§8.5): an order on receipt awaits nothing, so
     * `prompt()` is not for it, and nothing else leads its buyer to the page of payment. Null unless
     * the page of payment would say the same: the order owes money, nothing is held or being
     * checked, and one of the other methods is online. Reads the rows, never asks the gateway.
     *
     * @return array{switch: string, others: list<array{key: string, title: string, option: MethodOption}>}|null
     */
    public function offer(Order $order): ?array
    {
        $block = is_array($order->payment) ? $order->payment : [];

        if (($block['kind'] ?? null) !== Kind::Offline->value || ($block['type'] ?? null) === BankTransfer::KEY) {
            return null;
        }

        $due = $this->orders->balance($order)->due;
        $switch = $this->links->method($order);

        if ($order->cancelled_at !== null || $order->paid_at !== null || $due <= 0 || $switch === '') {
            return null;
        }

        $last = Payment::query()->where('order_id', $order->id)->orderByDesc('id')->first();

        if ($last?->status === PaymentStatus::Authorized || ($last?->status === PaymentStatus::Pending && ! $last->notStarted() && ! $last->declined())) {
            return null;
        }

        $others = $this->others($order, $due);

        foreach ($others as $option) {
            if ($option->kind === Kind::Online) {
                return ['switch' => $switch, 'others' => self::listed($others)];
            }
        }

        return null;
    }

    /**
     * Of the orders that await payment, the ones `prompt()` answers for — «My orders» (§8.4): the
     * same rule without the buttons, and one query for the list. Money held is paid for the buyer.
     *
     * @param  list<Order>  $orders
     * @return list<int>
     */
    public function promptable(array $orders): array
    {
        $online = array_values(array_filter($orders, $this->payableOnline(...)));
        $ids = array_map(static fn (Order $order): int => $order->id, $online);

        if ($ids === []) {
            return [];
        }

        $held = Payment::query()->whereIn('order_id', $ids)->where('status', PaymentStatus::Authorized->value)->pluck('order_id')->all();

        return array_values(array_diff($ids, $held));
    }

    /** Paid by an online method, and the site has the page to pay on. */
    private function payableOnline(Order $order): bool
    {
        $block = is_array($order->payment) ? $order->payment : [];

        return ($block['kind'] ?? null) === Kind::Online->value && $this->links->page($order) !== '';
    }

    /**
     * Every method this order may be paid with now (§8.5): the rules of each by what it owes and
     * by its delivery, offline ones too. An online one needs its type installed.
     *
     * @return list<MethodOption>
     */
    public function options(Order $order, ?int $due = null): array
    {
        return array_values(array_filter(
            $this->methods->available(OrderContext::of($order, $due ?? $this->orders->balance($order)->due)),
            fn (MethodOption $option): bool => $option->kind !== Kind::Online || $this->calls->type($option->method->type) !== null,
        ));
    }

    /**
     * The online methods offered for what the order owes now (§8.5): a top-up or «Pay online now».
     *
     * @return list<MethodOption>
     */
    public function onlineOptions(Order $order, ?int $due = null): array
    {
        return array_values(array_filter($this->options($order, $due), static fn (MethodOption $option): bool => $option->kind === Kind::Online));
    }

    /**
     * What a form of «Choose another method» means (groups spec §6.4, §6.8). The order's own method
     * is not on the list, so a group that stands for a method never falls to it; named outright it
     * is still taken: a second press of «Continue» arrives when the first has already switched.
     */
    public function picked(Order $order, ?string $group, ?string $method): ?MethodOption
    {
        $named = $this->methods->picked($this->options($order), null, $method);

        if ($named !== null && ($group === null || $named->group === $group)) {
            return $named;
        }

        return $group === null ? null : $this->methods->picked($this->others($order, $this->orders->balance($order)->due), $group, null);
    }

    /** The order's own method, if it is an enabled online one whose type is installed. */
    public function online(string $key): ?Method
    {
        $method = $this->methods->find($key);

        return $method !== null && $this->calls->type($method->type) !== null ? $method : null;
    }

    /**
     * Another method than the order's own (§8.5).
     *
     * @return list<MethodOption>
     */
    private function others(Order $order, int $due): array
    {
        $own = is_array($order->payment) ? ($order->payment['method'] ?? null) : null;

        return array_values(array_filter($this->options($order, $due), static fn (MethodOption $option): bool => $option->method->key !== $own));
    }

    /**
     * The list as `pay/others` takes it: the option for the list of methods, the key and the title
     * for a theme's copy of the template from before it.
     *
     * @param  list<MethodOption>  $options
     * @return list<array{key: string, title: string, option: MethodOption}>
     */
    private static function listed(array $options): array
    {
        return array_map(static fn (MethodOption $option): array => ['key' => $option->method->key, 'title' => $option->title, 'option' => $option], $options);
    }
}
