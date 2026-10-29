<?php

declare(strict_types=1);

namespace WebxUi\Orders\Mcp;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Events\Dispatcher as EventDispatcher;
use Illuminate\Support\Carbon;
use WebxUi\Cart\Money;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Rehearsal;
use WebxUi\Mcp\Tool;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Events\OrderPaid;
use WebxUi\Orders\Events\OrderReceived;
use WebxUi\Orders\Http\Controllers\Panel\MassController;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderAdjustment;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderRefund;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\OrderTender;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\OrderDetails;
use WebxUi\Orders\Orders;
use WebxUi\Orders\OrderStatuses;
use WebxUi\Orders\Panel\Consequences;
use WebxUi\Orders\Panel\OrderCard;
use WebxUi\Orders\Panel\OrderList;
use WebxUi\Orders\Panel\OrdersModule;
use WebxUi\Orders\Panel\StatusBatch;
use WebxUi\Orders\Panel\StatusChoices;
use WebxUi\Orders\RefundRefused;
use WebxUi\Orders\RefundRequest;
use WebxUi\Orders\RefundResult;
use WebxUi\Orders\Refusal;
use WebxUi\Orders\StatusMoveRefused;

/**
 * The orders for an agent (spec §12): reading them, and working them the way the card does.
 *
 * Every change is a call of `Orders` (decision 18) with the source `mcp`, and a dry run is the
 * service's own — the same {@see Consequences} the panel's dialogs show, the same
 * {@see StatusBatch} as the panel's selection — so the agent and the manager cannot be told two
 * stories. Where the service has no dry run of its own (the contacts), the real call is rehearsed
 * and rolled back ({@see Rehearsal}).
 *
 * The buyer's contacts are in the answers: `orders.view` is the access to them (§11.1). Money is
 * in minor units with a `*_formatted` string beside it.
 */
