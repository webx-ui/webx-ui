<?php

declare(strict_types=1);

namespace WebxUi\Orders\Mcp;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Events\Dispatcher as EventDispatcher;
use WebxUi\Admin\Visitors\Visitors;
use WebxUi\Cart\Money;
use WebxUi\Localization\Locales;
use WebxUi\Mcp\Arguments;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Rehearsal;
use WebxUi\Mcp\Tool;
use WebxUi\Orders\Balance;
use WebxUi\Orders\CartNotOrderable;
use WebxUi\Orders\Checkout\CheckoutPreview;
use WebxUi\Orders\Contact;
use WebxUi\Orders\Events\OrderPaid;
use WebxUi\Orders\Events\OrderReceived;
use WebxUi\Orders\Http\Controllers\Panel\MassController;
use WebxUi\Orders\ItemsEdit;
use WebxUi\Orders\ItemsEditRefused;
use WebxUi\Orders\ManagerOrder;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderAdjustment;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderRefund;
use WebxUi\Orders\Models\OrderRevision;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\OrderTender;
use WebxUi\Orders\Models\PublicStatus;
use WebxUi\Orders\Notifications\OrderChannels;
use WebxUi\Orders\NotifyRefused;
use WebxUi\Orders\OrderDetails;
use WebxUi\Orders\OrderRefused;
use WebxUi\Orders\Orders;
use WebxUi\Orders\Panel\Consequences;
use WebxUi\Orders\Panel\OrderCard;
use WebxUi\Orders\Panel\OrderList;
use WebxUi\Orders\Panel\OrdersModule;
use WebxUi\Orders\Panel\OrdersSummary;
use WebxUi\Orders\Panel\StatusBatch;
use WebxUi\Orders\Panel\StatusChoices;
use WebxUi\Orders\PaymentRecord;
use WebxUi\Orders\PaymentRefused;
use WebxUi\Orders\RefundRefused;
use WebxUi\Orders\RefundRequest;
use WebxUi\Orders\RefundResult;
use WebxUi\Orders\RefundReversal;
use WebxUi\Orders\RefundReversalRefused;
use WebxUi\Orders\Refusal;
use WebxUi\Orders\Site\VisitorOrders;
use WebxUi\Orders\StatusMoveRefused;
use WebxUi\Orders\VisitorLink;

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
        private OrderList $list,
        private OrderCard $card,
        private Consequences $consequences,
        private StatusBatch $batch,
        private Money $money,
        private Validation $validation,
        private Dispatcher $events,
        private OrderChannels $channels,
        private OrdersSummary $summary,
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

        $tools = [
            Tool::read(
                'list',
                'Orders, newest first, twenty a page: number, when placed, the buyer (name, phone, e-mail — personal data, '
                .'which orders.view opens), the first line and how many more, total and payable in minor units with '
                .'`*_formatted`, the internal status with its public one, the milestones and the flags «awaiting payment» '
                .'and «payment failed», the delivery and payment lines, `balance` (> 0 owed, < 0 to give back) and `balance_kind` '
                .'(topup — paid and owing more, unpaid, over, null — settled). `counts` — how many each view holds under the same '
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
                    'balance' => ['type' => 'string', 'enum' => OrderList::BALANCES, 'description' => 'Payment differs: due — paid and still owing a top-up (after an edit); over — paid more than it costs, money to give back.'],
                    'visitor' => ['type' => 'string', 'enum' => ['guest', 'visitor'], 'description' => 'Placed by a guest or by a signed-in visitor.'],
                    'delivery' => ['type' => 'string', 'description' => 'The delivery method as the block keeps it.'],
                    'payment' => ['type' => 'string', 'description' => 'The payment method as the block keeps it.'],
                    'source' => ['type' => 'string', 'description' => 'Where the order came from: checkout, quick («Buy in one click»), panel and mcp (a manager placed it), api — or the code\'s own source.'],
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
                .'reference, and the refunds. `balance`: what came in (`paid_total`), what went back, what is still `due` '
                .'or `over` (to give back), `tenders_over`, and `kind` (topup, unpaid, over, null). Money in minor units with `*_formatted`.',
                fn (array $arguments): array => $this->get($arguments),
                ['properties' => ['order' => $order], 'required' => ['order']],
                permission: self::READ,
            ),

            Tool::read(
                'history',
                'The timeline of an order, oldest first — the card\'s tab «History»: moves between statuses, milestones, '
                .'refunds, notes, top-ups, edits of the lines (`revision`: each line before → after, the total before and after, the '
                .'balance after; the reason as `note`), changed contacts and blocks, each with when, the source (panel, mcp, api, checkout, '
                .'status#<id>) and the administrator\'s name where known. With the notifications module, `notifications` — what '
                .'its channels sent about the order, newest first, and why a row was not sent; null without it. `revisions` — '
                .'every edit of the lines in full: changes, discounts, totals before and after, reason, message, source.',
                fn (array $arguments, ?Authenticatable $caller = null): array => $this->history($arguments, $caller),
                ['properties' => ['order' => $order], 'required' => ['order']],
                permission: self::READ,
            ),

            Tool::read(
                'summary',
                'The orders at a glance: how many stand in each public status and in each internal one, how many await '
                .'payment, how many have a failed payment, how many are paid while still standing in a status new orders '
                .'come into («paid, the status waits»), and for calendar days in the site\'s timezone — `days` 1 is today from '
                .'midnight, 7 and 30 are today and the 6 or 29 days before it — by currency: orders placed, paid, '
                .'and the revenue — what was paid less what was given back — with the average of a paid order; what awaits '
                .'payment now by currency (`awaiting_by_currency`: orders and the sum); paid orders still owing a top-up after an '
                .'edit (`awaiting_topup`, and `topup_by_currency`: orders and the sum); the revenue counts the money that came in, '
                .'top-ups too; `daily` — orders placed each of the last '
                .'30 days, today last. The panel\'s figures over the list are this same count.',
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
                'payment_record',
                'Record money that came in for an order that is paid already — a top-up after its lines were changed, by a '
                .'bank transfer or at the desk. `amount` in minor units, the operation\'s `reference`, the day (`at`, '
                .'YYYY-MM-DD; today by default), a `note`. The milestone «paid» stays one; the balance goes down, and more than is '
                .'due shows as money to give back. An order not paid yet is refused — its first payment is orders_mark; a '
                .'cancelled one too. `dry_run` answers the balance before and after it, or why it is refused.',
                fn (array $arguments): array => $this->payment($arguments),
                ['properties' => [
                    'order' => $order,
                    'amount' => ['type' => 'integer', 'description' => 'Money in, minor units; orders_get `balance.due` is what is owed.'],
                    'reference' => ['type' => 'string', 'description' => 'The bank\'s or the ledger\'s number of it.'],
                    'at' => ['type' => 'string', 'description' => 'When the money came, YYYY-MM-DD; today by default.'],
                    'note' => ['type' => 'string'],
                ], 'required' => ['order', 'amount']],
                permission: OrdersModule::MANAGE,
            ),

            Tool::mutating(
                'items_edit',
                'Change the lines of a placed order — the buyer called to add a product, take one out, change how many. '
                .'`revision` is orders_get `items_revision`: an edit saved since is refused, read the order again. `add` — '
                .'new lines as orders_create takes them (type, ref, qty, options), at today\'s price and without the cart\'s '
                .'rules; `qty` — line id → the new qty, top-level lines only (a set changes whole, its parts follow; 0 takes '
                .'the line out); `remove` — line ids. The lines placed keep their price; their discounts follow the qty; the '
                .'discounts on the whole order, the delivery and the benefits stay as placed. A line keeps the units refunded '
                .'of it; a rule\'s gift may only go. `reason` (optional) only managers see; `message` — words for '
                .'the buyer\'s letter; `notify` (true by default) — send the buyer «order changed». Received, cancelled and '
                .'refunded orders are not edited. A paid order then owes a top-up or is owed money back: `balance` says '
                .'which (orders_payment_record, orders_refund). `dry_run` answers what the panel\'s editor shows: each line '
                .'before → after, the totals before and after, the balance after, the warnings — or the refusal and its field.',
                fn (array $arguments): array => $this->editItems($arguments),
                ['properties' => [
                    'order' => $order,
                    'revision' => ['type' => 'integer', 'description' => 'orders_get `items_revision` — the lines as you read them.'],
                    'add' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'type' => ['type' => 'string', 'description' => 'What is bought: `catalog.product`.'],
                        'ref' => ['type' => ['integer', 'string']],
                        'qty' => ['type' => 'integer', 'description' => '1 by default.'],
                        'options' => ['type' => 'object', 'description' => 'The chosen options, as the product names them.'],
                    ], 'required' => ['type', 'ref']]],
                    'qty' => ['type' => 'object', 'description' => 'Line id → the new qty (orders_get `items`).', 'additionalProperties' => ['type' => 'integer']],
                    'remove' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Line ids to take out.'],
                    'reason' => ['type' => 'string', 'description' => 'Why, if there is a reason worth keeping — for the managers, never shown to the buyer.'],
                    'message' => ['type' => 'string', 'description' => 'Words for the buyer in the letter about the change.'],
                    'notify' => $flag('Send the buyer the letter «order changed»; true by default.'),
                ], 'required' => ['order', 'revision']],
                permission: OrdersModule::MANAGE,
            ),

            Tool::mutating(
                'cancel',
                'Cancel an order with its reason: the milestone «cancelled», and the tenders left on it (wallet, gift card) go '
                .'back, and the order comes into the first active status that cancels (`status`: its name, what the buyer '
                .'sees, the channels that write; null — it stays). Money is not given back — a paid order answers how much '
                .'is left to refund, and orders_refund records it. `dry_run` answers whether it was paid, the money left, '
                .'the tenders that go back and the status.',
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
                'refund_reverse',
                'Reverse a refund recorded by mistake — a refund is never edited. The record stays in the history, struck '
                .'through with the reason; what it gave back (money, tenders, units) counts no more, and when the order is no '
                .'longer given back whole the milestone «refunded» comes off. Then record the right one with orders_refund. '
                .'`refund` is the id from orders_get. `dry_run` answers what comes off, what is left after it and whether '
                .'the milestone comes off, or why it is refused.',
                fn (array $arguments): array => $this->reverseRefund($arguments),
                ['properties' => [
                    'order' => $order,
                    'refund' => ['type' => 'integer', 'description' => 'The id of the refund record, from orders_get `refunds`.'],
                    'reason' => ['type' => 'string', 'description' => 'Why the record was a mistake — it goes into the history.'],
                ], 'required' => ['order', 'refund', 'reason']],
                permission: OrdersModule::REFUND,
            ),

            Tool::mutating(
                'notify',
                'Send a message about an order again by hand — «the buyer says the letter never came». With the notifications '
                .'module only. `delivery` — the id of a row of orders_history `notifications`: the same message to the same '
                .'recipient, sent or failed alike, as a new row marked manual. Or `event` — tell the buyer of an event the '
                .'order has a reason for (placed; public_status — the one it stands in; paid, payment_failed, received, '
                .'cancelled, refunded once they happened), to the order\'s e-mail now, through the channels of that event that '
                .'write to the buyer (customer_email). Either is a line of the history with who did it; the same message again '
                .'within a minute is refused. Do not send unless a person asked. `dry_run` answers what would go — for an '
                .'event, `previews` with each channel\'s subject and text — or why it is refused.',
                fn (array $arguments): array => $this->notify($arguments),
                ['properties' => [
                    'order' => $order,
                    'delivery' => ['type' => 'integer', 'description' => 'The id of a row of the order\'s log, from orders_history `notifications`.'],
                    'event' => ['type' => 'string', 'enum' => OrderChannels::BY_HAND, 'description' => 'The event to tell the buyer of again.'],
                ], 'required' => ['order']],
                permission: OrdersModule::MANAGE,
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
                'create',
                'Place an order for a buyer, as a manager takes one on the phone: the lines (`lines`: type, ref, qty, '
                .'options — as «Add to cart» takes them; catalog_products_get has a product\'s ref and options), the buyer '
                .'(`customer`: first_name required, last_name, a phone or an e-mail), the recipient if another person, a '
                .'comment. The order is counted as on the checkout — today\'s prices and the cart\'s rules, the next number, '
                .'its letters — with no delivery or payment chosen; a price or a discount of your own is not possible. '
                .'`key` makes a repeat the same order. `dry_run` answers the lines, totals and warnings it would have, and '
                .'whether it can be placed, and writes nothing.',
                fn (array $arguments): array => $this->create($arguments),
                ['properties' => [
                    'lines' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                        'type' => ['type' => 'string', 'description' => 'What is bought: `catalog.product`.'],
                        'ref' => ['type' => ['integer', 'string']],
                        'qty' => ['type' => 'integer', 'description' => '1 by default.'],
                        'options' => ['type' => 'object', 'description' => 'The chosen options, as the product names them.'],
                    ], 'required' => ['type', 'ref']]],
                    'customer' => ['type' => 'object', 'properties' => [
                        'first_name' => ['type' => 'string'], 'last_name' => ['type' => ['string', 'null']],
                        'phone' => ['type' => ['string', 'null']], 'email' => ['type' => ['string', 'null']],
                    ], 'required' => ['first_name']],
                    'recipient' => ['type' => ['object', 'null'], 'properties' => [
                        'first_name' => ['type' => 'string'], 'last_name' => ['type' => ['string', 'null']], 'phone' => ['type' => ['string', 'null']],
                    ]],
                    'comment' => ['type' => ['string', 'null']],
                    'locale' => ['type' => 'string', 'description' => 'The order\'s language: its names and letters. The site\'s default language when left out.'],
                    'key' => ['type' => 'string', 'description' => 'Any string of yours: a second call with it is the order of the first.'],
                ], 'required' => ['lines', 'customer']],
                permission: OrdersModule::MANAGE,
            ),

            Tool::mutating(
                'note',
                'Write a line into the order\'s history — «called, the buyer will come on Friday». The buyer does not see it.',
                fn (array $arguments): array => $this->note($arguments),
                ['properties' => ['order' => $order, 'text' => ['type' => 'string']], 'required' => ['order', 'text']],
                permission: OrdersModule::MANAGE,
            ),
        ];

        // The visitor of an order (§13.3) — only where there are visitors.
        if (VisitorOrders::installed()) {
            $tools[] = Tool::mutating(
                'link_visitor',
                'Link an order to a visitor\'s account — it then shows in their «My orders» —, move it to another visitor in one '
                .'step, or unlink it (`unlink: true`) so it is a guest\'s again. `visitor` is the account\'s id or its exact '
                .'e-mail (users_list finds one by a part of the name or the address). Only the link changes: the contacts in '
                .'the order stay what the buyer typed; the history keeps who was linked, and no letter goes to the buyer. '
                .'`dry_run` answers whom it finds, and whom the order belonged to before.',
                fn (array $arguments): array => $this->linkVisitor($arguments),
                ['properties' => [
                    'order' => $order,
                    'visitor' => ['type' => ['integer', 'string'], 'description' => 'The visitor: the account id or its exact e-mail.'],
                    'unlink' => $flag('Unlink instead: the order is a guest\'s again.'),
                ], 'required' => ['order']],
                permission: OrdersModule::MANAGE,
            );
        }

        return Arguments::strictAll($tools, 'orders_');
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
            // orders_items_edit takes it back as `revision`; 0 — the lines as placed.
            'items_revision' => $order->items_revision,
            'items_changed_at' => $order->items_changed_at?->toAtomString(),
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
                ...$money('refundable', Balance::refundable($order)),
            ],
            'balance' => Consequences::balance(Balance::of($order), $this->money),
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
                // Reversed by mistake (decision 98): it counts no more, and says when and why.
                'reversed' => $refund->reversed() ? [
                    'at' => $refund->reversed_at?->toAtomString(),
                    'source' => $refund->reversed_source,
                    'admin_id' => $refund->reversed_by,
                    'reason' => $refund->reversal_reason,
                ] : null,
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
                if (in_array($line['kind'], ['refund', 'refund_reversed', 'payment'], true) && array_key_exists('amount_minor', $line)) {
                    $line['amount_formatted'] = $line['amount'];
                    $line['amount'] = $line['amount_minor'];
                    unset($line['amount_minor']);
                }

                unset($line['key']);

                return $line;
            }, $lines),
            'notifications' => $this->channels->available() ? $this->channels->deliveries($order, $caller) : null,
            // The edits of the lines with every field of their row (§38.2).
            'revisions' => $order->revisions->map(static fn (OrderRevision $revision): array => [
                'number' => $revision->number,
                'changes' => $revision->changes,
                'adjustments' => $revision->adjustments,
                'before' => $revision->before,
                'after' => $revision->after,
                'reason' => $revision->reason,
                'message' => $revision->message,
                'source' => $revision->source,
                'admin_id' => $revision->admin_id,
                'at' => $revision->created_at?->toAtomString(),
            ])->values()->all(),
        ];
    }

    /**
     * An edit of the lines (§37) — the editor's answer, {@see Consequences::itemsEdit()}.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function editItems(array $arguments): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $revision = $arguments['revision'] ?? null;

        if (! is_int($revision) || $revision < 0) {
            throw new ToolFailure('`revision` is orders_get `items_revision`, a whole number.');
        }

        $qty = [];

        foreach ((array) ($arguments['qty'] ?? []) as $id => $value) {
            if (! ctype_digit((string) $id) || ! is_int($value) || $value < 0) {
                throw new ToolFailure('`qty` is line id → a whole number of units, 0 or more.');
            }

            $qty[(int) $id] = $value;
        }

        $remove = array_values((array) ($arguments['remove'] ?? []));

        if (array_filter($remove, static fn (mixed $id): bool => ! is_int($id)) !== []) {
            throw new ToolFailure('`remove` is a list of line ids.');
        }

        $edit = new ItemsEdit(
            revision: $revision,
            add: array_values(array_map(static fn (mixed $line): array => (array) $line, (array) ($arguments['add'] ?? []))),
            qty: $qty,
            remove: $remove,
            reason: isset($arguments['reason']) ? (string) $arguments['reason'] : null,
            message: isset($arguments['message']) ? (string) $arguments['message'] : null,
            notify: ($arguments['notify'] ?? true) !== false,
            source: self::SOURCE,
        );

        try {
            $result = $this->attempt(fn () => $this->orders->editItems($order, $edit, $this->dryRun($arguments)));
        } catch (ItemsEditRefused $refused) {
            $result = $refused->result;
        } catch (CartNotOrderable $refused) {
            throw new ToolFailure("Order {$order->number}: ".CheckoutPreview::notOrderable($refused));
        } catch (OrderRefused $refused) {
            throw new ToolFailure("Order {$order->number}: ".$refused->getMessage());
        }

        $answer = ['number' => $order->number, ...$this->consequences->itemsEdit($result, $order->currency, $this->money)];

        if ($result->refusal !== null && ! $result->dryRun) {
            throw new ToolFailure("Order {$order->number}: ".$answer['message'].($result->field !== null ? " ({$result->field})" : ''));
        }

        return $answer;
    }

    /**
     * The count the panel's figures over the list show too — {@see OrdersSummary}.
     *
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        return $this->summary->describe(app()->getLocale());
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
     * A top-up (§36.3) — the dialog's answer, {@see Consequences::payment()}.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function payment(array $arguments): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $amount = $arguments['amount'] ?? null;

        if (! is_int($amount) || $amount < 1) {
            throw new ToolFailure('`amount` is a whole number of minor units, above zero.');
        }

        $at = null;

        if (isset($arguments['at'])) {
            $day = (string) $arguments['at'];

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
                throw new ToolFailure('`at` is a day, YYYY-MM-DD.');
            }

            $at = CarbonImmutable::parse($day)->setTimeFrom(CarbonImmutable::now());
        }

        $record = fn (bool $dryRun): PaymentRecord => $this->orders->recordPayment(
            $order,
            $amount,
            self::SOURCE,
            isset($arguments['reference']) ? (string) $arguments['reference'] : null,
            $at,
            isset($arguments['note']) ? (string) $arguments['note'] : null,
            $dryRun,
        );

        try {
            $result = $record($this->dryRun($arguments));
        } catch (PaymentRefused $refused) {
            throw new ToolFailure($this->paymentRefusal($order, $refused->reason));
        }

        $answer = ['number' => $order->number, ...$this->consequences->payment($result, $this->money)];

        if ($result->refusal !== null) {
            $answer['message'] = $this->paymentRefusal($order, $result->refusal);
        }

        return $answer;
    }

    private function paymentRefusal(Order $order, string $reason): string
    {
        return $this->refusal($order, $reason).($reason === PaymentRecord::NOT_PAID ? ' Use orders_mark with «paid» for its first payment.' : '');
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
            // The status it comes into, as orders_move says one (decision 88); null — it stays.
            'status' => $said['status'],
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
    private function reverseRefund(array $arguments): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $dryRun = $this->dryRun($arguments);
        $refund = $arguments['refund'] ?? null;

        if (! is_int($refund) && ! (is_string($refund) && ctype_digit($refund))) {
            throw new ToolFailure('`refund` is the id of a refund record; orders_get lists them under `refunds`.');
        }

        $reason = isset($arguments['reason']) ? (string) $arguments['reason'] : null;

        try {
            $reversal = $this->attempt(fn (): RefundReversal => $this->orders->reverseRefund($order, (int) $refund, $reason, self::SOURCE, $dryRun));
        } catch (RefundReversalRefused $refused) {
            throw new ToolFailure("Order {$order->number}: ".__('webx-orders::panel.reverse-refusals.'.$refused->reason));
        }

        return ['number' => $order->number, ...$this->consequences->reversal($reversal, $order->currency, $this->money)];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function notify(array $arguments): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $delivery = $arguments['delivery'] ?? null;
        $event = $arguments['event'] ?? null;

        if (($delivery === null) === ($event === null)) {
            throw new ToolFailure('Send `delivery` (a row of orders_history `notifications`) or `event`, one of them.');
        }

        if ($delivery !== null && ! is_int($delivery) && ! (is_string($delivery) && ctype_digit($delivery))) {
            throw new ToolFailure('`delivery` is the id of a row of orders_history `notifications`.');
        }

        try {
            $result = $this->orders->notify($order, $delivery !== null ? ['delivery' => (int) $delivery] : ['event' => (string) $event], self::SOURCE, $this->dryRun($arguments));
        } catch (NotifyRefused $refused) {
            throw new ToolFailure("Order {$order->number}: ".$refused->said());
        }

        return ['number' => $order->number, ...$result->toArray()];
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
     * §29.2 through `Orders::placeByManager()`: the dry run is the service's count, rolled back.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function create(array $arguments): array
    {
        $text = static fn (mixed $value): ?string => is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null;
        $contact = static function (mixed $value, string $name, array $keys) use ($text): Contact {
            if (! is_array($value)) {
                throw new ToolFailure("`{$name}` is an object of ".implode(', ', $keys).'.');
            }

            Arguments::refuseUnknown($value, $keys, "`{$name}`");

            return new Contact((string) $text($value['first_name'] ?? null), $text($value['last_name'] ?? null), $text($value['phone'] ?? null), $text($value['email'] ?? null));
        };

        $lines = $arguments['lines'] ?? null;

        if (! is_array($lines) || ! array_is_list($lines)) {
            throw new ToolFailure('`lines` is a list of { type, ref, qty, options }.');
        }

        foreach ($lines as $index => $line) {
            if (! is_array($line)) {
                throw new ToolFailure("`lines.{$index}` is an object of type, ref, qty, options.");
            }

            Arguments::refuseUnknown($line, ['type', 'ref', 'qty', 'options'], "`lines.{$index}`");
        }

        $locales = Container::getInstance()->make(Locales::class);
        $locale = isset($arguments['locale']) ? (string) $arguments['locale'] : $locales->defaultCode();

        if (! $locales->has($locale)) {
            throw new ToolFailure("The site has no language «{$locale}»: ".implode(', ', $locales->codes()).'.');
        }

        $recipient = $arguments['recipient'] ?? null;
        $input = new ManagerOrder(
            lines: $lines,
            locale: $locale,
            customer: $contact($arguments['customer'] ?? null, 'customer', ['first_name', 'last_name', 'phone', 'email']),
            recipient: $recipient !== null ? $contact($recipient, 'recipient', ['first_name', 'last_name', 'phone']) : null,
            comment: $text($arguments['comment'] ?? null),
            idempotencyKey: $text($arguments['key'] ?? null),
        );

        if ($this->dryRun($arguments)) {
            $preview = $this->attempt(fn () => $this->orders->previewByManager($input));

            return [
                'orderable' => $preview->orderable(),
                'refusal' => $preview->refusal?->reason,
                'message' => $preview->refusal !== null ? CheckoutPreview::notOrderable($preview->refusal) : null,
                'totals' => $preview->state->toArray(),
                'dry_run' => true,
            ];
        }

        try {
            $order = $this->attempt(fn (): Order => $this->orders->placeByManager($input, self::SOURCE));
        } catch (CartNotOrderable $refused) {
            throw new ToolFailure('The order cannot be placed: '.CheckoutPreview::notOrderable($refused));
        } catch (OrderRefused $refused) {
            throw new ToolFailure('The order cannot be placed: '.$refused->getMessage());
        }

        return ['order' => $this->get(['order' => $order->number]), 'dry_run' => false];
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
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function linkVisitor(array $arguments): array
    {
        $order = $this->order($arguments['order'] ?? null);
        $dryRun = $this->dryRun($arguments);

        if ((bool) ($arguments['unlink'] ?? false)) {
            $link = $this->orders->unlinkVisitor($order, self::SOURCE, $dryRun);
        } else {
            $reference = $arguments['visitor'] ?? null;
            $visitors = Container::getInstance()->make(Visitors::class);
            $visitor = match (true) {
                is_int($reference), is_string($reference) && ctype_digit($reference) => $visitors->find((int) $reference),
                is_string($reference) && str_contains($reference, '@') => $visitors->findByEmail($reference),
                default => throw new ToolFailure('`visitor` is the account id or its exact e-mail; `unlink: true` unlinks.'),
            } ?? throw new ToolFailure('There is no such visitor. users_list finds one by a part of the name or the address.');

            $link = $this->orders->linkVisitor($order, $visitor->id, self::SOURCE, $dryRun);
        }

        return [
            'number' => $order->number,
            ...$link->toArray(),
            'message' => match ($link->outcome) {
                VisitorLink::LINKED => 'The order shows in «My orders» of '.VisitorLink::name($link->visitor ?? throw new ToolFailure('No visitor.')).'.',
                VisitorLink::UNLINKED => 'The order is a guest\'s: it is gone from the visitor\'s «My orders».',
                default => 'Nothing changes: the order is linked so already.',
            },
        ];
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
            'source' => $row['source'],
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
            ...$this->amount('balance', $order->balance, $order->currency),
            'balance_kind' => Balance::of($order)->kind(),
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
            // Taken out whole by an edit: orders_history has it, the order no longer does.
            if ($item->parent_id !== $parent || ! $item->shown()) {
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
                // What orders_items_edit may do with it: a gift only goes; a set's part is not edited apart.
                'gift' => $item->source !== null,
                'revision' => $item->revision,
                'removed_at' => $item->removed_at?->toAtomString(),
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
