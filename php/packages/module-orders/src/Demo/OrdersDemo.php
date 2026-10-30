<?php

declare(strict_types=1);

namespace WebxUi\Orders\Demo;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Carbon;
use WebxUi\Admin\Demo\DemoLedger;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Contracts\CurrencySource;
use WebxUi\Cart\Purchasables;
use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Cart\Totals\AdjustmentSources;
use WebxUi\Cart\Totals\Benefit;
use WebxUi\Cart\Totals\BenefitSources;
use WebxUi\Cart\Totals\GeneratedLine;
use WebxUi\Cart\Totals\LineSources;
use WebxUi\Cart\Totals\Tender;
use WebxUi\Cart\Totals\TenderSources;
use WebxUi\Localization\Locales;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\Notifications\SendOrderNotifications;
use WebxUi\Orders\Orders;
use WebxUi\Orders\PlaceOrder;
use WebxUi\Orders\RefundRequest;
use WebxUi\Orders\Site\VisitorOrders;
use WebxUi\Users\Models\User;

/**
 * Twelve orders (spec §15) so that every corner of the section has something in it: each public
 * status, two awaiting payment — one of them after a failed try, one paid while its status still
 * waits, a cancellation unpaid and one paid and given back whole, a received order with one line
 * given back, a rule's discount with its gift, a wallet's tender; with visitors on the site, three
 * of them the visitor's — one a guest order linked by hand.
 *
 * Every order is made and moved by {@see Orders}, never by writing its tables (decision 18): a
 * demo that wrote rows would show the panel what no real order ever looks like. The clock is set
 * back for each order while it is made and moved, so that the list has days to sort by and the
 * history of an order reads in the order it happened.
 *
 * No notification goes out: a demo that mailed the shop's contacts twelve times, the first time
 * anybody looked at it, would be a demo nobody runs twice.
 */
final class OrdersDemo
{
    /** The demo's source in the history: it is `webx:demo` that does it. */
    private const string SOURCE = 'command';

    /** The visitor `webx-ui/module-users` seeds, the first choice of the visitor's orders. */
    private const string VISITOR = 'anna.demo@example.com';

    public function __construct(
        private readonly Container $container,
        private readonly Orders $orders,
        private readonly CartService $carts,
        private readonly CurrencySource $currencies,
        private readonly Locales $locales,
    ) {}

    /** @return list<string> */
    public function requires(): array
    {
        // The visitor's orders want the visitor seeded first; without the module there are none.
        return VisitorOrders::installed() ? ['users'] : [];
    }