final readonly class OrdersTools
{
    use AnswersAgents;

    private const int PER_PAGE = 20;

    private const int PER_PAGE_MAX = 100;

    /** What reads an order — whoever may work it or give money back reads it too, as in the panel. */
    private const array READ = [OrdersModule::VIEW, OrdersModule::MANAGE, OrdersModule::REFUND];

    private const string SOURCE = 'mcp';

    public function __construct(
        private Orders $orders,
        private OrderStatuses $statuses,
        private OrderList $list,
        private OrderCard $card,
        private Consequences $consequences,
        private StatusBatch $batch,
        private Money $money,
        private Validation $validation,
        private Dispatcher $events,
    ) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $order = [
            'type' => ['string', 'integer'],
            'description' => 'The order: its number (`1024`, `A-001024`) or its id; a number wins when both match.',
        ];
        $flag = static fn (string $what): array => ['type' => 'boolean', 'description' => $what];

        return Arguments::strictAll([
            Tool::read(
                'list',
                'Orders, newest first, twenty a page: number, when placed, the buyer (name, phone, e-mail — personal data, '
                .'which orders.view opens), the first line and how many more, total and payable in minor units with '
                .'`*_formatted`, the internal status with its public one, the milestones and the flags «awaiting payment» '
                .'and «payment failed», the delivery and payment lines. `counts` — how many each view holds under the same '
                .'filters. Filters as in the panel; `search` finds a number, a phone in any spelling, an e-mail, a name in '
                .'either order.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'view' => ['type' => 'string', 'enum' => OrderList::VIEWS, 'description' => 'new — in the status new orders come into; awaiting — awaiting payment; failed — a payment did not go through; all (default).'],
                    'search' => ['type' => 'string'],
                    'status' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Internal status ids, any of them (orders_statuses_list).'],
                    'public' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Public status codes, any of them: `shipped`.'],
                    'paid' => $flag('Paid (true) or not (false).'),
                    'received' => $flag('Received or not.'),
                    'cancelled' => $flag('Cancelled or not.'),
                    'refunded' => $flag('With a refund recorded or without.'),
                    'awaiting' => $flag('Awaiting payment (and not cancelled) or not.'),
                    'failed' => $flag('A payment try failed or not.'),
                    'visitor' => ['type' => 'string', 'enum' => ['guest', 'visitor'], 'description' => 'Placed by a guest or by a signed-in visitor.'],
                    'delivery' => ['type' => 'string', 'description' => 'The delivery method as the block keeps it.'],
                    'payment' => ['type' => 'string', 'description' => 'The payment method as the block keeps it.'],
                    'from' => ['type' => 'string', 'description' => 'Placed on this day or later, YYYY-MM-DD.'],
                    'to' => ['type' => 'string', 'description' => 'Placed on this day or earlier, YYYY-MM-DD.'],
                    'sort' => ['type' => 'string', 'enum' => ['-placed_at', 'placed_at', '-total', 'total', '-number', 'number'], 'description' => '`-` for the largest first; -placed_at by default.'],
                    'page' => ['type' => 'integer', 'description' => '1 by default.'],
                    'per_page' => ['type' => 'integer', 'description' => '20 by default, at most 100.'],
                ]],
                permission: self::READ,
            ),

            Tool::read(
                'get',
                'One order in full: the buyer and the recipient (personal data), the lines as a tree (a set\'s parts under it) '
                .'with their discounts and what was given back of them, the adjustments, tenders and benefits, the totals, the '
                .'delivery and payment blocks, the internal and public status, every milestone with when, by whom and its '
                .'reference, and the refunds. Money in minor units with `*_formatted`.',
                fn (array $arguments): array => $this->get($arguments),
                ['properties' => ['order' => $order], 'required' => ['order']],
                permission: self::READ,
            ),

            Tool::read(
                'history',
                'The timeline of an order, oldest first — the card\'s tab «History»: moves between statuses, milestones, '
                .'refunds, notes, changed contacts and blocks, each with when, the source (panel, mcp, api, checkout, '
                .'status#<id>) and the administrator\'s name where known.',
                fn (array $arguments, ?Authenticatable $caller = null): array => $this->history($arguments, $caller),
                ['properties' => ['order' => $order], 'required' => ['order']],
                permission: self::READ,
            ),

            Tool::read(
                'summary',
                'The orders at a glance: how many stand in each public status and in each internal one, how many await '
                .'payment, how many have a failed payment, how many are paid while still standing in a status new orders '
                .'come into («paid, the status waits»), and for the last 1, 7 and 30 days, by currency: orders placed, paid, '
                .'and the revenue — what was paid less what was given back.',
                fn (): array => $this->summary(),
                permission: self::READ,
            ),

            Tool::mutating(
                'set_status',
                'Move an order (`order`) or several (`numbers`, up to 200) to an internal status, with a note for the history. '
                .'A status of another public group shows the buyer a new status and fires its channels; a status with a '
                .'milestone sets it. Into a status that cancels, `note` is the reason and required. `dry_run` answers what the '
                .'panel\'s dialog shows: for one order — whether it moves, what the buyer sees, the milestone, the channels; '
                .'for several — how many move, stay, are refused and why, how many buyers see a new status and how many '
                .'messages go. Nothing changes on a dry run.',
                fn (array $arguments): array => $this->setStatus($arguments),
                ['properties' => [
                    'order' => $order,
                    'numbers' => ['type' => 'array', 'items' => ['type' => ['string', 'integer']], 'description' => 'Several orders by number or id, instead of `order`.'],
                    'status' => ['type' => ['integer', 'string'], 'description' => 'The internal status: its id or its name in any language (orders_statuses_list).'],
                    'note' => ['type' => 'string', 'description' => 'A line for the history; the reason, into a status that cancels.'],
                ], 'required' => ['status']],
                permission: OrdersModule::MANAGE,
            ),

            Tool::mutating(
                'mark',
                'Set the milestone «paid» or «received» by hand, once, with the operation\'s reference and a note. A repeat '
                .'changes nothing and answers the first mark. A cancelled order is refused. `dry_run` answers whether it would '
                .'be set and which events, and listeners by class, would run.',
                fn (array $arguments): array => $this->mark($arguments),
                ['properties' => [
                    'order' => $order,
                    'milestone' => ['type' => 'string', 'enum' => [Milestone::Paid->value, Milestone::Received->value]],
                    'reference' => ['type' => 'string', 'description' => 'The bank\'s, the ledger\'s or the carrier\'s number of it.'],
                    'note' => ['type' => 'string'],
                ], 'required' => ['order', 'milestone']],
                permission: OrdersModule::MANAGE,
            ),

            Tool::mutating(
                'cancel',
                'Cancel an order with its reason: the milestone «cancelled», and the tenders left on it (wallet, gift card) go '
                .'back. Money is not given back — a paid order answers how much is left to refund, and orders_refund records '
                .'it. `dry_run` answers whether it was paid, the money left, the tenders that go back.',
                fn (array $arguments): array => $this->cancel($arguments),
                ['properties' => [
                    'order' => $order,
                    'reason' => ['type' => 'string', 'description' => 'Why — it goes into the history.'],
                ], 'required' => ['order', 'reason']],
                permission: OrdersModule::MANAGE,
            ),

            Tool::mutating(
                'refund',
                'Record money given back outside the site (a bank transfer, cash) — no gateway is called. Each part is yours to '
                .'split and is checked against what is left: `amount` of money, each tender (`tenders`: tender id → amount), '
                .'the units of each line returned (`items`: line id → qty); orders_get has the ids. When money and every '
                .'tender are back, the milestone «refunded» is set. `dry_run` answers what is left of each part and whether '
                .'this refund would be full, or why it is refused.',
                fn (array $arguments): array => $this->refund($arguments),
                ['properties' => [
                    'order' => $order,
                    'amount' => ['type' => 'integer', 'description' => 'Money back, minor units; 0 — tenders or units only.'],
                    'tenders' => ['type' => 'object', 'description' => 'Tender id → amount back, minor units.', 'additionalProperties' => ['type' => 'integer']],
                    'items' => ['type' => 'object', 'description' => 'Line id → units returned.', 'additionalProperties' => ['type' => 'integer']],
                    'reference' => ['type' => 'string'],
                    'reason' => ['type' => 'string'],
                ], 'required' => ['order']],
                permission: OrdersModule::REFUND,
            ),

            Tool::mutating(
                'update',
                'Change the buyer\'s contacts (`customer`: first_name, last_name, phone, e-mail — the ones sent, the rest '
                .'stay), the recipient (`recipient`: first_name, last_name, phone; null — the buyer takes it), the buyer\'s '
                .'comment, the manager note. Lines, money and milestones never change. The history and the panel\'s journal '
                .'keep what was changed. `dry_run` answers the fields that would change.',
                fn (array $arguments): array => $this->update($arguments),
                ['properties' => [
                    'order' => $order,
                    'customer' => ['type' => 'object', 'properties' => [
                        'first_name' => ['type' => 'string'], 'last_name' => ['type' => ['string', 'null']],
                        'phone' => ['type' => ['string', 'null']], 'email' => ['type' => ['string', 'null']],
                    ]],
                    'recipient' => ['type' => ['object', 'null'], 'properties' => [
                        'first_name' => ['type' => 'string'], 'last_name' => ['type' => ['string', 'null']], 'phone' => ['type' => ['string', 'null']],
                    ]],
                    'comment' => ['type' => ['string', 'null']],
                    'manager_note' => ['type' => ['string', 'null']],
                ], 'required' => ['order']],
                permission: OrdersModule::MANAGE,
            ),

            Tool::mutating(
                'note',
                'Write a line into the order\'s history — «called, the buyer will come on Friday». The buyer does not see it.',
                fn (array $arguments): array => $this->note($arguments),
                ['properties' => ['order' => $order, 'text' => ['type' => 'string']], 'required' => ['order', 'text']],
                permission: OrdersModule::MANAGE,
            ),
        ], 'orders_');
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $filters = $arguments;

        // The panel sends ids as one comma-separated string; the agent sends lists, and codes.
        if (isset($arguments['status'])) {
            $filters['status'] = implode(',', array_map(intval(...), (array) $arguments['status']));
        }

        if (isset($arguments['public'])) {
            $filters['public'] = implode(',', $this->publicIds((array) $arguments['public']));
        }

        $perPage = (int) ($arguments['per_page'] ?? self::PER_PAGE);

        if ($perPage < 1 || $perPage > self::PER_PAGE_MAX) {
            throw new ToolFailure('`per_page` is from 1 to '.self::PER_PAGE_MAX.'.');
        }

        $valid = $this->attempt(fn (): array => $this->validation->make($filters, OrderList::rules())->validate());
        $locale = app()->getLocale();
        $statuses = new StatusChoices;
        $page = $this->list->page($valid, $perPage, (int) ($valid['page'] ?? 1));

        return [
            'orders' => array_map(fn (Order $one): array => $this->row($one, $statuses, $locale), $page->items()),
            'counts' => $this->list->counts($valid),
            'page' => $page->currentPage(),
            'pages' => $page->lastPage(),
            'total' => $page->total(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $order->load(['items', 'adjustments', 'tenders', 'benefits', 'milestones', 'refunds']);
        $locale = app()->getLocale();
        $currency = $order->currency;
        $money = fn (string $key, int $minor): array => $this->amount($key, $minor, $currency);

        return [
            'id' => $order->id,
            'number' => $order->number,
            'placed_at' => $order->placed_at->toAtomString(),
            'locale' => $order->locale,
            'currency' => $currency,
            'user_id' => $order->user_id,
            'customer' => [
                'first_name' => $order->customer_first_name,
                'last_name' => $order->customer_last_name,
                'phone' => $order->customer_phone,
                'email' => $order->customer_email,
            ],
            'recipient' => $order->recipient,
            'comment' => $order->comment,
            'manager_note' => $order->manager_note,
            'status' => (new StatusChoices)->find($order->status_id, $locale),
            'awaiting_payment' => $order->awaiting_payment && $order->cancelled_at === null,
            'payment_failed_at' => $order->paid_at === null ? $order->payment_failed_at?->toAtomString() : null,
            'milestones' => $order->milestones->mapWithKeys(static fn (OrderMilestone $mark): array => [$mark->milestone->value => [
                'at' => $mark->at->toAtomString(),
                'source' => $mark->source,
                'admin_id' => $mark->admin_id,
                'reference' => $mark->reference,
                'note' => $mark->note,
            ]])->all(),
            'items' => $this->lines($order, null, $locale, $money),
            'adjustments' => $order->adjustments
                ->filter(static fn (OrderAdjustment $adjustment): bool => $adjustment->item_id === null)
                ->map(static fn (OrderAdjustment $adjustment): array => [
                    'id' => $adjustment->id,
                    'label' => $adjustment->label,
                    'source' => $adjustment->source,
                    ...$money('amount', $adjustment->amount),
                ])->values()->all(),
            'tenders' => $order->tenders->map(static fn (OrderTender $tender): array => [
                'id' => $tender->id,
                'label' => $tender->label,
                'source' => $tender->source,
                ...$money('amount', $tender->amount),
                ...$money('refunded_amount', $tender->refunded_amount),
            ])->values()->all(),
            'benefits' => $order->benefits->map(static fn ($benefit): array => ['key' => $benefit->key, 'label' => $benefit->label])->values()->all(),
            'totals' => [
                ...$money('subtotal', $order->subtotal),
                ...$money('adjustments_total', $order->adjustments_total),
                ...$money('total', $order->total),
                ...$money('tenders_total', $order->tenders_total),
                ...$money('payable', $order->payable),
                ...$money('refunded_total', $order->refunded_total),
                ...$money('refundable', max(0, $order->payable - $order->refunded_total)),
            ],
            'blocks' => [
                'delivery' => $order->delivery === null ? null : ['summary' => $this->list->summary('delivery', $order->delivery, $locale), 'value' => $order->delivery],
                'payment' => $order->payment === null ? null : ['summary' => $this->list->summary('payment', $order->payment, $locale), 'value' => $order->payment],
            ],
            'refunds' => $order->refunds->map(static fn (OrderRefund $refund): array => [
                'id' => $refund->id,
                ...$money('amount', $refund->amount),
                'tenders' => $refund->tenders,
                'items' => $refund->items,
                'method' => $refund->method,
                'reference' => $refund->reference,
                'reason' => $refund->reason,
                'source' => $refund->source,
                'at' => $refund->created_at?->toAtomString(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function history(array $arguments, ?Authenticatable $caller): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $lines = $this->card->history($order, new StatusChoices, app()->getLocale(), $caller);

        return [
            'number' => $order->number,
            // A refund's money as every amount here is: an integer and the string beside it.
            'timeline' => array_map(static function (array $line): array {
                if ($line['kind'] === 'refund') {
                    $line['amount_formatted'] = $line['amount'];
                    $line['amount'] = $line['amount_minor'];
                    unset($line['amount_minor']);
                }

                unset($line['key']);

                return $line;
            }, $lines),
        ];
    }

    /**
     * Counted by `GROUP BY` and conditional sums over the indexed columns — no order is loaded.
     *
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        $locale = app()->getLocale();
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

        $waiting = $initial === [] ? '0 = 1' : 'status_id in ('.implode(',', array_fill(0, count($initial), '?')).')';
        $flags = Order::query()->toBase()->selectRaw(
            'sum(case when awaiting_payment = ? and cancelled_at is null then 1 else 0 end) as awaiting,'
            .' sum(case when awaiting_payment = ? and cancelled_at is null and payment_failed_at is not null then 1 else 0 end) as failed,'
            ." sum(case when paid_at is not null and cancelled_at is null and {$waiting} then 1 else 0 end) as paid_waiting",
            [true, true, ...$initial],
        )->first();

        $now = Carbon::now();
        $since = [1 => $now->copy()->subDay(), 7 => $now->copy()->subDays(7), 30 => $now->copy()->subDays(30)];
        $select = ['currency'];
        $bindings = [];

        foreach ($since as $days => $from) {
            $select[] = "sum(case when placed_at >= ? then 1 else 0 end) as placed_{$days}";
            $select[] = "sum(case when placed_at >= ? and paid_at is not null then 1 else 0 end) as paid_{$days}";
            $select[] = "sum(case when placed_at >= ? and paid_at is not null then payable - refunded_total else 0 end) as revenue_{$days}";
            array_push($bindings, $from, $from, $from);
        }

        $rows = Order::query()->toBase()
            ->selectRaw(implode(', ', $select), $bindings)
            ->where('placed_at', '>=', $since[30])
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $periods = [];

        foreach (array_keys($since) as $days) {
            $periods[] = [
                'days' => $days,
                'by_currency' => $rows->map(fn (object $row): array => [
                    'currency' => (string) $row->currency,
                    'placed' => (int) $row->{"placed_{$days}"},
                    'paid' => (int) $row->{"paid_{$days}"},
                    ...$this->amount('revenue', (int) $row->{"revenue_{$days}"}, (string) $row->currency),
                ])->values()->all(),
            ];
        }

        return [
            'public' => $public,
            'internal' => $internal,
            'awaiting_payment' => (int) ($flags->awaiting ?? 0),
            'payment_failed' => (int) ($flags->failed ?? 0),
            'paid_status_waits' => (int) ($flags->paid_waiting ?? 0),
            'periods' => $periods,
        ];
    }

    /**
     * One order answers the card's dialog; several answer the selection's — {@see StatusBatch}.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function setStatus(array $arguments): array
    {
        $dryRun = $this->dryRun($arguments);
        $note = isset($arguments['note']) ? (string) $arguments['note'] : null;
        $statuses = new StatusChoices;
        $target = $this->status($arguments['status'] ?? null, $statuses);
        $locale = app()->getLocale();

        if (isset($arguments['numbers'])) {
            if (isset($arguments['order'])) {
                throw new ToolFailure('Send `order` or `numbers`, not both.');
            }

            $numbers = array_values((array) $arguments['numbers']);

            if ($numbers === [] || count($numbers) > MassController::MAX) {
                throw new ToolFailure('`numbers` holds from 1 to '.MassController::MAX.' orders.');
            }

            $orders = array_map($this->order(...), $numbers);
            usort($orders, static fn (Order $a, Order $b): int => $a->id <=> $b->id);
            $tally = $this->attempt(fn (): array => $this->batch->move($orders, $target, self::SOURCE, $note, $dryRun));

            return [...$tally, 'status' => $statuses->find($target->id, $locale)];
        }

        $order = $this->order($arguments['order'] ?? null);

        try {
            $move = $this->attempt(fn () => $this->orders->moveTo($order, $target, self::SOURCE, $note, $dryRun));
        } catch (StatusMoveRefused $refused) {
            throw new ToolFailure($this->refusal($order, $refused->reason));
        }

        return [
            'number' => $order->number,
            ...$this->consequences->move($move, $statuses, $locale),
            'status' => $statuses->find($target->id, $locale),
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function mark(array $arguments): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $milestone = Milestone::tryFrom((string) ($arguments['milestone'] ?? ''));

        if (! in_array($milestone, [Milestone::Paid, Milestone::Received], true)) {
            throw new ToolFailure('`milestone` is paid or received; the others come with the order, a cancellation or a refund.');
        }

        $reference = isset($arguments['reference']) ? (string) $arguments['reference'] : null;
        $note = isset($arguments['note']) ? (string) $arguments['note'] : null;

        if ($this->dryRun($arguments)) {
            // The checks of mark(), read without the lock: nothing is written either way.
            if ($order->cancelled_at !== null) {
                throw new ToolFailure($this->refusal($order, 'cancelled'));
            }

            $first = OrderMilestone::query()->where('order_id', $order->id)->where('milestone', $milestone->value)->first();
            $event = $milestone === Milestone::Paid ? OrderPaid::class : OrderReceived::class;

            return [
                'number' => $order->number,
                'milestone' => Consequences::milestone($milestone),
                'outcome' => $first === null ? 'marked' : 'already_marked',
                'first' => $first === null ? null : ['at' => $first->at->toAtomString(), 'source' => $first->source, 'reference' => $first->reference],
                'events' => $first === null ? [$event] : [],
                'listeners' => $first === null ? $this->listeners($event) : [],
                'dry_run' => true,
            ];
        }

        try {
            $mark = $this->orders->mark($order, $milestone, self::SOURCE, reference: $reference, note: $note);
        } catch (Refusal $refused) {
            throw new ToolFailure($this->refusal($order, $refused->reason));
        }

        return [
            'number' => $order->number,
            'milestone' => Consequences::milestone($milestone),
            'outcome' => $mark->outcome,
            'at' => $mark->at->toAtomString(),
            'source' => $mark->source,
            'reference' => $mark->reference,
            'note' => $mark->note,
            'dry_run' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function cancel(array $arguments): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $dryRun = $this->dryRun($arguments);

        try {
            $mark = $this->attempt(fn () => $this->orders->cancel($order, self::SOURCE, isset($arguments['reason']) ? (string) $arguments['reason'] : null, $dryRun));
        } catch (Refusal $refused) {
            throw new ToolFailure($this->refusal($order, $refused->reason));
        }

        $said = $this->consequences->cancel($mark, $order->currency, $this->money);

        return [
            'number' => $order->number,
            'outcome' => $said['outcome'],
            'was_paid' => $said['was_paid'],
            ...$this->amount('money_to_refund', $mark->moneyToRefund, $order->currency),
            'tenders' => array_map(fn ($tender): array => [
                'id' => $tender->id,
                'label' => $tender->label,
                ...$this->amount('amount', $tender->amount, $order->currency),
            ], $mark->tendersReturned),
            'dry_run' => $dryRun,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function refund(array $arguments): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $dryRun = $this->dryRun($arguments);
        $parts = static function (mixed $value, string $name): array {
            if ($value === null) {
                return [];
            }

            if (! is_array($value)) {
                throw new ToolFailure("`{$name}` is an object of id → number.");
            }

            $kept = [];

            foreach ($value as $id => $number) {
                if (! is_numeric($id) || ! is_int($number) || $number < 0) {
                    throw new ToolFailure("`{$name}` is an object of id → a whole number of zero or more.");
                }

                if ($number > 0) {
                    $kept[(int) $id] = $number;
                }
            }

            return $kept;
        };

        $amount = $arguments['amount'] ?? 0;

        if (! is_int($amount) || $amount < 0) {
            throw new ToolFailure('`amount` is a whole number of minor units, zero or more.');
        }

        $request = new RefundRequest(
            $amount,
            $parts($arguments['tenders'] ?? null, 'tenders'),
            $parts($arguments['items'] ?? null, 'items'),
            isset($arguments['reference']) ? (string) $arguments['reference'] : null,
            isset($arguments['reason']) ? (string) $arguments['reason'] : null,
            self::SOURCE,
        );

        if ($dryRun) {
            return ['number' => $order->number, ...$this->refundAnswer($this->orders->refund($order, $request, true), $order->currency)];
        }

        try {
            $result = $this->orders->refund($order, $request);
        } catch (RefundRefused $refused) {
            throw new ToolFailure("Order {$order->number}: ".$this->refundRefusal($refused->result, $order->currency));
        }

        return ['number' => $order->number, ...$this->refundAnswer($result, $order->currency), 'refund_id' => $result->refundId];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function update(array $arguments): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $patch = new OrderDetails;
        $current = OrderCard::values($order);
        $text = static fn (mixed $value): ?string => is_scalar($value) ? (string) $value : null;

        if (array_key_exists('customer', $arguments)) {
            $customer = $arguments['customer'];

            if (! is_array($customer) || $customer === []) {
                throw new ToolFailure('`customer` is an object of first_name, last_name, phone, email.');
            }

            Arguments::refuseUnknown($customer, ['first_name', 'last_name', 'phone', 'email'], '`customer`');
            $pick = static fn (string $key): ?string => array_key_exists($key, $customer) ? $text($customer[$key]) : $current['customer.'.$key];
            $patch = $patch->customer(new Contact((string) $pick('first_name'), $pick('last_name'), $pick('phone'), $pick('email')));
        }

        if (array_key_exists('recipient', $arguments)) {
            $recipient = $arguments['recipient'];

            if ($recipient !== null && ! is_array($recipient)) {
                throw new ToolFailure('`recipient` is an object of first_name, last_name, phone — or null: the buyer takes it.');
            }

            if ($recipient === null) {
                $patch = $patch->recipient(null);
            } else {
                Arguments::refuseUnknown($recipient, ['first_name', 'last_name', 'phone'], '`recipient`');
                $pick = static fn (string $key): ?string => array_key_exists($key, $recipient) ? $text($recipient[$key]) : $current['recipient.'.$key];
                $patch = $patch->recipient(new Contact((string) $pick('first_name'), $pick('last_name'), $pick('phone')));
            }
        }

        foreach (['comment' => 'comment', 'manager_note' => 'managerNote'] as $key => $method) {
            if (array_key_exists($key, $arguments)) {
                $patch = $patch->{$method}($text($arguments[$key]));
            }
        }

        if ($patch->isEmpty()) {
            throw new ToolFailure('Nothing to change: send `customer`, `recipient`, `comment` or `manager_note`.');
        }

        $work = function () use ($order, $patch, $current): array {
            $this->orders->updateDetails($order, $patch, self::SOURCE);
            $after = OrderCard::values($order->refresh());

            return [
                'changed' => array_keys(array_diff_assoc(array_map(strval(...), $after), array_map(strval(...), $current))),
                'values' => $after,
            ];
        };

        $dryRun = $this->dryRun($arguments);
        $answer = $this->attempt(fn (): array => $dryRun ? Rehearsal::run($work, $order->getConnectionName()) : $work());

        return ['number' => $order->number, ...$answer, 'dry_run' => $dryRun];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function note(array $arguments): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $text = trim((string) ($arguments['text'] ?? ''));

        if ($text === '') {
            throw new ToolFailure('`text` says something.');
        }

        if (mb_strlen($text) > 1000) {
            throw new ToolFailure('`text` is at most 1000 characters.');
        }

        $dryRun = $this->dryRun($arguments);

        if (! $dryRun) {
            $this->orders->note($order, $text, self::SOURCE);
        }

        return ['number' => $order->number, 'note' => $text, 'dry_run' => $dryRun];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Order $order, StatusChoices $statuses, string $locale): array
    {
        $row = $this->list->row($order, $statuses, $locale);

        return [
            'id' => $order->id,
            'number' => $order->number,
            'placed_at' => $row['placed_at'],
            'customer' => [
                'first_name' => $order->customer_first_name,
                'last_name' => $order->customer_last_name,
                'phone' => $order->customer_phone,
                'email' => $order->customer_email,
            ],
            'user_id' => $order->user_id,
            'items' => $row['items'],
            'currency' => $order->currency,
            ...$this->amount('total', $order->total, $order->currency),
            ...$this->amount('payable', $order->payable, $order->currency),
            'status' => $row['status'],
            'awaiting_payment' => $row['awaiting_payment'],
            'payment_failed' => $row['payment_failed'],
            'paid_at' => $row['paid_at'],
            'received_at' => $row['received_at'],
            'cancelled_at' => $row['cancelled_at'],
            'refunded' => $row['refunded'],
            'delivery' => $row['delivery'],
            'payment' => $row['payment'],
        ];
    }

    /**
     * The lines under a parent (none — the roots), each with its discounts and its parts.
     *
     * @param  Closure(string, int): array<string, int|string>  $money
     * @return list<array<string, mixed>>
     */
    private function lines(Order $order, ?int $parent, string $locale, Closure $money): array
    {
        $lines = [];

        foreach ($order->items as $item) {
            /** @var OrderItem $item */
            if ($item->parent_id !== $parent) {
                continue;
            }

            $item->setRelation('order', $order);
            $lines[] = [
                'id' => $item->id,
                'type' => $item->type,
                'ref' => $item->ref_id,
                'name' => $item->nameIn($locale),
                'sku' => $item->sku,
                'options' => $item->options_label,
                'label' => $item->label,
                ...$money('price', $item->price),
                'qty' => $item->qty,
                'refunded_qty' => $item->refunded_qty,
                ...$money('total', $item->total),
                'discounts' => $order->adjustments
                    ->filter(static fn (OrderAdjustment $adjustment): bool => $adjustment->item_id === $item->id)
                    ->map(static fn (OrderAdjustment $adjustment): array => ['label' => $adjustment->label, ...$money('amount', $adjustment->amount)])
                    ->values()->all(),
                'children' => $this->lines($order, $item->id, $locale, $money),
            ];
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    private function refundAnswer(RefundResult $result, string $currency): array
    {
        return [
            ...$this->amount('money_left', $result->moneyLeft, $currency),
            'tenders_left' => $result->tendersLeft,
            'items_left' => $result->itemsLeft,
            'full' => $result->full,
            'milestone' => $result->milestone !== null ? Consequences::milestone($result->milestone) : null,
            'refusal' => $result->refusal,
            'field' => $result->field,
            'message' => $result->refusal !== null ? $this->refundRefusal($result, $currency) : null,
            'dry_run' => $result->dryRun,
        ];
    }

    private function refundRefusal(RefundResult $result, string $currency): string
    {
        return (string) __('webx-orders::panel.refund-refusals.'.$result->refusal, [
            'left' => $this->money->format($result->moneyLeft, $currency),
        ]);
    }

    /** A refusal of the service in the panel's words, with the order it is about. */
    private function refusal(Order $order, string $reason): string
    {
        return "Order {$order->number}: ".__('webx-orders::panel.refusals.'.$reason);
    }

    /**
     * @return array<string, int|string>
     */
    private function amount(string $key, int $minor, string $currency): array
    {
        return [$key => $minor, $key.'_formatted' => $this->money->format($minor, $currency)];
    }

    /**
     * An order by its number first, then by its id.
     */
    private function order(mixed $reference): Order
    {
        if (is_string($reference)) {
            $reference = trim($reference);
        }

        if ((is_string($reference) && $reference !== '') || is_int($reference)) {
            $found = Order::query()->where('number', (string) $reference)->first()
                ?? (is_int($reference) || ctype_digit($reference) ? Order::query()->find((int) $reference) : null);

            if ($found instanceof Order) {
                return $found;
            }

            throw new ToolFailure("There is no order {$reference}. orders_list finds one by its number, the buyer's phone, e-mail or name.");
        }

        throw new ToolFailure('An order is its number or its id.');
    }

    /**
     * An internal status by its id or by its name in any language — deleted ones too, so the
     * service, not this lookup, says why it cannot be moved into.
     */
    private function status(mixed $reference, StatusChoices $statuses): OrderStatus
    {
        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            return $statuses->model((int) $reference) ?? throw new ToolFailure("There is no status {$reference}. orders_statuses_list lists them.");
        }

        $name = mb_strtolower(trim((string) $reference));

        if ($name === '') {
            throw new ToolFailure('`status` is the id or the name of an internal status.');
        }

        $found = OrderStatus::withTrashed()->orderBy('id')->get()->filter(
            static fn (OrderStatus $status): bool => in_array($name, array_map(mb_strtolower(...), array_map(strval(...), $status->getTranslations('name'))), true),
        );

        if ($found->count() > 1) {
            throw new ToolFailure("More than one status is called «{$reference}»: ids ".implode(', ', $found->modelKeys()).'. Send the id.');
        }

        return $found->first() ?? throw new ToolFailure("There is no status «{$reference}». orders_statuses_list lists them.");
    }

    /**
     * @param  list<mixed>  $codes
     * @return list<int>
     */
    private function publicIds(array $codes): array
    {
        $codes = array_map(static fn (mixed $code): string => strtolower(trim((string) $code)), $codes);
        $ids = PublicStatus::withTrashed()->whereIn('code', $codes)->pluck('id', 'code')->all();
        $unknown = array_diff($codes, array_keys($ids));

        if ($unknown !== []) {
            throw new ToolFailure('There is no public status ['.implode('], [', $unknown).']. orders_statuses_list lists them.');
        }

        return array_values(array_map(intval(...), $ids));
    }

    /**
     * The listeners of an event, by class — what a mark would set going.
     *
     * @return list<string>
     */
    private function listeners(string $event): array
    {
        if (! $this->events instanceof EventDispatcher) {
            return [];
        }

        $raw = $this->events->getRawListeners()[$event] ?? [];

        return array_values(array_map(static fn (mixed $listener): string => match (true) {
            is_string($listener) => $listener,
            is_array($listener) => (is_object($listener[0]) ? $listener[0]::class : (string) $listener[0]).'@'.(string) ($listener[1] ?? 'handle'),
            $listener instanceof Closure => 'Closure',
            is_object($listener) => $listener::class,
            default => 'unknown',
        }, $raw));
    }
}
