<?php

declare(strict_types=1);

namespace WebxUi\Orders\Panel;

use Closure;
use Illuminate\Container\Container;
use WebxUi\Admin\Support\Authors;
use WebxUi\Admin\Visitors\Visitors;
use WebxUi\Cart\Money;
use WebxUi\Orders\Balance;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderAdjustment;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderRefund;
use WebxUi\Orders\Models\OrderTender;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\Notifications\OrderChannels;
use WebxUi\Orders\Site\VisitorOrders;

/**
 * The card of an order on the server (spec §11.3): what `orders.show` draws — the head with the
 * status and what the buyer sees, the lines as a tree with their discounts, the totals, the
 * tenders and the benefits, the timeline of §8 — and `values`, the part the screen edits: the
 * contacts, the recipient, the comment, the manager note.
 *
 * Nothing is written here. A change of anything goes through `Orders`, which this class only
 * reads after (decision 18).
 */
final readonly class OrderCard
{
    /** The fields of `values`, the keys the screen's inputs carry — as the service's errors name them. */
    public const array FIELDS = [
        'customer.first_name', 'customer.last_name', 'customer.phone', 'customer.email',
        'recipient.first_name', 'recipient.last_name', 'recipient.phone',
        'comment', 'manager_note',
    ];

    public function __construct(
        private Money $money,
        private OrderList $list,
        private OrderChannels $channels,
    ) {}

    /**
     * @param  callable(string): bool  $can  the reader's permissions
     * @return array{order: array<string, mixed>, values: array<string, string|null>}
     */
    public function describe(Order $order, StatusChoices $statuses, string $locale, callable $can, mixed $reader): array
    {
        $order->loadMissing(['items', 'adjustments', 'tenders', 'benefits', 'milestones', 'refunds', 'history']);

        // The pinned note is the block «Notes»'s, saved on its own: the screen's save never sends it back.
        $values = self::values($order);
        unset($values['manager_note']);

        return ['order' => $this->order($order, $statuses, $locale, $can, $reader), 'values' => $values];
    }

    /**
     * The timeline alone (§8) — what the card's tab «History» draws, for the agent's `orders_history`.
     *
     * @return list<array<string, mixed>>
     */
    public function history(Order $order, StatusChoices $statuses, string $locale, mixed $reader): array
    {
        $order->loadMissing(['milestones', 'refunds', 'history']);

        return $this->timeline($order, $statuses, $locale, self::people($order, $reader), fn (int $minor): string => $this->money->format($minor, $order->currency));
    }

    /**
     * What an opened row of the list shows (§11.2) without going into the order: the lines in
     * short and the totals as the card's «Summary» has them — from the order's own columns, not a
     * relation more — the buyer and the recipient, the buyer's comment, the pinned note and the
     * latest of the feed, the lines of the blocks, the milestones with when. One order and three
     * relations whatever it holds — the authors of the notes in one more.
     *
     * @return array<string, mixed>
     */
    public function glance(Order $order, string $locale, mixed $reader): array
    {
        $order->loadMissing(['items', 'milestones', 'history']);
        $money = fn (int $minor): string => $this->money->format($minor, $order->currency);
        $notes = self::notes($order, Authors::people($reader, $order->history->pluck('admin_id')->all()));
        $recipient = $order->recipient;
        $marks = $this->milestones($order);

        return [
            'id' => $order->id,
            'items' => $order->items->whereNull('parent_id')->map(static fn (OrderItem $item): array => [
                'id' => $item->id,
                'name' => $item->nameIn($locale),
                'options' => $item->options_label,
                'qty' => $item->qty,
                'total' => $money($item->total),
            ])->values()->all(),
            'count' => self::count($order),
            'totals' => self::totals($order, $money),
            'paid' => $order->paid_at !== null,
            'awaiting_payment' => $order->awaiting_payment && $order->cancelled_at === null,
            // Where it came from, as the card's «Summary» marks it under «Placed»: `quick`, `panel`.
            'source' => $marks['placed']['source'] ?? null,
            'customer' => [
                'name' => $order->customerName(),
                'phone' => $order->customer_phone,
                'email' => $order->customer_email,
            ],
            'recipient' => is_array($recipient) && $recipient !== [] ? [
                'name' => trim(((string) ($recipient['first_name'] ?? '')).' '.((string) ($recipient['last_name'] ?? ''))),
                'phone' => $recipient['phone'] ?? null,
            ] : null,
            'comment' => $order->comment,
            'manager_note' => $order->manager_note,
            'last_note' => $notes === [] ? null : $notes[array_key_last($notes)],
            'blocks' => array_map(static fn (array $block): array => ['key' => $block['key'], 'summary' => $block['summary']], $this->blocks($order, $locale)),
            'milestones' => array_map(static fn (array $mark): string => $mark['at'], $marks),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public static function values(Order $order): array
    {
        $recipient = $order->recipient ?? [];

        return [
            'customer.first_name' => $order->customer_first_name,
            'customer.last_name' => $order->customer_last_name,
            'customer.phone' => $order->customer_phone,
            'customer.email' => $order->customer_email,
            'recipient.first_name' => $recipient['first_name'] ?? null,
            'recipient.last_name' => $recipient['last_name'] ?? null,
            'recipient.phone' => $recipient['phone'] ?? null,
            'comment' => $order->comment,
            'manager_note' => $order->manager_note,
        ];
    }

    /**
     * @param  callable(string): bool  $can
     * @return array<string, mixed>
     */
    private function order(Order $order, StatusChoices $statuses, string $locale, callable $can, mixed $reader): array
    {
        $currency = $order->currency;
        $money = fn (int $minor): string => $this->money->format($minor, $currency);
        $paid = $order->paid_at !== null;
        $cancelled = $order->cancelled_at !== null;
        $refundable = Balance::refundable($order);
        $tendersLeft = $order->tenders->sum(static fn (OrderTender $tender): int => $tender->amount - $tender->refunded_amount);
        $unitsLeft = $order->items->sum(static fn (OrderItem $item): int => $item->qty - $item->refunded_qty);
        $manage = $can('orders.manage');
        $people = self::people($order, $reader);
        // Messages sent again by hand (decision 99): only with the notifications module; «Send the
        // buyer a letter» says why it cannot when no channel writes to the buyer or there is no e-mail.
        $notifying = $manage && $this->channels->available();
        $notifyReason = $notifying ? $this->channels->buyerEvents($order)['reason'] : null;

        return [
            'id' => $order->id,
            'number' => $order->number,
            'placed_at' => $order->placed_at->toAtomString(),
            'locale' => $order->locale,
            'currency' => $currency,
            'exponent' => $this->money->exponent($currency),
            'guest' => $order->user_id === null,
            'user_id' => $order->user_id,
            'visitor' => $this->visitor($order),
            'status' => $statuses->find($order->status_id, $locale),
            'statuses' => $statuses->grouped($locale),
            'awaiting_payment' => $order->awaiting_payment && ! $cancelled,
            'payment_failed_at' => $paid ? null : $order->payment_failed_at?->toAtomString(),
            'milestones' => $this->milestones($order),
            'items' => $this->items($order, $locale, $money),
            'adjustments' => $order->adjustments
                ->filter(static fn (OrderAdjustment $adjustment): bool => $adjustment->item_id === null)
                ->map(static fn (OrderAdjustment $adjustment): array => [
                    'id' => $adjustment->id,
                    'label' => $adjustment->label,
                    'source' => $adjustment->source,
                    'amount' => $money($adjustment->amount),
                ])->values()->all(),
            'tenders' => $order->tenders->map(static fn (OrderTender $tender): array => [
                'id' => $tender->id,
                'label' => $tender->label,
                'source' => $tender->source,
                'amount' => $money($tender->amount),
                'amount_minor' => $tender->amount,
                'refunded' => $tender->refunded_amount > 0 ? $money($tender->refunded_amount) : null,
                'left_minor' => $tender->amount - $tender->refunded_amount,
            ])->values()->all(),
            'benefits' => $order->benefits->map(static fn ($benefit): array => [
                'key' => $benefit->key,
                'label' => $benefit->label,
            ])->values()->all(),
            'totals' => [
                ...self::totals($order, $money),
                'refundable' => $money(max(0, $refundable)),
                'refundable_minor' => max(0, $refundable),
            ],
            // «Lines: 2 · units: 5» of the side card: a set is one line, its parts are not counted.
            'count' => self::count($order),
            // The refunds beside «Refunded» of the totals, each with its reason and its reference;
            // one reversed by mistake stays, struck through, with why (decision 98).
            'refunds' => $order->refunds->map(static fn (OrderRefund $refund): array => [
                'id' => $refund->id,
                'at' => $refund->created_at?->toAtomString(),
                'amount' => $money($refund->amount),
                'reason' => $refund->reason,
                'reference' => $refund->reference,
                'reversed' => $refund->reversed() ? [
                    'at' => $refund->reversed_at?->toAtomString(),
                    'reason' => $refund->reversal_reason,
                ] : null,
            ])->values()->all(),
            'blocks' => $this->blocks($order, $locale),
            'timeline' => $this->timeline($order, $statuses, $locale, $people, $money),
            // The note pinned over the feed (decision 91): one standing line, changed in place.
            'manager_note' => $order->manager_note,
            // The feed «Notes» under the tabs (decision 90): the history's notes, oldest first.
            'notes' => self::notes($order, $people),
            // The block «Notifications» under the timeline (§8): null without the module.
            'notifications' => $this->channels->available() ? $this->channels->deliveries($order, $reader) : null,
            'actions' => [
                'status' => $manage,
                'paid' => $manage && ! $paid && ! $cancelled,
                'received' => $manage && $order->received_at === null && ! $cancelled,
                'cancel' => $manage && ! $cancelled && $order->refunded_at === null,
                'details' => $manage,
                'note' => $manage,
                'refund' => $can('orders.refund') && ($refundable > 0 || $tendersLeft > 0 || $unitsLeft > 0),
                // A top-up of a paid order (§36.3): the first payment is «Mark as paid».
                'payment' => $manage && $paid && ! $cancelled,
                // Reverse a refund record made by mistake (decision 98), each one not reversed yet.
                'refund_reverse' => $can('orders.refund'),
                // Linking to a visitor (§13.3): only with the visitors installed; finding one is
                // reading the customers too.
                'visitor' => $manage && VisitorOrders::installed(),
                'visitor_search' => $manage && VisitorOrders::installed() && $can('users.view'),
                'resend' => $notifying,
                'notify' => $notifying,
                'notify_reason' => $notifyReason !== null ? (string) __('webx-orders::panel.notify-refusals.'.$notifyReason) : null,
            ],
        ];
    }

    /**
     * The notes of the order (decision 90): the lines of its history of the kind `note` — a
     * manager's from the panel, an agent's `orders_note`, a module's «the parcel came back». A
     * line of the history never changes, so neither does a note. The author is the administrator
     * with their photograph where one is known; otherwise only the source, which the panel names.
     *
     * @param  array<int, array{name: string, avatar: string|null}>  $people
     * @return list<array{id: int, body: string, source: string, author: array{id: int, name: string, avatar: string|null}|null, created_at: string|null}>
     */
    private static function notes(Order $order, array $people): array
    {
        $notes = $order->history->filter(static fn (StatusChange $change): bool => $change->kind === StatusChange::NOTE);

        return $notes->map(static fn (StatusChange $change): array => [
            'id' => $change->id,
            'body' => (string) $change->note,
            'source' => $change->source,
            'author' => $change->admin_id !== null && isset($people[$change->admin_id])
                ? ['id' => $change->admin_id, ...$people[$change->admin_id]]
                : null,
            'created_at' => $change->created_at?->toAtomString(),
        ])->values()->all();
    }

    /**
     * The administrators who wrote anything on the order — its history, its milestones, its
     * refunds — with their photographs, in one query for the timeline and the notes together.
     *
     * @return array<int, array{name: string, avatar: string|null}>
     */
    private static function people(Order $order, mixed $reader): array
    {
        return Authors::people($reader, [
            ...$order->history->pluck('admin_id')->all(),
            ...$order->milestones->pluck('admin_id')->all(),
            ...$order->refunds->pluck('admin_id')->all(),
        ]);
    }

    /**
     * The lines of the order and their units, the parts of a set not counted apart, and the units
     * given back.
     *
     * @return array{lines: int, units: int, refunded_units: int}
     */
    private static function count(Order $order): array
    {
        $top = $order->items->whereNull('parent_id');

        return [
            'lines' => $top->count(),
            'units' => (int) $top->sum('qty'),
            'refunded_units' => (int) $top->sum('refunded_qty'),
        ];
    }

    /**
     * The visitor the order belongs to, as the account is now — one look-up; an id whose account
     * is gone is shown by the id alone.
     *
     * @return array{id: int, name: string|null, email: string|null}|null
     */
    private function visitor(Order $order): ?array
    {
        if ($order->user_id === null) {
            return null;
        }

        $visitor = Container::getInstance()->make(Visitors::class)->find($order->user_id);

        return ['id' => $order->user_id, 'name' => $visitor?->name(), 'email' => $visitor?->email];
    }

    /**
     * The totals as the buyer was charged, written in the order's currency; what is not there —
     * no discount, no tender, nothing given back — is null.
     *
     * «Paid» is what came in (§42.4): the first payment and the top-ups after it, read off the
     * history lines `payment` when the history is loaded. The balance line is there only when
     * it says something beyond «to pay»: `topup` — paid and owes more, `over` — money to give back.
     *
     * @param  Closure(int): string  $money
     * @return array<string, mixed>
     */
    private static function totals(Order $order, Closure $money): array
    {
        $balance = Balance::of($order);
        $kind = $balance->kind();
        $topups = $order->relationLoaded('history')
            ? (int) $order->history->sum(static fn (StatusChange $change): int => $change->kind === StatusChange::PAYMENT ? self::paymentLine($change->note)['amount'] : 0)
            : 0;

        return [
            'subtotal' => $money($order->subtotal),
            'adjustments' => $order->adjustments_total !== 0 ? $money($order->adjustments_total) : null,
            'total' => $money($order->total),
            'tenders' => $order->tenders_total !== 0 ? $money($order->tenders_total) : null,
            'payable' => $money($order->payable),
            'refunded' => $order->refunded_total > 0 ? $money($order->refunded_total) : null,
            'paid' => $order->paid_total > 0 ? $money($order->paid_total) : null,
            'paid_first' => $topups > 0 && $order->paid_total > $topups ? $money($order->paid_total - $topups) : null,
            'paid_topups' => $topups > 0 ? $money($topups) : null,
            'balance' => in_array($kind, ['topup', 'over'], true) ? [
                'kind' => $kind,
                'amount' => $money($kind === 'over' ? $balance->over : $balance->due),
                'amount_minor' => $kind === 'over' ? $balance->over : $balance->due,
            ] : null,
        ];
    }

    /**
     * The money, the reference and the note of a history line `payment` ({@see \WebxUi\Orders\Orders::paymentNote()}).
     *
     * @return array{amount: int, reference: string|null, note: string|null}
     */
    public static function paymentLine(?string $line): array
    {
        if ($line === null || preg_match('/^(\d+)(?: \[([^\]]*)\])?(?:: (.*))?$/s', $line, $match) !== 1) {
            return ['amount' => 0, 'reference' => null, 'note' => $line];
        }

        return [
            'amount' => (int) $match[1],
            'reference' => ($match[2] ?? '') !== '' ? $match[2] : null,
            'note' => ($match[3] ?? '') !== '' ? $match[3] : null,
        ];
    }

    /**
     * Each milestone the order has, with when and by whom (§5.1); the rest are not there.
     *
     * @return array<string, array{at: string, source: string, reference: string|null, note: string|null}>
     */
    private function milestones(Order $order): array
    {
        $marks = [];

        foreach ($order->milestones as $mark) {
            /** @var OrderMilestone $mark */
            $marks[$mark->milestone->value] = [
                'at' => $mark->at->toAtomString(),
                'source' => $mark->source,
                'reference' => $mark->reference,
                'note' => $mark->note,
            ];
        }

        return $marks;
    }

    /**
     * The lines as the cart had them: a set with its parts under it, the discounts of each line
     * beside it, what is left of it to give back.
     *
     * @param  callable(int): string  $money
     * @return list<array<string, mixed>>
     */
    private function items(Order $order, string $locale, callable $money): array
    {
        $lines = [];

        foreach ($order->items->whereNull('parent_id') as $item) {
            $lines[] = $this->line($order, $item, $locale, $money);
        }

        return $lines;
    }

    /**
     * @param  callable(int): string  $money
     * @return array<string, mixed>
     */
    private function line(Order $order, OrderItem $item, string $locale, callable $money): array
    {
        $item->setRelation('order', $order);
        $discounts = [];
        $children = [];

        foreach ($order->adjustments as $adjustment) {
            if ($adjustment->item_id === $item->id) {
                $discounts[] = ['label' => $adjustment->label, 'amount' => $money($adjustment->amount)];
            }
        }

        foreach ($order->items as $child) {
            if ($child->parent_id === $item->id) {
                $children[] = $this->line($order, $child, $locale, $money);
            }
        }

        return [
            'id' => $item->id,
            'name' => $item->nameIn($locale),
            'sku' => $item->sku,
            'options' => $item->options_label,
            'image' => $item->image,
            'label' => $item->label,
            'price' => $money($item->price),
            'qty' => $item->qty,
            'refunded_qty' => $item->refunded_qty,
            'total' => $money($item->total),
            'discounts' => $discounts,
            'children' => $children,
        ];
    }

    /**
     * Each block with the line of its owner, and the block itself for the card without the
     * owner's tab — folded JSON under the line (§11.3).
     *
     * @return list<array{key: string, summary: string|null, value: array<string, mixed>}>
     */
    private function blocks(Order $order, string $locale): array
    {
        $blocks = [];

        foreach (['delivery' => $order->delivery, 'payment' => $order->payment] as $key => $value) {
            if (is_array($value) && $value !== []) {
                $blocks[] = ['key' => $key, 'summary' => $this->list->summary($key, $value, $locale), 'value' => $value];
            }
        }

        return $blocks;
    }

    /**
     * One timeline out of three tables, by time (§8): moves, notes and the rest of the history;
     * the milestones; the refunds. The author is the administrator's name where one is known,
     * otherwise the source as the panel names it.
     *
     * @param  array<int, array{name: string, avatar: string|null}>  $people
     * @param  callable(int): string  $money
     * @return list<array<string, mixed>>
     */
    private function timeline(Order $order, StatusChoices $statuses, string $locale, array $people, callable $money): array
    {
        $names = array_map(static fn (array $person): string => $person['name'], $people);
        $who = static fn (?int $adminId, string $source): array => [
            'source' => $source,
            'admin' => $adminId !== null ? ($names[$adminId] ?? null) : null,
        ];
        $lines = [];

        foreach ($order->history as $change) {
            /** @var StatusChange $change */
            $line = [
                'key' => 'history-'.$change->id,
                'kind' => $change->kind,
                'at' => $change->created_at?->toAtomString(),
                ...$who($change->admin_id, $change->source),
                'note' => $change->note,
            ];

            if ($change->kind === StatusChange::STATUS) {
                $from = $change->from_status_id !== null ? $statuses->find($change->from_status_id, $locale) : null;
                $to = $change->to_status_id !== null ? $statuses->find($change->to_status_id, $locale) : null;
                $line['from'] = $from['name'] ?? null;
                $line['to'] = $to['name'] ?? null;
                $line['to_color'] = $to['color'] ?? null;
                $line['public'] = $change->from_public_id !== $change->to_public_id ? ($to['public']['name'] ?? null) : null;
            }

            // «linked: Anna <anna@…>» / «unlinked: …» — the words are the panel's, the name the moment's.
            if ($change->kind === StatusChange::VISITOR && $change->note !== null && preg_match('/^(linked|unlinked): (.*)$/s', $change->note, $match) === 1) {
                $line['kind'] = 'visitor-'.$match[1];
                $line['note'] = $match[2];
            }

            // «refund#12: why» — the record's money from the record itself, the reason as a note.
            if ($change->kind === StatusChange::REFUND_REVERSED && $change->note !== null && preg_match('/^refund#(\d+)(?:: (.*))?$/s', $change->note, $match) === 1) {
                $reversed = $order->refunds->firstWhere('id', (int) $match[1]);
                $line['refund_id'] = (int) $match[1];
                $line['amount'] = $reversed instanceof OrderRefund ? $money($reversed->amount) : null;
                $line['amount_minor'] = $reversed instanceof OrderRefund ? $reversed->amount : null;
                $line['note'] = ($match[2] ?? '') !== '' ? $match[2] : null;
            }

            // «orders.placed: buyer@example.com» — the event in the panel's words, the addresses as they went.
            if ($change->kind === StatusChange::NOTIFICATION_SENT && $change->note !== null && preg_match('/^([a-z_.]+): (.*)$/s', $change->note, $match) === 1) {
                $line['event'] = $match[1];
                $line['event_label'] = str_starts_with($match[1], 'orders.') ? (string) __('webx-orders::notifications.events.'.substr($match[1], strlen('orders.'))) : $match[1];
                $line['note'] = $match[2] !== '' ? $match[2] : null;
            }

            // «1500 [bank-77]: by card» — a top-up's money, its reference and its note (§36.3).
            if ($change->kind === StatusChange::PAYMENT) {
                $payment = self::paymentLine($change->note);
                $line['amount'] = $money($payment['amount']);
                $line['amount_minor'] = $payment['amount'];
                $line['reference'] = $payment['reference'];
                $line['note'] = $payment['note'];
            }

            if ($change->kind === StatusChange::MILESTONE_REMOVED && $change->note !== null && ($taken = Milestone::tryFrom($change->note)) !== null) {
                $line['milestone'] = Consequences::milestone($taken);
                $line['note'] = null;
            }

            if ($change->kind === StatusChange::DETAILS && $change->note !== null) {
                $line['note'] = implode(', ', array_map(
                    static fn (string $field): string => (string) __('webx-orders::module.fields.'.$field),
                    explode(',', $change->note),
                ));
            }

            $lines[] = $line;
        }

        foreach ($order->milestones as $mark) {
            /** @var OrderMilestone $mark */
            $lines[] = [
                'key' => 'milestone-'.$mark->id,
                'kind' => 'milestone',
                'at' => $mark->at->toAtomString(),
                ...$who($mark->admin_id, $mark->source),
                'milestone' => Consequences::milestone($mark->milestone),
                'reference' => $mark->reference,
                'note' => $mark->note,
            ];
        }

        foreach ($order->refunds as $refund) {
            /** @var OrderRefund $refund */
            $lines[] = [
                'key' => 'refund-'.$refund->id,
                'kind' => 'refund',
                'at' => $refund->created_at?->toAtomString(),
                ...$who($refund->admin_id, $refund->source),
                'amount' => $money($refund->amount),
                'amount_minor' => $refund->amount,
                'units' => array_sum(array_map(static fn (array $item): int => (int) $item['qty'], $refund->items)),
                'reference' => $refund->reference,
                'note' => $refund->reason,
                'refund_id' => $refund->id,
                // Reversed later by mistake (decision 98): its own line says when and why.
                'reversed' => $refund->reversed(),
            ];
        }

        // The milestone «placed» and the first line of the history share a moment; the mark
        // reads first. Ties beyond that keep the order they were written in.
        $rank = static fn (array $line): int => $line['kind'] === 'milestone' && ($line['milestone']['key'] ?? null) === Milestone::Placed->value ? 0 : 1;
        usort($lines, static fn (array $a, array $b): int => [(string) $a['at'], $rank($a)] <=> [(string) $b['at'], $rank($b)]);

        return $lines;
    }
}