    public function seed(DemoLedger $ledger): void
    {
        // Orders already there are somebody's: no made-up ones among them, and no numbers taken.
        if (Order::query()->exists()) {
            $ledger->note('The site already has orders; the demo left them alone.');

            return;
        }

        $currency = $this->currencies->currency();

        if ($currency === null) {
            $ledger->note('No currency is set for the cart (webx-cart.currency); the demo made no orders.');

            return;
        }

        $purchasables = $this->container->make(Purchasables::class);

        if (! $purchasables->has(DemoItems::TYPE)) {
            $purchasables->register(DemoItems::TYPE, new DemoItems($currency));
        }

        $sources = new DemoSources;
        $this->container->make(LineSources::class)->register($sources);
        $this->container->make(AdjustmentSources::class)->register($sources);
        $this->container->make(TenderSources::class)->register($sources);
        $this->container->make(BenefitSources::class)->register($sources);

        $visitor = $this->visitor();

        if (VisitorOrders::installed() && $visitor === null) {
            $ledger->note('No visitor on the site; the demo orders are all guests\'.');
        }

        try {
            SendOrderNotifications::quietly(function () use ($ledger, $sources, $visitor): void {
                // Read once: from the first order on, the clock is the one the demo set back.
                $now = CarbonImmutable::now();

                foreach ($this->scenarios() as $index => $scenario) {
                    $this->order($ledger, $sources, $now, $index, $scenario, $visitor);
                }
            });
        } finally {
            $sources->forget();
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * @param  array<string, mixed>  $scenario
     */
    private function order(DemoLedger $ledger, DemoSources $sources, CarbonImmutable $now, int $index, array $scenario, ?User $visitor): void
    {
        $placed = $now->subDays((int) $scenario['days'])->setTime(9 + $index % 9, 15 + $index * 3);
        // Today's order is never placed later than now.
        $placed = $placed->greaterThan($now->subHours(2)) ? $now->subHours(2) : $placed;
        $this->at($placed);

        $locale = $this->locales->defaultCode();
        $forVisitor = $visitor !== null && ($scenario['visitor'] ?? null) !== null;
        $customer = $forVisitor
            ? new Contact((string) $visitor->first_name, $visitor->last_name, $visitor->phone, $visitor->email)
            : new Contact($this->word(['en' => 'Buyer', 'ru' => 'Покупатель'], $locale), chr(65 + $index), sprintf('+800 1234 %04d', 5678 + $index), 'buyer-'.chr(97 + $index).'@example.com');

        $cart = $this->carts->add(null, $scenario['items'], $locale)->cart;
        $this->extras($sources, $scenario, $cart->items()->orderBy('id')->value('id'));

        try {
            $order = $this->orders->place(new PlaceOrder(
                cart: $cart,
                locale: $locale,
                customer: $customer,
                comment: $scenario['comment'] ?? null,
                awaitPayment: (bool) ($scenario['await'] ?? false),
                userId: $forVisitor && $scenario['visitor'] === 'account' ? $visitor->getKey() : null,
                source: 'checkout',
            ));
        } finally {
            $sources->forget();
            // The cart did its part: an order keeps its snapshot, and an empty cart nobody holds
            // a cookie of would only wait for the cart's prune.
            $this->carts->clear($cart);
        }

        $ledger->created($order, (string) $order->number);

        // A child line refuses its parent's deletion (lines are never deleted, so the key is
        // `restrict`): written after the order, it goes first when the journal is played back.
        foreach ($order->items()->whereNotNull('parent_id')->get() as $child) {
            $ledger->created($child, $order->number.': '.$child->sku);
        }

        foreach ($scenario['steps'] ?? [] as [$hours, $step, $argument]) {
            $this->at($placed->addHours($hours));
            $order->refresh();

            match ($step) {
                'paid' => $this->orders->mark($order, Milestone::Paid, self::SOURCE, reference: (string) $argument),
                'failed' => $this->orders->paymentFailed($order, self::SOURCE, (string) $argument),
                'status' => $this->move($order, ...$argument),
                'refund' => $this->orders->refund($order, $this->refund($order, $argument)),
                'link' => $visitor !== null ? $this->orders->linkVisitor($order, (int) $visitor->getKey(), self::SOURCE) : null,
                default => null,
            };
        }
    }

    /**
     * The rule's discount with its gift, or the wallet's tender, for the cart about to be ordered.
     *
     * @param  array<string, mixed>  $scenario
     */
    private function extras(DemoSources $sources, array $scenario, ?int $firstLine): void
    {
        $locale = $this->locales->defaultCode();

        if (($scenario['rule'] ?? false) && $firstLine !== null) {
            $label = $this->word(['en' => 'Spring sale', 'ru' => 'Весенняя распродажа'], $locale);
            $gift = $this->word(['en' => 'Gift', 'ru' => 'Подарок'], $locale);
            // A line costs at least one minor unit; the rule takes that unit back, so the total is whole.
            $sources->lines = [new GeneratedLine('rule#demo', DemoItems::TYPE, DemoItems::C, parentId: $firstLine, price: 1, label: $gift)];
            $sources->adjustments = [
                new Adjustment('rule#demo', $label.' −10%', -intdiv(DemoItems::price(DemoItems::A), 10), $firstLine, ['kind' => 'rule']),
                new Adjustment('rule#demo', $gift, -1, null, ['kind' => 'rule']),
            ];
            $sources->benefits = [new Benefit('demo.gift', $label, 'rule#demo')];
        }

        if (isset($scenario['wallet'])) {
            $sources->tenders = [new Tender('wallet', $this->word(['en' => 'Wallet', 'ru' => 'Кошелёк'], $locale), (int) $scenario['wallet'])];
        }
    }

    /** Into the status at this place of its public group, as the default set has them. */
    private function move(Order $order, string $group, int $place, ?string $note = null): void
    {
        $status = $this->status($group, $place);

        if ($status !== null) {
            $this->orders->moveTo($order, $status, self::SOURCE, $note);
        }
    }

    /**
     * `full` — what is left of the money; otherwise one unit of the order's line of that item.
     *
     * @param  array{0: string|int, 1: string, 2: string}  $argument  what, reference, reason
     */
    private function refund(Order $order, array $argument): RefundRequest
    {
        [$what, $reference, $reason] = $argument;

        if ($what === 'full') {
            return new RefundRequest($order->payable - $order->refunded_total, reference: $reference, reason: $reason, source: self::SOURCE);
        }

        $item = $order->items()->where('ref_id', $what)->whereNull('parent_id')->firstOrFail();

        return new RefundRequest($item->price, items: [$item->id => 1], reference: $reference, reason: $reason, source: self::SOURCE);
    }

    /**
     * A status of the set the site started with, found by its group and its place in it: the
     * internal statuses have no code, and a site that renamed them still has them where they were.
     */
    private function status(string $group, int $place): ?OrderStatus
    {
        $public = PublicStatus::query()->where('code', $group)->first();

        return $public === null ? null : OrderStatus::query()
            ->where('public_status_id', $public->id)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->skip($place)
            ->first();
    }

    /** The demo visitor, or the first visitor the site has; null without the module. */
    private function visitor(): ?User
    {
        if (! VisitorOrders::installed() || ! class_exists(User::class)) {
            return null;
        }

        return User::query()->where('email', self::VISITOR)->first() ?? User::query()->orderBy('id')->first();
    }

    private function at(CarbonImmutable $moment): void
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }

    /** @param  array<string, string>  $words */
    private function word(array $words, string $locale): string
    {
        return $words[$locale] ?? $words['en'];
    }

    /**
     * The orders, oldest first. `steps` — [hours after placing, what, its argument]:
     * `paid` (reference), `failed` (reason), `status` ([group, place, note]), `refund`
     * ([item ref or `full`, reference, reason]), `link` — the visitor takes a guest's order.
     *
     * @return list<array<string, mixed>>
     */
    private function scenarios(): array
    {
        $a = ['type' => DemoItems::TYPE, 'ref' => DemoItems::A];
        $b = ['type' => DemoItems::TYPE, 'ref' => DemoItems::B];
        $c = ['type' => DemoItems::TYPE, 'ref' => DemoItems::C];
        $set = [
            ['type' => DemoItems::TYPE, 'ref' => DemoItems::D, 'key' => 'set'],
            ['type' => DemoItems::TYPE, 'ref' => DemoItems::D_PART_ONE, 'parent' => 'set'],
            ['type' => DemoItems::TYPE, 'ref' => DemoItems::D_PART_TWO, 'parent' => 'set'],
        ];

        return [
            // Received, one line of it given back later.
            ['days' => 24, 'items' => [$a, [...$c, 'qty' => 2]], 'steps' => [
                [1, 'paid', 'PAY-1001'],
                [2, 'status', ['in_progress', 0]],
                [26, 'status', ['shipped', 0]],
                [72, 'status', ['completed', 0]],
                [150, 'refund', [DemoItems::C, 'RET-1001', 'One unit came damaged.']],
            ]],
            // The rule's discount and its gift line; the visitor's own.
            ['days' => 20, 'items' => [$a], 'rule' => true, 'visitor' => 'account', 'steps' => [
                [1, 'paid', 'PAY-1002'],
                [3, 'status', ['in_progress', 2]],
                [30, 'status', ['shipped', 0]],
                [80, 'status', ['completed', 0]],
            ]],
            // Paid, cancelled, the money given back outside the site.
            ['days' => 17, 'items' => [$b, $c], 'steps' => [
                [1, 'paid', 'PAY-1003'],
                [5, 'status', ['cancelled', 0, 'The buyer changed their mind.']],
                [28, 'refund', ['full', 'BANK-0423', 'Returned by bank transfer.']],
            ]],
            // Cancelled unpaid, by the manager, with the reason.
            ['days' => 14, 'items' => [$b], 'steps' => [
                [20, 'status', ['cancelled', 0, 'No answer to three calls.']],
            ]],
            // A bundle, on its way.
            ['days' => 10, 'items' => $set, 'steps' => [
                [1, 'paid', 'PAY-1005'],
                [4, 'status', ['in_progress', 2]],
                [30, 'status', ['shipped', 0]],
            ]],
            // Part of it paid from the wallet; the visitor's own.
            ['days' => 8, 'items' => [$a, $b], 'wallet' => 50000, 'visitor' => 'account', 'steps' => [
                [1, 'paid', 'PAY-1006'],
                [6, 'status', ['in_progress', 2]],
            ]],
            // Paid on delivery: in work and not paid.
            ['days' => 6, 'items' => [[...$c, 'qty' => 3]], 'steps' => [
                [3, 'status', ['in_progress', 0]],
            ]],
            // Waiting for the buyer to answer the phone.
            ['days' => 5, 'items' => [$b], 'comment' => 'Please call before delivery.', 'steps' => [
                [2, 'status', ['in_progress', 1]],
            ]],
            // Awaiting payment.
            ['days' => 3, 'items' => [$a], 'await' => true],
            // Awaiting payment after a try that did not go through.
            ['days' => 2, 'items' => [$b, $c], 'await' => true, 'steps' => [
                [0, 'failed', 'The card was declined.'],
            ]],
            // Paid, while its status still waits for a manager.
            ['days' => 1, 'items' => [$c], 'steps' => [
                [0, 'paid', 'PAY-1011'],
            ]],
            // A guest's, linked to the visitor by hand.
            ['days' => 0, 'items' => [$a, $c], 'visitor' => 'guest', 'steps' => [
                [1, 'link', null],
            ]],
        ];
    }
}
