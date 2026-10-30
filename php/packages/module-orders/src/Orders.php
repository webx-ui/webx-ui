<?php

declare(strict_types=1);

namespace WebxUi\Orders;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Validation\Factory as Validation;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use WebxUi\Admin\History\Journal;
use WebxUi\Admin\Visitors\Visitor;
use WebxUi\Admin\Visitors\Visitors;
use WebxUi\Cart\CartService;
use WebxUi\Cart\Contracts\CurrencySource;
use WebxUi\Cart\Models\Cart;
use WebxUi\Cart\Money;
use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Cart\Totals\Calculator;
use WebxUi\Cart\Totals\CartState;
use WebxUi\Cart\Totals\Purpose;
use WebxUi\Cart\Totals\ResolvedLine;
use WebxUi\Cart\Totals\Tender;
use WebxUi\Orders\Blocks\BlockRefused;
use WebxUi\Orders\Blocks\OrderBlocks;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Data\PublicStatusData;
use WebxUi\Orders\Data\RefundData;
use WebxUi\Orders\Data\RevisionData;
use WebxUi\Orders\Data\StatusData;
use WebxUi\Orders\Data\TenderData;
use WebxUi\Orders\Events\OrderBlockUpdated;
use WebxUi\Orders\Events\OrderCancelled;
use WebxUi\Orders\Events\OrderDetailsChanged;
use WebxUi\Orders\Events\OrderItemsChanged;
use WebxUi\Orders\Events\OrderItemsChanging;
use WebxUi\Orders\Events\OrderPaid;
use WebxUi\Orders\Events\OrderPaymentFailed;
use WebxUi\Orders\Events\OrderPaymentReceived;
use WebxUi\Orders\Events\OrderPlaced;
use WebxUi\Orders\Events\OrderPlacing;
use WebxUi\Orders\Events\OrderPublicStatusChanged;
use WebxUi\Orders\Events\OrderReceived;
use WebxUi\Orders\Events\OrderRefunded;
use WebxUi\Orders\Events\OrderRefundReversed;
use WebxUi\Orders\Events\OrderStatusChanged;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderAdjustment;
use WebxUi\Orders\Models\OrderBenefit;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderRefund;
use WebxUi\Orders\Models\OrderRevision;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\OrderTender;
use WebxUi\Orders\Models\StatusChange;
use WebxUi\Orders\Notifications\OrderChannels;

/**
 * The one door to an order (spec §5.3, decision 18). The panel, the agent's tools, the checkout
 * and the site's API check the right, read the input, call one method and give back its answer;
 * none of them writes the tables of an order, and neither does a listener of a neighbour.
 *
 * Every method that changes an order is one transaction with the order's row locked, and its
 * events go after the commit (§7.1).
 */
final class Orders
{
    public function __construct(
        private readonly Calculator $calculator,
        private readonly CartService $carts,
        private readonly CurrencySource $currencies,
        private readonly OrderBlocks $blocks,
        private readonly OrderNumbers $numbers,
        private readonly InitialStatuses $statuses,
        private readonly ConnectionInterface $db,
        private readonly Dispatcher $events,
        private readonly Validation $validation,
        private readonly Auth $auth,
        private readonly Repository $config,
        private readonly Journal $journal,
    ) {}

    /**
     * §6 — the only way an order comes to be: the cart counted again under its lock, checked,
     * frozen into the order, and emptied — all in one transaction, or nothing.
     *
     * A repeat with the same idempotency key is the order it made, and nothing is done again.
     *
     * @throws ValidationException the contacts or the comment
     * @throws BlockRefused a block no module registered, or one its owner refuses
     * @throws CartNotOrderable empty, another currency, lines that cannot be bought
     * @throws PayableChanged the buyer saw another amount
     * @throws OrderRefused a listener of OrderPlacing would not have it
     */
    public function place(PlaceOrder $input): Order
    {
        $customer = $input->customer->normalised();
        $recipient = $input->recipient?->normalised();
        $comment = $input->comment !== null && trim($input->comment) !== '' ? trim($input->comment) : null;

        $this->validate($customer, $recipient, $comment);
        $this->blocks->check($input->blocks);

        $key = $input->idempotencyKey !== null && $input->idempotencyKey !== '' ? self::hash($input->idempotencyKey) : null;

        if ($key !== null && ($order = $this->byHash($key)) !== null) {
            return $order;
        }

        try {
            return $this->db->transaction(fn (): Order => $this->write($input, $customer, $recipient, $comment, $key));
        } catch (UniqueConstraintViolationException $e) {
            // A twin that came past the lock of another cart with the same key: the key is the
            // order's, whichever request made it.
            if ($key !== null && ($order = $this->byHash($key)) !== null) {
                return $order;
            }

            throw $e;
        }
    }

    /**
     * §29.2 — an order a manager makes in the panel, the buyer on the phone. The lines go into a
     * cart of its own, as for «Buy in one click» (§29.1): a row of `carts` whose token nobody is
     * given. `place()` makes the order of it, so prices, rules, the snapshot, the number, the
     * events and the letters are those of any order, and then the cart goes. One transaction: a
     * refusal at any step leaves neither the cart nor the order.
     *
     * The right (`orders.manage`) is the caller's to check, as for every method here; the
     * manager is in the history by the source (`panel`, `mcp`).
     *
     * @throws ValidationException the lines (none, unknown, options, qty), the contacts, the comment
     * @throws CartNotOrderable a line that cannot be bought
     * @throws OrderRefused a listener of OrderPlacing would not have it
     */
    public function placeByManager(ManagerOrder $input, string $source = 'panel'): Order
    {
        $this->validateManager($input);

        if ($input->idempotencyKey !== null && $input->idempotencyKey !== '' && ($order = $this->findByIdempotencyKey($input->idempotencyKey)) !== null) {
            return $order;
        }

        return $this->db->transaction(function () use ($input, $source): Order {
            $cart = $this->carts->add(null, $input->lines, $input->locale)->cart;

            $order = $this->place(new PlaceOrder(
                cart: $cart,
                locale: $input->locale,
                customer: $input->customer,
                recipient: $input->recipient,
                comment: $input->comment,
                idempotencyKey: $input->idempotencyKey,
                source: $source,
            ));

            $this->carts->clear($cart);

            return $order;
        });
    }

    /**
     * The order a manager's lines would make, counted and not placed: the dry run of
     * `orders_create` and the totals of the panel's form. The same checks as `placeByManager()`,
     * and the cart is really made, counted for the order and rolled back — so the count is the
     * one `place()` would freeze, and nothing stays.
     *
     * @param  bool  $contacts  false — the lines alone: the panel's form counts them while the
     *                          contacts are still being typed, and placing checks them anyway
     *
     * @throws ValidationException as placeByManager()
     */
    public function previewByManager(ManagerOrder $input, bool $contacts = true): ManagerPreview
    {
        if ($contacts) {
            $this->validateManager($input);
        }

        $this->db->beginTransaction();

        try {
            $cart = $this->carts->add(null, $input->lines, $input->locale)->cart;
            $state = $this->calculator->calculate($cart, $input->locale, Purpose::Order);

            try {
                $this->orderable($cart, $state);
            } catch (CartNotOrderable $refused) {
                return new ManagerPreview($state, $refused);
            }

            return new ManagerPreview($state);
        } finally {
            $this->db->rollBack();
        }
    }

    /** The order a checkout's form token made (§6.2, step 1), or null; the key is hashed the same way. */
    public function findByIdempotencyKey(string $key): ?Order
    {
        return $key === '' ? null : $this->byHash(self::hash($key));
    }

    /**
     * Set a milestone once (§5.1). A repeat is silence: the first mark comes back and nothing is
     * dispatched — the order's row is locked, so a gateway and a manager marking it paid at the
     * same moment make one event, not two.
     *
     * «Paid» counts money in (§36.3): `$amount` is what a gateway confirmed; without it — a
     * manager's «Mark as paid» — the order is paid whole, what it still owes.
     *
     * @throws MilestoneRefused «placed», «awaiting payment», «refunded»: they are not set by hand
     * @throws OrderCancelledAlready «paid» or «received» of a cancelled order
     */
    public function mark(
        Order $order,
        Milestone $milestone,
        string $source,
        ?CarbonInterface $at = null,
        ?string $reference = null,
        ?string $note = null,
        ?int $amount = null,
    ): MilestoneMark {
        if (! $milestone->settableByStatus()) {
            throw new MilestoneRefused($milestone);
        }

        if ($amount !== null && $amount < 0) {
            throw new InvalidArgumentException('A payment is not below zero.');
        }

        $adminId = $this->adminId($source);
        $at = $at !== null ? CarbonImmutable::instance($at) : CarbonImmutable::now();

        return $this->locked($order, function (Order $locked) use ($milestone, $source, $at, $reference, $note, $adminId, $amount): MilestoneMark {
            if ($this->forbidden($locked, $milestone)) {
                throw new OrderCancelledAlready("«{$milestone->value}»");
            }

            [$mark, $event] = $this->setMilestone($locked, $milestone, $source, $adminId, $at, self::cut($reference, 190), self::cut($note, 500), $amount);

            if ($event !== null) {
                $this->events->dispatch($event($this->snapshot($locked)));
            }

            return $mark;
        });
    }

    /**
     * A payment try that did not go through (§5.4): a history line and `payment_failed_at`, not a
     * milestone — there may be several. The order keeps awaiting payment.
     */
    public function paymentFailed(Order $order, string $source, ?string $reason = null): void
    {
        $adminId = $this->adminId($source);
        $reason = self::text($reason, 1000);

        $this->locked($order, function (Order $locked) use ($source, $reason, $adminId): void {
            $at = CarbonImmutable::now();

            // A late answer of a gateway about an order paid meanwhile is history, not a flag:
            // «paid» is what took the flag down.
            if ($locked->paid_at === null) {
                $locked->setAttribute('payment_failed_at', $at);
                $locked->save();
            }

            $this->line($locked, StatusChange::PAYMENT_FAILED, $source, $adminId, $at, $reason);
            $this->events->dispatch(new OrderPaymentFailed($this->snapshot($locked), $reason, $source, $adminId, $at));
        });
    }

    /**
     * The order stops awaiting payment without being paid (§5.5, decision 42): its method was
     * switched to one that does not await it. A history line, no event; an order that does not
     * await payment is silence.
     *
     * @throws OrderPaidAlready
     * @throws OrderCancelledAlready
     */
    public function stopAwaitingPayment(Order $order, string $source): void
    {
        $adminId = $this->adminId($source);

        $this->locked($order, function (Order $locked) use ($source, $adminId): void {
            if ($locked->cancelled_at !== null) {
                throw new OrderCancelledAlready('to stop awaiting payment');
            }

            if ($locked->paid_at !== null) {
                throw new OrderPaidAlready('to stop awaiting payment');
            }

            if (! $locked->awaiting_payment) {
                return;
            }

            $at = CarbonImmutable::now();
            $locked->awaiting_payment = false;
            $locked->save();

            // The row «awaiting_payment» of order_milestones stays: it is history (decision 24).
            $this->line($locked, StatusChange::PAYMENT_AWAITING_STOPPED, $source, $adminId, $at);
        });
    }

    /**
     * The order starts awaiting payment again (§36.3): its method was switched to one that awaits
     * it — cash on receipt → online (§41.2). The pair of `stopAwaitingPayment()` (decision 42): the
     * flag, the milestone «awaiting payment» if it was never set, a history line, no event; an order
     * that awaits payment already is silence.
     *
     * @throws OrderPaidAlready
     * @throws OrderCancelledAlready
     */
    public function awaitPayment(Order $order, string $source): void
    {
        $adminId = $this->adminId($source);

        $this->locked($order, function (Order $locked) use ($source, $adminId): void {
            if ($locked->cancelled_at !== null) {
                throw new OrderCancelledAlready('to await payment');
            }

            if ($locked->paid_at !== null) {
                throw new OrderPaidAlready('to await payment');
            }

            if ($locked->awaiting_payment) {
                return;
            }

            $at = CarbonImmutable::now();
            $locked->awaiting_payment = true;
            $locked->save();

            $set = OrderMilestone::query()->where('order_id', $locked->id)->where('milestone', Milestone::AwaitingPayment->value)->exists();

            if (! $set) {
                OrderMilestone::query()->insert([
                    'order_id' => $locked->id,
                    'milestone' => Milestone::AwaitingPayment->value,
                    'at' => $at,
                    'source' => self::cut($source, 64),
                    'admin_id' => $adminId,
                    'created_at' => $at,
                ]);
            }

            $this->line($locked, StatusChange::PAYMENT_AWAITING, $source, $adminId, $at);
        });
    }

    /** §36.1 — the order's money in one answer: owed, paid, refunded, due (> 0), over (> 0). */
    public function balance(Order $order): Balance
    {
        return Balance::of($order);
    }

    /**
     * Money that came in for an order that is paid already: a top-up after an edit (§36.3, §37).
     * No milestone — «paid» is set once (decision 24) and `OrderPaid` is not dispatched again;
     * `paid_total` grows, a history line `payment` and `OrderPaymentReceived`. More than is due
     * is taken as it came: the balance shows it as money to give back.
     *
     * A dry run answers the balance after it, and a refusal in the answer; it writes nothing.
     *
     * @throws ValidationException the amount is not a positive number of minor units
     * @throws PaymentRefused `not_paid` — the first payment is `mark(paid)`; `cancelled`
     */
    public function recordPayment(
        Order $order,
        int $amount,
        string $source,
        ?string $reference = null,
        ?CarbonInterface $at = null,
        ?string $note = null,
        bool $dryRun = false,
    ): PaymentRecord {
        if ($amount < 1) {
            throw ValidationException::withMessages(['amount' => (string) __('webx-orders::panel.payment-amount-required')]);
        }

        $reference = self::text($reference, 190);
        $note = self::text($note, 500);
        $at = $at !== null ? CarbonImmutable::instance($at) : CarbonImmutable::now();

        if ($dryRun) {
            return $this->paymentPlan(Order::query()->findOrFail($order->id), $amount, $reference, $note, $at, true);
        }

        $adminId = $this->adminId($source);

        return $this->locked($order, function (Order $locked) use ($amount, $source, $reference, $note, $at, $adminId): PaymentRecord {
            $plan = $this->paymentPlan($locked, $amount, $reference, $note, $at, false);

            if ($plan->refusal !== null) {
                throw new PaymentRefused($plan);
            }

            $locked->paid_total += $amount;
            $locked->balance = Balance::stored($locked);
            $locked->save();

            $this->line($locked, StatusChange::PAYMENT, $source, $adminId, $at, self::paymentNote($amount, $reference, $note));
            $this->events->dispatch(new OrderPaymentReceived($this->snapshot($locked), $amount, $reference, $plan->after, $source, $adminId, $at));

            return $plan;
        });
    }

    /**
     * §37 — a manager changes the lines of a placed order: the buyer called and wants another
     * thing, or one of two, or a line is out of stock. The lines that were keep their price and
     * their discounts in proportion to the qty; a line added comes at the provider's price now,
     * through a cart of its own as `placeByManager()` makes it (§29.2), without the rules'
     * discounts; the discounts of the whole order, the delivery and the benefits stay as placed
     * (decision 101, variant A). A line is never deleted: taken out, it keeps the units refunded
     * of it. The money is counted again, the balance with it, and the edit is a row of
     * `order_revisions` and a line of the history; `OrderItemsChanging` may refuse it inside the
     * transaction, `OrderItemsChanged` goes after the commit.
     *
     * The milestones stay (§37.5): a paid order that owes more now owes it by its balance.
     *
     * A dry run answers the same — the cart of the lines added is made, counted and rolled back —
     * with a refusal in the answer, and writes nothing.
     *
     * @throws ValidationException no reason from the panel or an agent; a line added the cart refuses, under `add.N.*`
     * @throws CartNotOrderable a line added that cannot be bought, or one in another currency
     * @throws ItemsEditRefused
     * @throws OrderRefused a listener of OrderItemsChanging would not have it
     */
    public function editItems(Order $order, ItemsEdit $edit, bool $dryRun = false): ItemsEditResult
    {
        $reason = self::text($edit->reason, 500);
        $message = self::text($edit->message, 1000);

        if (in_array($edit->source, ['panel', 'mcp'], true)) {
            $this->validation->make(['reason' => $reason], ['reason' => ['required', 'string']], [
                'reason.required' => (string) __('webx-orders::panel.items-reason-required'),
            ])->validate();
        }

        if ($dryRun) {
            // The cart of the lines added is real for the count, and goes with the rollback.
            $this->db->beginTransaction();

            try {
                return $this->itemsPlan(Order::query()->findOrFail($order->id), $edit, true)->result;
            } finally {
                $this->db->rollBack();
            }
        }

        $adminId = $this->adminId($edit->source);

        return $this->locked($order, function (Order $locked) use ($edit, $reason, $message, $adminId): ItemsEditResult {
            $plan = $this->itemsPlan($locked, $edit, false);

            if ($plan->result->refusal !== null) {
                throw new ItemsEditRefused($plan->result);
            }

            return $this->writeEdit($locked, $edit, $plan, $reason, $message, $adminId);
        });
    }

    /**
     * Move the order to another internal status (§5.2): freely, back too. A status with a
     * milestone sets it with the source `status#<id>`; when the order may not take it, the move
     * is refused whole. The same status is silence. A dry run answers the same and writes nothing.
     *
     * A status that cancels the order is a cancellation: the panel and an agent give its reason
     * in the note, as they do to `cancel()` (§9.1), and a dry run asks for it too.
     *
     * @throws StatusMoveRefused
     * @throws ValidationException no note to a status that cancels, from the panel or an agent
     */
    public function moveTo(Order $order, OrderStatus $to, string $source, ?string $note = null, bool $dryRun = false): StatusMove
    {
        $note = self::text($note, 1000);

        if ($dryRun) {
            $move = $this->plan(Order::query()->findOrFail($order->id), $to, true);
            $this->reasonOf($move, $source, $note);

            return $move;
        }

        $adminId = $this->adminId($source);

        return $this->locked($order, function (Order $locked) use ($to, $source, $note, $adminId): StatusMove {
            $move = $this->plan($locked, $to, false);

            if ($move->refusal !== null) {
                throw new StatusMoveRefused($move);
            }

            $this->reasonOf($move, $source, $note);

            if (! $move->moved) {
                return $move;
            }

            $at = CarbonImmutable::now();
            $locked->status_id = $move->to->id;
            $locked->save();

            StatusChange::query()->insert([
                'order_id' => $locked->id,
                'kind' => StatusChange::STATUS,
                'from_status_id' => $move->from->id,
                'to_status_id' => $move->to->id,
                'from_public_id' => $move->publicFrom->id,
                'to_public_id' => $move->publicTo->id,
                'source' => $source,
                'admin_id' => $adminId,
                'note' => $note,
                'created_at' => $at,
            ]);

            $event = null;

            if ($move->milestone !== null) {
                [, $event] = $this->setMilestone($locked, $move->milestone, "status#{$move->to->id}", $adminId, $at, null, self::cut($note, 500));
            }

            // One snapshot for all of them, after everything; the order of §7.2: the status, the
            // public one, the milestone — all after the one commit.
            $data = $this->snapshot($locked);
            $this->events->dispatch(new OrderStatusChanged($data, $move->from, $move->to, $move->publicChanged, $note, $source, $adminId, $at));

            if ($move->publicChanged) {
                $this->events->dispatch(new OrderPublicStatusChanged($data, $move->publicFrom, $move->publicTo, $source, $adminId, $at));
            }

            if ($event !== null) {
                $this->events->dispatch($event($data));
            }

            return $move;
        });
    }

    /**
     * Write a part of a block (§6.4) — the waybill number into `delivery`, a new method into
     * `payment`: merged into what is there, checked by the block's owner, a history line and
     * `OrderBlockUpdated`. A patch that changes nothing is silence.
     *
     * @param  array<string, mixed>  $patch
     *
     * @throws BlockRefused a block no module registered, or the result its owner refuses
     */
    public function patchBlock(Order $order, string $block, array $patch, string $source): void
    {
        if (! $this->blocks->has($block)) {
            throw new BlockRefused($block, ['no module registered it']);
        }

        $adminId = $this->adminId($source);

        $this->locked($order, function (Order $locked) use ($block, $patch, $source, $adminId): void {
            /** @var array<string, mixed>|null $current */
            $current = $locked->getAttribute($block);
            $merged = array_replace_recursive($current ?? [], $patch);

            if ($current !== null && $merged === $current) {
                return;
            }

            $this->blocks->check([$block => $merged]);

            $at = CarbonImmutable::now();
            $locked->setAttribute($block, $merged);
            $locked->save();

            $this->line($locked, StatusChange::BLOCK, $source, $adminId, $at, $block);
            $this->events->dispatch(new OrderBlockUpdated($this->snapshot($locked), $block, $patch, $source, $adminId, $at));
        });
    }

    /** A line in the order's history from a module (§5.3, §8): «the parcel came back». No event. */
    public function note(Order $order, string $text, string $source): void
    {
        $text = self::text($text, 1000) ?? throw new InvalidArgumentException('A note says something.');

        $this->line($order, StatusChange::NOTE, $source, $this->adminId($source), CarbonImmutable::now(), $text);
    }

    /**
     * Cancel the order (§9.1): the milestone «cancelled» with its reason — one the panel and the
     * agent must give — and the tenders left on it marked given back in the same transaction.
     * The order comes into the first active status that cancels (decision 88), as a move does:
     * its history line with the reason, its events, the letters of its public status. A site with
     * no such status keeps the order where it is, and `webx:doctor` says so.
     * Money is not given back: a paid order answers `wasPaid` and how much is left, and the
     * manager records the refund. A cancelled order is silence; one given back whole too.
     *
     * A dry run answers the same and writes nothing.
     *
     * @throws ValidationException no reason from the panel or an agent
     */
    public function cancel(Order $order, string $source, ?string $reason = null, bool $dryRun = false): MilestoneMark
    {
        $reason = self::text($reason, 500);

        if (in_array($source, ['panel', 'mcp'], true)) {
            $this->validation->make(['reason' => $reason], ['reason' => ['required', 'string']], [
                'reason.required' => (string) __('webx-orders::panel.cancel-reason-required'),
            ])->validate();
        }

        $adminId = $this->adminId($source);

        if ($dryRun) {
            return $this->cancelling(Order::query()->findOrFail($order->id), $source, $adminId, $reason, true);
        }

        return $this->locked($order, fn (Order $locked): MilestoneMark => $this->cancelling($locked, $source, $adminId, $reason, false));
    }

    /**
     * Record money given back outside the site (§9.3, §9.4, decision 27): `manual` only, no
     * gateway is called — the wallet gives the tenders back on `OrderRefunded`. Nothing is
     * counted for the manager: the amount, each tender and each qty are checked against what is
     * left of them. When the money and every tender are back — by refunds, or the tenders by a
     * cancellation — the milestone «refunded» is set, once (§9.2). The lines and the totals of the
     * order stay as they were; the refunds lie beside them.
     *
     * A dry run answers what is left and whether it would be full, and a refusal in the answer.
     *
     * @throws RefundRefused
     */
    public function refund(Order $order, RefundRequest $request, bool $dryRun = false): RefundResult
    {
        if ($dryRun) {
            return $this->refundPlan(Order::query()->findOrFail($order->id), $request, true);
        }

        $adminId = $this->adminId($request->source);
        $reference = self::text($request->reference, 190);
        $reason = self::text($request->reason, 500);

        return $this->locked($order, function (Order $locked) use ($request, $adminId, $reference, $reason): RefundResult {
            $plan = $this->refundPlan($locked, $request, false);

            if ($plan->refusal !== null) {
                throw new RefundRefused($plan);
            }

            $at = CarbonImmutable::now();
            $refund = new OrderRefund([
                'order_id' => $locked->id,
                'amount' => $plan->amount,
                'tenders' => array_map(static fn (int $id, int $amount): array => ['tender_id' => $id, 'amount' => $amount], array_keys($plan->tenders), $plan->tenders),
                'items' => array_map(static fn (int $id, int $qty): array => ['item_id' => $id, 'qty' => $qty], array_keys($plan->items), $plan->items),
                'method' => OrderRefund::MANUAL,
                'reference' => $reference,
                'reason' => $reason,
                'source' => (string) self::cut($request->source, 64),
                'admin_id' => $adminId,
                'created_at' => $at,
            ]);
            $refund->save();

            foreach ($plan->tenders as $id => $amount) {
                OrderTender::query()->whereKey($id)->increment('refunded_amount', $amount);
            }

            foreach ($plan->items as $id => $qty) {
                OrderItem::query()->whereKey($id)->increment('refunded_qty', $qty);
            }

            $locked->refunded_total += $plan->amount;
            $locked->balance = Balance::stored($locked);

            if ($plan->milestone !== null) {
                // Its event is OrderRefunded itself, with full = true.
                $this->setMilestone($locked, $plan->milestone, $request->source, $adminId, $at, $reference, $reason);
            } else {
                $locked->save();
            }

            $this->events->dispatch(new OrderRefunded($this->snapshot($locked), RefundData::of($refund), $plan->full, $request->source, $adminId, $at));

            return $plan->recorded($refund->id);
        });
    }

    /**
     * Reverse a refund recorded by mistake (decision 98, §9.5): a refund is never edited. The row
     * stays with when, who and why it was reversed, and what it gave back comes off the order —
     * `refunded_total`, the `refunded_amount` of its tenders, the `refunded_qty` of its lines. The
     * tenders a cancellation gave back are not the record's and stay given back. When the order is
     * no longer given back whole, «refunded» comes off — the one milestone that ever does, and
     * only here — with its line in the history; a new full refund sets it again. The right one is
     * then recorded as any refund.
     *
     * A dry run answers what is left after it and whether the milestone comes off, and a refusal
     * in the answer; it writes nothing.
     *
     * @throws ValidationException no reason from the panel or an agent
     * @throws RefundReversalRefused another order's record, or one reversed already
     */
    public function reverseRefund(Order $order, int $refundId, ?string $reason, string $source, bool $dryRun = false): RefundReversal
    {
        $reason = self::text($reason, 500);

        if (in_array($source, ['panel', 'mcp'], true)) {
            $this->validation->make(['reason' => $reason], ['reason' => ['required', 'string']], [
                'reason.required' => (string) __('webx-orders::panel.reverse-reason-required'),
            ])->validate();
        }

        if ($dryRun) {
            return $this->reversalPlan(Order::query()->findOrFail($order->id), $refundId, true);
        }

        $adminId = $this->adminId($source);

        return $this->locked($order, function (Order $locked) use ($refundId, $reason, $source, $adminId): RefundReversal {
            $plan = $this->reversalPlan($locked, $refundId, false);

            if ($plan->refusal !== null) {
                throw new RefundReversalRefused($plan);
            }

            $at = CarbonImmutable::now();
            $refund = OrderRefund::query()->whereKey($refundId)->firstOrFail();
            $refund->forceFill([
                'reversed_at' => $at,
                'reversed_source' => self::cut($source, 64),
                'reversed_by' => $adminId,
                'reversal_reason' => $reason,
            ])->save();

            foreach ($plan->tenders as $id => $amount) {
                OrderTender::query()->whereKey($id)->decrement('refunded_amount', $amount);
            }

            foreach ($plan->items as $id => $qty) {
                OrderItem::query()->whereKey($id)->decrement('refunded_qty', $qty);
            }

            $locked->refunded_total = $plan->refundedTotal;
            $locked->balance = Balance::stored($locked);
            $this->line($locked, StatusChange::REFUND_REVERSED, $source, $adminId, $at, "refund#{$refund->id}".($reason !== null ? ": {$reason}" : ''));

            if ($plan->milestoneRemoved !== null) {
                OrderMilestone::query()->where('order_id', $locked->id)->where('milestone', $plan->milestoneRemoved->value)->delete();
                $locked->setAttribute($plan->milestoneRemoved->column(), null);
                $this->line($locked, StatusChange::MILESTONE_REMOVED, $source, $adminId, $at, $plan->milestoneRemoved->value);
            }

            $locked->save();

            $this->events->dispatch(new OrderRefundReversed($this->snapshot($locked), RefundData::of($refund), $reason, $plan->milestoneRemoved !== null, $source, $adminId, $at));

            return $plan;
        });
    }

    /**
     * Send a message about the order again by hand (decision 99): «the buyer says the letter never
     * came». A row of the order's log goes again — the same message to the same recipient, a new
     * row of the log marked manual with the administrator — or, with `$event`, the buyer is told
     * of an event the order has a reason for, to its e-mail now, through the event's channels that
     * write to the buyer. Either is a line of the history, `notification_sent`, with who did it.
     *
     * The order is locked while it is sent, and the notifications module refuses the same message
     * again within a minute: a second press sends nothing. A dry run answers what would go — for
     * an event, each channel's message — and writes nothing.
     *
     * @param  array{delivery?: int, event?: string}  $what
     *
     * @throws NotifyRefused no notifications module, no e-mail, no reason, no channel, another order's row, a repeat
     */
    public function notify(Order $order, array $what, string $source, bool $dryRun = false): NotifyResult
    {
        $channels = Container::getInstance()->make(OrderChannels::class);
        $adminId = $this->adminId($source);
        $send = static fn (Order $one): NotifyResult => isset($what['delivery'])
            ? $channels->resend($one, (int) $what['delivery'], $adminId, $dryRun)
            : $channels->notifyBuyer($one, (string) ($what['event'] ?? ''), $adminId, $dryRun);

        if ($dryRun) {
            return $send(Order::query()->findOrFail($order->id));
        }

        return $this->locked($order, function (Order $locked) use ($send, $source, $adminId): NotifyResult {
            $result = $send($locked);
            $this->line($locked, StatusChange::NOTIFICATION_SENT, $source, $adminId, CarbonImmutable::now(), self::cut($result->event.': '.implode(', ', $result->recipients()), 1000));

            return $result;
        });
    }

    /**
     * The contacts, the recipient, the comment, the manager note (§8) — the lines, the money and
     * the milestones never. What changed goes to the panel's journal and to the order's timeline,
     * and `OrderDetailsChanged` carries the old values; a patch that changes nothing is silence.
     *
     * @throws ValidationException
     */
    public function updateDetails(Order $order, OrderDetails $patch, string $source): void
    {
        if ($patch->isEmpty()) {
            return;
        }

        $adminId = $this->adminId($source);

        $this->locked($order, function (Order $locked) use ($patch, $source, $adminId): void {
            $before = $this->details($locked);
            $after = $before;

            if ($patch->has('customer')) {
                /** @var Contact $customer */
                $customer = $patch->get('customer');
                $customer = $customer->normalised();
                $after = [
                    ...$after,
                    'customer_first_name' => $customer->firstName,
                    'customer_last_name' => $customer->lastName,
                    'customer_phone' => $customer->phone,
                    'customer_email' => $customer->email,
                ];
            }

            if ($patch->has('recipient')) {
                /** @var Contact|null $recipient */
                $recipient = $patch->get('recipient');
                $recipient = $recipient?->normalised();
                $after['recipient'] = $recipient !== null
                    ? ['first_name' => $recipient->firstName, 'last_name' => $recipient->lastName, 'phone' => $recipient->phone]
                    : null;
            }

            foreach (['comment', 'manager_note'] as $field) {
                if ($patch->has($field)) {
                    $value = $patch->get($field);
                    $after[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
                }
            }

            $customer = new Contact((string) $after['customer_first_name'], $after['customer_last_name'], $after['customer_phone'], $after['customer_email']);
            /** @var array{first_name: string, last_name: string|null, phone: string|null}|null $recipient */
            $recipient = $after['recipient'];
            $this->validate(
                $customer,
                $recipient !== null ? new Contact($recipient['first_name'], $recipient['last_name'] ?? null, $recipient['phone'] ?? null) : null,
                $after['comment'],
                $after['manager_note'],
            );

            $fields = array_keys(array_filter($after, static fn (mixed $value, string $field): bool => $value !== $before[$field], ARRAY_FILTER_USE_BOTH));

            if ($fields === []) {
                return;
            }

            $at = CarbonImmutable::now();
            $locked->fill(array_intersect_key($after, array_flip($fields)));

            if (in_array('customer_phone', $fields, true)) {
                $locked->customer_phone_key = $customer->phoneKey();
            }

            $locked->save();

            $changes = [];

            foreach ($fields as $field) {
                $changes[$field] = [self::shown($before[$field]), self::shown($after[$field])];
            }

            $this->journal->record($locked, 'updated', $changes);
            $this->line($locked, StatusChange::DETAILS, $source, $adminId, $at, implode(',', $fields));
            $this->events->dispatch(new OrderDetailsChanged(
                $this->snapshot($locked),
                $fields,
                array_intersect_key($before, array_flip($fields)),
                $source,
                $adminId,
                $at,
            ));
        });
    }

    /**
     * The order belongs to this visitor from now on (§13.3): it shows in their «My orders». A
     * guest's order is linked, another visitor's is moved in the same step. Only `user_id`
     * changes — the contacts stay what the buyer typed. A line of the history, the journal and
     * `OrderDetailsChanged` with the old `user_id`; the visitor is not written to.
     *
     * A dry run answers the same and writes nothing.
     *
     * @throws VisitorRefused no such visitor, or no visitors module
     */
    public function linkVisitor(Order $order, int $userId, string $source, bool $dryRun = false): VisitorLink
    {
        $visitor = Container::getInstance()->make(Visitors::class)->find($userId) ?? throw new VisitorRefused;

        return $this->relink($order, $visitor, $source, $dryRun);
    }

    /** The order is a guest's again (§13.3): gone from the visitor's «My orders», nothing else. */
    public function unlinkVisitor(Order $order, string $source, bool $dryRun = false): VisitorLink
    {
        return $this->relink($order, null, $source, $dryRun);
    }

    /**
     * A visitor's account is going (§13.2, `VisitorDeleting`): their orders become guests' — the
     * contacts in them are the record of the sale and stay. One update and one line of history
     * an order, no event: nobody is told an order changed hands, because it did not.
     *
     * @return int how many orders let go
     */
    public function forgetVisitor(int $userId, string $source): int
    {
        $ids = Order::query()->where('user_id', $userId)->pluck('id')->all();

        if ($ids === []) {
            return 0;
        }

        $visitor = Container::getInstance()->make(Visitors::class)->find($userId);
        $note = 'unlinked: '.($visitor !== null ? VisitorLink::name($visitor) : "#{$userId}");
        $at = CarbonImmutable::now();

        $this->db->transaction(function () use ($ids, $source, $note, $at): void {
            Order::query()->whereIn('id', $ids)->update(['user_id' => null]);
            StatusChange::query()->insert(array_map(static fn (int $id): array => [
                'order_id' => $id,
                'kind' => StatusChange::VISITOR,
                'source' => (string) self::cut($source, 64),
                'admin_id' => null,
                'note' => $note,
                'created_at' => $at,
            ], $ids));
        });

        return count($ids);
    }

    private function relink(Order $order, ?Visitor $visitor, string $source, bool $dryRun): VisitorLink
    {
        $plan = function (?int $before) use ($visitor, $dryRun): VisitorLink {
            $previous = $before !== null ? Container::getInstance()->make(Visitors::class)->find($before) : null;
            $outcome = $before === $visitor?->id
                ? VisitorLink::UNCHANGED
                : ($visitor !== null ? VisitorLink::LINKED : VisitorLink::UNLINKED);

            return new VisitorLink($outcome, $visitor, $before, $previous, $dryRun);
        };

        if ($dryRun) {
            $before = Order::query()->whereKey($order->id)->value('user_id');

            return $plan($before !== null ? (int) $before : null);
        }

        $adminId = $this->adminId($source);

        return $this->locked($order, function (Order $locked) use ($plan, $visitor, $source, $adminId): VisitorLink {
            $before = $locked->user_id;
            $link = $plan($before);

            if (! $link->changed()) {
                return $link;
            }

            $at = CarbonImmutable::now();
            $named = static fn (?Visitor $one, ?int $id): ?string => $one !== null ? VisitorLink::name($one) : ($id !== null ? "#{$id}" : null);
            $locked->user_id = $visitor?->id;
            $locked->save();

            $this->journal->record($locked, 'updated', ['user_id' => [$named($link->previous, $before), $named($visitor, $visitor?->id)]]);
            $this->line($locked, StatusChange::VISITOR, $source, $adminId, $at, $visitor !== null
                ? 'linked: '.VisitorLink::name($visitor)
                : 'unlinked: '.$named($link->previous, $before));
            $this->events->dispatch(new OrderDetailsChanged($this->snapshot($locked), ['user_id'], ['user_id' => $before], $source, $adminId, $at));

            return $link;
        });
    }

    /**
     * The contacts before the cart: a form with a wrong phone is answered by field and makes no
     * row, even one rolled back.
     *
     * @throws ValidationException
     */
    private function validateManager(ManagerOrder $input): void
    {
        $comment = $input->comment !== null && trim($input->comment) !== '' ? trim($input->comment) : null;

        $this->validate($input->customer->normalised(), $input->recipient?->normalised(), $comment);
    }

    private function write(PlaceOrder $input, Contact $customer, ?Contact $recipient, ?string $comment, ?string $key): Order
    {
        // 2. The lock of the cart's row, the first of the locks (§6.3): the same cart is not
        // ordered twice from two tabs, and a line added meanwhile waits for the order.
        $cart = $this->carts->lock($input->cart);

        // The twin that waited for the lock finds the order the first one made.
        if ($key !== null && ($order = $this->byHash($key)) !== null) {
            return $order;
        }

        // 3–4. Counted for the order: a source that fails fails the order, not a line of it.
        $state = $this->calculator->calculate($cart, $input->locale, Purpose::Order);
        $this->orderable($cart, $state);

        if ($input->expectedPayable !== null && $state->payable !== $input->expectedPayable) {
            throw new PayableChanged($state, $input->expectedPayable);
        }

        $status = $this->statuses->for($input->awaitPayment);
        $at = CarbonImmutable::now();
        $adminId = $this->adminId($input->source);

        // 6. The order, then its number from its id (§4).
        $order = Order::query()->create([
            'number' => null,
            'idempotency_key' => $key,
            'user_id' => $input->userId,
            'cart_id' => $cart->id,
            'locale' => $input->locale,
            'currency' => (string) $state->currency,
            'status_id' => $status->id,
            'subtotal' => $state->subtotal,
            'adjustments_total' => array_sum(array_map(static fn (Adjustment $adjustment): int => $adjustment->amount, $state->adjustments)),
            'total' => $state->total,
            'tenders_total' => array_sum(array_map(static fn (Tender $tender): int => $tender->amount, $state->tenders)),
            'payable' => $state->payable,
            'refunded_total' => 0,
            'paid_total' => 0,
            'balance' => $state->payable,
            'items_revision' => 0,
            'customer_first_name' => $customer->firstName,
            'customer_last_name' => $customer->lastName,
            'customer_phone' => $customer->phone,
            'customer_phone_key' => $customer->phoneKey(),
            'customer_email' => $customer->email,
            'recipient' => $recipient !== null
                ? ['first_name' => $recipient->firstName, 'last_name' => $recipient->lastName, 'phone' => $recipient->phone]
                : null,
            'comment' => $comment,
            'delivery' => $input->blocks['delivery'] ?? null,
            'payment' => $input->blocks['payment'] ?? null,
            'placed_at' => $at,
            'awaiting_payment' => $input->awaitPayment,
        ]);
        $order->number = $this->numbers->format($order->id);
        $order->save();

        $items = $this->writeItems($order, $state);
        $this->writeMoney($order, $state, $items);
        $this->writeHistory($order, $status, $input, $at, $adminId);

        // 7. What must happen with the order or not at all; a refusal rolls all of it back.
        $this->events->dispatch(new OrderPlacing($order, $state, $input->source, $adminId, $at));

        // 8. The lines go; the cart, its cookie and the checkout's draft stay (decision 34).
        $this->carts->empty($cart);

        // After the commit — the dispatcher holds it till then.
        $this->events->dispatch(new OrderPlaced(OrderData::of($order), $input->awaitPayment, $input->source, $adminId, $at));

        return $order;
    }

    /**
     * Lines are never dropped quietly: an empty cart, another currency, or a line that cannot be
     * bought is a refusal the checkout shows the buyer. Public for the agent's trial confirmation
     * (`orders_checkout_preview`), which asks it of a count it made itself.
     *
     * @throws CartNotOrderable
     */
    public function orderable(Cart $cart, CartState $state): void
    {
        $stored = array_filter($state->lines, static fn (ResolvedLine $line): bool => ! $line->generated());

        if ($stored === []) {
            throw new CartNotOrderable(CartNotOrderable::EMPTY, $state);
        }

        $currency = $this->currencies->currency();

        if ($currency === null || $cart->currency !== $currency) {
            throw new CartNotOrderable(CartNotOrderable::CURRENCY, $state);
        }

        $blocking = array_values(array_filter(
            $state->warnings,
            static fn ($warning): bool => $warning->line !== null && in_array($warning->code, CartNotOrderable::BLOCKING, true),
        ));

        if ($blocking !== []) {
            throw new CartNotOrderable(CartNotOrderable::LINES, $state, $blocking);
        }
    }

    /**
     * The lines, a query a level: the roots, their ids by position, then the children under them.
     * A child's qty is the absolute one the chain counted.
     *
     * @return array<string, int> the order line's id by the cart line's key
     */
    private function writeItems(Order $order, CartState $state): array
    {
        $positions = [];

        foreach ($state->lines as $position => $line) {
            $positions[(string) $line->key] = $position;
        }

        $ids = [];

        foreach ([false, true] as $children) {
            $rows = [];

            foreach ($state->lines as $position => $line) {
                if (($line->parentId !== null) !== $children) {
                    continue;
                }

                $adjustments = array_sum(array_map(static fn (Adjustment $adjustment): int => $adjustment->amount, $state->lineAdjustments[(string) $line->key] ?? []));
                $rows[] = $this->itemRow($order, $line, $position, $children ? $ids[(string) $line->parentId] ?? null : null, $adjustments);
            }

            if ($rows === []) {
                continue;
            }

            OrderItem::query()->insert($rows);

            foreach (OrderItem::query()->where('order_id', $order->id)->pluck('id', 'position') as $position => $id) {
                $ids[(string) $state->lines[(int) $position]->key] = (int) $id;
            }
        }

        return $ids;
    }

    /** @return array<string, mixed> */
    private function itemRow(Order $order, ResolvedLine $line, int $position, ?int $parentId, int $adjustments): array
    {
        $item = $line->line;
        $price = $line->price ?? 0;
        $names = array_filter($item->names ?? [], static fn (mixed $name): bool => is_string($name) && $name !== '');

        return [
            'order_id' => $order->id,
            'parent_id' => $parentId,
            'position' => $position,
            'type' => $line->type,
            'ref_id' => $line->refId,
            'options' => $line->options !== null ? json_encode($line->options, JSON_UNESCAPED_UNICODE) : null,
            'sku' => self::cut($item?->sku, 64),
            'name' => json_encode($names !== [] ? $names : [$order->locale => $line->name], JSON_UNESCAPED_UNICODE),
            'options_label' => self::cut($item?->optionsLabel, 255),
            // An address cut short is a broken one: none is better.
            'image' => $item?->image !== null && mb_strlen($item->image) <= 255 ? $item->image : null,
            'source' => self::cut($line->source, 64),
            'label' => self::cut($line->label, 64),
            'price' => $price,
            'qty' => $line->effectiveQty,
            'adjustments_total' => $adjustments,
            'total' => $price * $line->effectiveQty + $adjustments,
            'shippable' => $item->shippable ?? true,
        ];
    }

    /**
     * Adjustments, tenders and benefits: a query a table, labels as the chain gave them in the
     * order's language — a rule renamed later does not rename the order's discount.
     *
     * @param  array<string, int>  $items
     */
    private function writeMoney(Order $order, CartState $state, array $items): void
    {
        $adjustments = array_map(static fn (Adjustment $adjustment): array => [
            'order_id' => $order->id,
            'item_id' => $adjustment->line !== null ? $items[(string) $adjustment->line] ?? null : null,
            'source' => (string) self::cut($adjustment->source, 64),
            'label' => (string) self::cut($adjustment->label, 190),
            'amount' => $adjustment->amount,
            'meta' => $adjustment->meta !== [] ? json_encode($adjustment->meta, JSON_UNESCAPED_UNICODE) : null,
        ], $state->adjustments);

        $tenders = array_map(static fn (Tender $tender): array => [
            'order_id' => $order->id,
            'source' => (string) self::cut($tender->source, 64),
            'label' => (string) self::cut($tender->label, 190),
            'amount' => $tender->amount,
            'meta' => $tender->meta !== [] ? json_encode($tender->meta, JSON_UNESCAPED_UNICODE) : null,
        ], $state->tenders);

        // One of a kind: two sources that both pay the shipping are one fact for the waybill.
        $benefits = [];

        foreach ($state->benefits as $benefit) {
            $benefits[$benefit->key] ??= [
                'order_id' => $order->id,
                'key' => (string) self::cut($benefit->key, 64),
                'label' => (string) self::cut($benefit->label, 190),
                'source' => (string) self::cut($benefit->source, 64),
            ];
        }

        foreach ([OrderAdjustment::class => $adjustments, OrderTender::class => $tenders, OrderBenefit::class => array_values($benefits)] as $model => $rows) {
            if ($rows !== []) {
                $model::query()->insert($rows);
            }
        }
    }

    /** «Placed», «awaiting payment» when it does, and the first line of the history: none → the initial status. */
    private function writeHistory(Order $order, OrderStatus $status, PlaceOrder $input, CarbonImmutable $at, ?int $adminId): void
    {
        $milestones = [Milestone::Placed];

        if ($input->awaitPayment) {
            $milestones[] = Milestone::AwaitingPayment;
        }

        OrderMilestone::query()->insert(array_map(static fn (Milestone $milestone): array => [
            'order_id' => $order->id,
            'milestone' => $milestone->value,
            'at' => $at,
            'source' => $input->source,
            'admin_id' => $adminId,
            'created_at' => $at,
        ], $milestones));

        StatusChange::query()->insert([
            'order_id' => $order->id,
            'kind' => StatusChange::STATUS,
            'from_status_id' => null,
            'to_status_id' => $status->id,
            'from_public_id' => null,
            'to_public_id' => $status->public_status_id,
            'source' => $input->source,
            'admin_id' => $adminId,
            'created_at' => $at,
        ]);
    }

    /**
     * The order needs a first name and a way to reach the buyer — a phone or an e-mail; what else
     * a form requires is the checkout's. Answered like a form, keyed by field, in the module's
     * words ({@see ContactRules}).
     *
     * @throws ValidationException
     */
    private function validate(Contact $customer, ?Contact $recipient, ?string $comment, ?string $managerNote = null): void
    {
        $data = [
            'customer' => ['first_name' => $customer->firstName, 'last_name' => $customer->lastName, 'phone' => $customer->phone, 'email' => $customer->email],
            'comment' => $comment,
            'manager_note' => $managerNote,
        ];

        if ($recipient !== null) {
            $data['recipient'] = ['first_name' => $recipient->firstName, 'last_name' => $recipient->lastName, 'phone' => $recipient->phone];
        }

        $this->validation->make($data, ContactRules::rules($recipient !== null), ContactRules::messages(), ContactRules::attributes())->validate();
    }

    /**
     * One transaction with the order's row locked (§5.3): two writers of one order take turns,
     * and the second sees what the first did. The model passed in is brought up to date after.
     *
     * @template T
     *
     * @param  Closure(Order): T  $work
     * @return T
     */
    private function locked(Order $order, Closure $work): mixed
    {
        $locked = null;
        $result = $this->db->transaction(function () use ($order, $work, &$locked): mixed {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            return $work($locked);
        });

        if ($locked instanceof Order) {
            $order->setRawAttributes($locked->getAttributes(), true);
            $order->unsetRelations();
        }

        return $result;
    }

    /** «Paid» and «received» are not for a cancelled order (§5.1). */
    private function forbidden(Order $order, Milestone $milestone): bool
    {
        return in_array($milestone, [Milestone::Paid, Milestone::Received], true) && $order->cancelled_at !== null;
    }

    /**
     * The milestone, its copy on `orders`, and the event to dispatch once the caller has its
     * snapshot — or the first mark, when it is there already.
     *
     * «Paid» counts `$amount` into `paid_total`, or what the order still owes when no amount is
     * given (§36.2); every milestone writes the balance again — a cancellation takes what it owes
     * to 0.
     *
     * @return array{MilestoneMark, (Closure(OrderData): object)|null}
     */
    private function setMilestone(Order $locked, Milestone $milestone, string $source, ?int $adminId, CarbonImmutable $at, ?string $reference, ?string $note, ?int $amount = null): array
    {
        $first = OrderMilestone::query()->where('order_id', $locked->id)->where('milestone', $milestone->value)->first();

        if ($first !== null) {
            return [new MilestoneMark($milestone, MilestoneMark::ALREADY_MARKED, CarbonImmutable::instance($first->at), $first->source, $first->admin_id, $first->reference, $first->note), null];
        }

        // Given back whole, nothing is left to cancel — and «cancelled» would say otherwise.
        if ($milestone === Milestone::Cancelled && $locked->refunded_at !== null) {
            return [new MilestoneMark($milestone, MilestoneMark::REFUNDED, CarbonImmutable::instance($locked->refunded_at), $source, $adminId), null];
        }

        OrderMilestone::query()->insert([
            'order_id' => $locked->id,
            'milestone' => $milestone->value,
            'at' => $at,
            'source' => self::cut($source, 64),
            'admin_id' => $adminId,
            'reference' => $reference,
            'note' => $note,
            'created_at' => CarbonImmutable::now(),
        ]);

        $wasPaid = $locked->paid_at !== null;
        $locked->setAttribute($milestone->column(), $at);

        if ($milestone === Milestone::Paid) {
            // The row «awaiting_payment» of order_milestones stays; the flags of the list go.
            $locked->awaiting_payment = false;
            $locked->payment_failed_at = null;
            $locked->paid_total += $amount ?? max(0, Balance::stored($locked));
        }

        $returned = $milestone === Milestone::Cancelled ? $this->returnTenders($locked) : [];
        $locked->balance = Balance::stored($locked);
        $locked->save();

        return [
            new MilestoneMark($milestone, MilestoneMark::MARKED, $at, $source, $adminId, $reference, $note),
            static fn (OrderData $data): object => match ($milestone) {
                Milestone::Paid => new OrderPaid($data, $reference, $source, $adminId, $at),
                Milestone::Received => new OrderReceived($data, $reference, $source, $adminId, $at),
                default => new OrderCancelled($data, $note, $wasPaid, $returned, $source, $adminId, $at),
            },
        ];
    }

    /**
     * A cancellation gives the tenders back (§9.1): what was left of each is marked returned in
     * the same transaction and goes to the event — the wallet returns exactly that, and a refund
     * cannot return it a second time.
     *
     * @return list<TenderData> `amount` — what goes back now
     */
    private function returnTenders(Order $order): array
    {
        $left = $this->tendersLeft($order);

        if ($left !== []) {
            OrderTender::query()->whereKey(array_map(static fn (TenderData $tender): int => $tender->id, $left))->update(['refunded_amount' => $this->db->raw('amount')]);
        }

        return $left;
    }

    /**
     * What is left of each tender to give back, as a cancellation would return it.
     *
     * @return list<TenderData> `amount` — what is left; `refundedAmount` — the whole of it
     */
    private function tendersLeft(Order $order): array
    {
        $left = OrderTender::query()->where('order_id', $order->id)->whereColumn('refunded_amount', '<', 'amount')->orderBy('id')->get();

        return array_values($left->map(static fn (OrderTender $tender): TenderData => new TenderData(
            $tender->id,
            $tender->source,
            $tender->label,
            $tender->amount - $tender->refunded_amount,
            $tender->amount,
            $tender->meta ?? [],
        ))->all());
    }

    /** The cancellation, or on a dry run what it would be, read from the order as it is. */
    private function cancelling(Order $order, string $source, ?int $adminId, ?string $reason, bool $dryRun): MilestoneMark
    {
        $at = CarbonImmutable::now();
        $wasPaid = $order->paid_at !== null;
        // What came in less what went back — the balance a cancellation leaves (§36.1).
        $money = $wasPaid ? max(0, $order->paid_total - $order->refunded_total) : 0;
        $tenders = $this->tendersLeft($order);

        $move = null;

        if ($dryRun) {
            $first = OrderMilestone::query()->where('order_id', $order->id)->where('milestone', Milestone::Cancelled->value)->first();

            $mark = match (true) {
                $first !== null => new MilestoneMark(Milestone::Cancelled, MilestoneMark::ALREADY_MARKED, CarbonImmutable::instance($first->at), $first->source, $first->admin_id, $first->reference, $first->note),
                $order->refunded_at !== null => new MilestoneMark(Milestone::Cancelled, MilestoneMark::REFUNDED, CarbonImmutable::instance($order->refunded_at), $source, $adminId),
                default => new MilestoneMark(Milestone::Cancelled, MilestoneMark::MARKED, $at, $source, $adminId, null, $reason),
            };
            $move = $mark->marked() ? $this->cancelMove($order, true) : null;
        } else {
            [$mark, $event] = $this->setMilestone($order, Milestone::Cancelled, $source, $adminId, $at, null, $reason);

            if ($event !== null) {
                $move = $this->cancelMove($order, false);

                if ($move !== null) {
                    $order->status_id = $move->to->id;
                    $order->save();

                    StatusChange::query()->insert([
                        'order_id' => $order->id,
                        'kind' => StatusChange::STATUS,
                        'from_status_id' => $move->from->id,
                        'to_status_id' => $move->to->id,
                        'from_public_id' => $move->publicFrom->id,
                        'to_public_id' => $move->publicTo->id,
                        'source' => (string) self::cut($source, 64),
                        'admin_id' => $adminId,
                        'note' => $reason,
                        'created_at' => $at,
                    ]);
                }

                // The order of §7.2, as a move into that status has it: the status, the public
                // one, the milestone — one snapshot, after everything.
                $data = $this->snapshot($order);

                if ($move !== null) {
                    $this->events->dispatch(new OrderStatusChanged($data, $move->from, $move->to, $move->publicChanged, $reason, $source, $adminId, $at));

                    if ($move->publicChanged) {
                        $this->events->dispatch(new OrderPublicStatusChanged($data, $move->publicFrom, $move->publicTo, $source, $adminId, $at));
                    }
                }

                $this->events->dispatch($event($data));
            }
        }

        // Silence gives nothing back, and moves nothing.
        return $mark->cancelling($wasPaid, $money, $mark->marked() ? $tenders : [], $dryRun, $move);
    }

    /**
     * Where a cancellation takes the order (decision 88): the first active status that cancels,
     * unless the order stands in one already — a status deleted since, too — or the site has none.
     * The milestone is the cancellation's own, under its own source, so the move carries none.
     */
    private function cancelMove(Order $order, bool $dryRun): ?StatusMove
    {
        $current = OrderStatus::withTrashed()->find($order->status_id);

        if ($current?->sets_milestone === Milestone::Cancelled) {
            return null;
        }

        $to = $this->statuses->cancelling();

        if ($to === null) {
            return null;
        }

        $move = $this->plan($order, $to, $dryRun);

        return $move->moved && $move->refusal === null ? $move->without() : null;
    }

    /** What a refund would record, checked against what is left — the dry run's answer and the refund's plan. */
    private function refundPlan(Order $order, RefundRequest $request, bool $dryRun): RefundResult
    {
        $moneyLeft = Balance::refundable($order);
        $tendersLeft = [];
        $itemsLeft = [];

        foreach (OrderTender::query()->where('order_id', $order->id)->orderBy('id')->get(['id', 'amount', 'refunded_amount']) as $tender) {
            $tendersLeft[$tender->id] = $tender->amount - $tender->refunded_amount;
        }

        foreach (OrderItem::query()->where('order_id', $order->id)->orderBy('position')->orderBy('id')->get(['id', 'qty', 'refunded_qty']) as $item) {
            $itemsLeft[$item->id] = $item->qty - $item->refunded_qty;
        }

        $tenders = array_filter($request->tenders, static fn (int $amount): bool => $amount !== 0);
        $items = array_filter($request->items, static fn (int $qty): bool => $qty !== 0);
        [$refusal, $field] = [null, null];

        if ($request->amount < 0 || $request->amount > $moneyLeft) {
            [$refusal, $field] = [RefundResult::AMOUNT, 'amount'];
        }

        foreach ($tenders as $id => $amount) {
            if ($refusal === null && ($amount < 0 || $amount > ($tendersLeft[$id] ?? 0))) {
                [$refusal, $field] = [RefundResult::TENDER, "tenders.{$id}"];
            }
        }

        foreach ($items as $id => $qty) {
            if ($refusal === null && ($qty < 0 || $qty > ($itemsLeft[$id] ?? 0))) {
                [$refusal, $field] = [RefundResult::QTY, "items.{$id}"];
            }
        }

        if ($refusal === null && $request->amount === 0 && $tenders === [] && $items === []) {
            $refusal = RefundResult::EMPTY;
        }

        // Full when it gives back money or a tender and after it the money and every tender are
        // back — a cancellation gave its tenders back already. The units do not count: money back
        // without the goods is a full refund too, and units alone after it complete nothing; an
        // order paid by tenders alone and cancelled has nothing for a refund to give back (§9.2).
        $full = $refusal === null
            && ($request->amount > 0 || $tenders !== [])
            && $request->amount === $moneyLeft
            && array_all($tendersLeft, static fn (int $left, int $id): bool => $left === ($tenders[$id] ?? 0));

        return new RefundResult(
            $request->amount,
            $tenders,
            $items,
            $moneyLeft,
            $tendersLeft,
            $itemsLeft,
            $full,
            $full && $order->refunded_at === null ? Milestone::Refunded : null,
            $refusal,
            $field,
            null,
            $dryRun,
        );
    }

    /** What a reversal would take off, and what is left after it — the dry run's answer and the reversal's plan. */
    private function reversalPlan(Order $order, int $refundId, bool $dryRun): RefundReversal
    {
        $refund = OrderRefund::query()->where('order_id', $order->id)->whereKey($refundId)->first();

        if (! $refund instanceof OrderRefund) {
            return RefundReversal::refused($refundId, RefundReversal::FOREIGN, $dryRun);
        }

        if ($refund->reversed()) {
            return RefundReversal::refused($refundId, RefundReversal::REVERSED, $dryRun);
        }

        $tenders = [];
        $items = [];

        foreach ($refund->tenders as $one) {
            $tenders[(int) $one['tender_id']] = ($tenders[(int) $one['tender_id']] ?? 0) + (int) $one['amount'];
        }

        foreach ($refund->items as $one) {
            $items[(int) $one['item_id']] = ($items[(int) $one['item_id']] ?? 0) + (int) $one['qty'];
        }

        $tendersLeft = [];
        $itemsLeft = [];

        foreach (OrderTender::query()->where('order_id', $order->id)->orderBy('id')->get(['id', 'amount', 'refunded_amount']) as $tender) {
            $tendersLeft[$tender->id] = $tender->amount - $tender->refunded_amount + ($tenders[$tender->id] ?? 0);
        }

        foreach (OrderItem::query()->where('order_id', $order->id)->orderBy('position')->orderBy('id')->get(['id', 'qty', 'refunded_qty']) as $item) {
            $itemsLeft[$item->id] = $item->qty - $item->refunded_qty + ($items[$item->id] ?? 0);
        }

        $refundedTotal = max(0, $order->refunded_total - $refund->amount);
        // Given back whole after it as well — a record of units alone — keeps the milestone (§9.2).
        $ceiling = max($order->payable, $order->paid_total);
        $whole = $refundedTotal >= $ceiling && array_all($tendersLeft, static fn (int $left): bool => $left === 0);

        return new RefundReversal(
            $refund->id,
            $refund->amount,
            $tenders,
            $items,
            max(0, $ceiling - $refundedTotal),
            $tendersLeft,
            $itemsLeft,
            $refundedTotal,
            $order->refunded_at !== null && ! $whole ? Milestone::Refunded : null,
            null,
            $dryRun,
        );
    }

    /** What a move would do, read from the order as it is — the dry run's answer and the move's plan. */
    private function plan(Order $order, OrderStatus $to, bool $dryRun): StatusMove
    {
        $statuses = OrderStatus::withTrashed()->with('publicStatus')->whereKey(array_unique([$order->status_id, $to->id]))->get()->keyBy('id');
        $from = $statuses->get($order->status_id);
        $target = $statuses->get($to->id) ?? throw (new ModelNotFoundException)->setModel(OrderStatus::class, [$to->id]);
        $from ??= $target;

        $moved = $from->id !== $target->id;
        $publicChanged = $moved && $from->public_status_id !== $target->public_status_id;
        $milestone = null;
        $refusal = null;

        if ($moved) {
            $sets = $target->sets_milestone;

            if ($target->trashed()) {
                $refusal = StatusMove::DELETED;
            } elseif (! $target->is_active) {
                $refusal = StatusMove::INACTIVE;
            } elseif ($sets !== null && $order->getAttribute($sets->column()) === null) {
                if ($this->forbidden($order, $sets)) {
                    $refusal = StatusMove::CANCELLED;
                    $milestone = $sets;
                } elseif ($sets !== Milestone::Cancelled || $order->refunded_at === null) {
                    $milestone = $sets;
                }
            }
        }

        // The channels of a public status that is deleted do not fire (§5.6).
        $public = $target->publicStatus;
        $notifications = $publicChanged && ! $public->trashed()
            ? array_values(array_map(intval(...), $public->options['notify_channels'] ?? []))
            : [];

        return new StatusMove(
            StatusData::of($from, $order->locale),
            StatusData::of($target, $order->locale),
            PublicStatusData::of($from->publicStatus, $order->locale),
            PublicStatusData::of($public, $order->locale),
            $publicChanged,
            $moved,
            $milestone,
            $notifications,
            $refusal,
            $dryRun,
        );
    }

    /**
     * A move that would cancel the order wants its reason under `note` from the panel and an
     * agent, as `cancel()` does under `reason`; a move refused, or one that sets nothing, does not.
     *
     * @throws ValidationException
     */
    private function reasonOf(StatusMove $move, string $source, ?string $note): void
    {
        if ($move->refusal !== null || $move->milestone !== Milestone::Cancelled || ! in_array($source, ['panel', 'mcp'], true)) {
            return;
        }

        $this->validation->make(['note' => $note], ['note' => ['required', 'string']], [
            'note.required' => (string) __('webx-orders::panel.cancel-reason-required'),
        ])->validate();
    }

    /**
     * What an edit would do, read from the order as it is (§37): the refusal, or the lines and the
     * money after it — the dry run's answer and the edit's plan. The cart of the lines added is
     * made here; the caller's transaction takes it back or the edit clears it.
     *
     * @throws ValidationException|CartNotOrderable a line added
     */
    private function itemsPlan(Order $order, ItemsEdit $edit, bool $dryRun): ItemsPlan
    {
        $before = self::totals($order);
        $refused = static fn (string $refusal, ?string $field = null): ItemsPlan => new ItemsPlan(
            new ItemsEditResult([], [], $before, $before, Balance::of($order), [], $refusal, $field, null, $dryRun),
        );

        if ($edit->revision !== $order->items_revision) {
            return $refused(ItemsEditResult::STALE, 'revision');
        }

        // Received goods change by a return and a new order; a cancelled or refunded order has
        // nothing to edit, and an edit would give it a debt back (§37.2).
        if ($order->received_at !== null || $order->cancelled_at !== null || $order->refunded_at !== null) {
            return $refused(ItemsEditResult::STATE);
        }

        if ($edit->isEmpty()) {
            return $refused(ItemsEditResult::NOTHING);
        }

        $items = OrderItem::query()->where('order_id', $order->id)->orderBy('position')->orderBy('id')->get();
        $adjustmentRows = OrderAdjustment::query()->where('order_id', $order->id)->orderBy('id')->get();
        $children = [];
        $lineAdjustments = [];

        // A bundle: its children are its parts; a rule's gift under a line is not one of them.
        $bundled = [];

        foreach ($items as $item) {
            if ($item->parent_id !== null) {
                $children[$item->parent_id][] = $item;
                $bundled[$item->parent_id] = ($bundled[$item->parent_id] ?? false) || $item->source === null;
            }
        }

        foreach ($adjustmentRows as $adjustment) {
            if ($adjustment->item_id !== null) {
                $lineAdjustments[$adjustment->item_id][] = $adjustment;
            }
        }

        // The qty each top-level line comes to, and the field that asked for it.
        $targets = [];
        $fields = [];
        $out = [];

        foreach ($edit->qty as $id => $qty) {
            $targets[(int) $id] = max(0, (int) $qty);
            $fields[(int) $id] = "qty.{$id}";
        }

        foreach (array_values($edit->remove) as $n => $id) {
            $targets[(int) $id] = 0;
            $fields[(int) $id] = "remove.{$n}";
        }

        $byId = $items->keyBy('id');

        foreach ($targets as $id => $qty) {
            $item = $byId->get($id);

            if (! $item instanceof OrderItem || $item->removed_at !== null) {
                return $refused(ItemsEditResult::FOREIGN, $fields[$id]);
            }

            if ($item->parent_id !== null) {
                return $refused(ItemsEditResult::CHILD, $fields[$id]);
            }

            // Taken out, a line keeps the units refunded of it: the refunds point at them (§37.4).
            if ($qty === 0) {
                $out[$id] = true;
                $targets[$id] = $item->refunded_qty;

                continue;
            }

            if ($item->source !== null && $qty !== $item->qty) {
                return $refused(ItemsEditResult::GENERATED, $fields[$id]);
            }

            if ($qty < $item->refunded_qty) {
                return $refused(ItemsEditResult::BELOW_REFUNDED, $fields[$id]);
            }
        }

        $lines = [];
        $warnings = [];
        $cart = null;

        if ($edit->add !== []) {
            [$cart, $state, $index] = $this->cartOfEdit($order, $edit->add);

            // The cart's stored lines with their bundles; a rule's gift for the new lines is not
            // taken, nor any discount of a rule (variant A, §37.3).
            foreach ($state->lines as $line) {
                if (! $line->generated() && $line->parentId === null) {
                    $lines[(string) $line->key] = ['line' => $line, 'children' => []];
                }
            }

            foreach ($state->lines as $line) {
                if (! $line->generated() && $line->parentId !== null && isset($lines[(string) $line->parentId])) {
                    $lines[(string) $line->parentId]['children'][] = $line;
                }
            }

            foreach ($state->warnings as $warning) {
                if ($warning->line !== null && isset($index[(string) $warning->line])) {
                    $warnings[] = ['code' => $warning->code, 'line' => 'add.'.$index[(string) $warning->line], 'message' => $warning->message];
                }
            }

            // The same thing again at the same price is more of the line it is (§37.4); at another
            // price it is a line of its own — the units ordered before keep theirs.
            foreach ($lines as $key => $new) {
                $line = $new['line'];
                $same = $new['children'] === [] ? $items->first(static fn (OrderItem $item): bool => $item->parent_id === null
                    && $item->removed_at === null
                    && $item->source === null
                    && ! isset($out[$item->id])
                    && ! ($bundled[$item->id] ?? false)
                    && $item->type === $line->type
                    && $item->ref_id === $line->refId
                    && $item->price === $line->price
                    && ($item->options ?? []) == ($line->options ?? [])) : null;

                if ($same instanceof OrderItem) {
                    $targets[$same->id] = ($targets[$same->id] ?? $same->qty) + $line->effectiveQty;
                    unset($lines[$key]);
                }
            }
        }

        $changes = [];
        $adjustments = [];
        $rows = [];
        $amounts = [];
        $subtotal = $order->subtotal;
        $adjustmentsTotal = $order->adjustments_total;

        // A line's discount follows its qty: round(amount × new / old); taken out whole, it is 0.
        $recount = static function (OrderItem $item, int $to, bool $removed) use ($lineAdjustments, &$rows, &$amounts, &$adjustments, &$subtotal, &$adjustmentsTotal): int {
            $sum = 0;

            foreach ($lineAdjustments[$item->id] ?? [] as $adjustment) {
                $amount = $item->qty > 0 ? (int) round($adjustment->amount * $to / $item->qty) : 0;
                $sum += $amount;

                if ($amount !== $adjustment->amount) {
                    $amounts[$adjustment->id] = $amount;
                    $adjustments[] = ['adjustment_id' => $adjustment->id, 'label' => $adjustment->label, 'amount_from' => $adjustment->amount, 'amount_to' => $amount];
                    $adjustmentsTotal += $amount - $adjustment->amount;
                }
            }

            $total = $item->price * $to + $sum;
            $subtotal += $item->price * ($to - $item->qty);
            $rows[$item->id] = ['qty' => $to, 'adjustments_total' => $sum, 'total' => $total, 'removed' => $removed];

            return $total;
        };

        foreach ($items as $item) {
            if ($item->parent_id !== null || ! array_key_exists($item->id, $targets)) {
                continue;
            }

            $to = $targets[$item->id];
            $removed = isset($out[$item->id]);

            if ($to === $item->qty && ! $removed) {
                continue;
            }

            $total = $recount($item, $to, $removed);

            // A bundle changes whole: a child's qty is absolute, so it follows its parent's (§37.4).
            foreach ($children[$item->id] ?? [] as $child) {
                // A gift is what the rule gave at placing: it goes with its line and is not counted again.
                $childTo = match (true) {
                    $removed => $child->refunded_qty,
                    $child->source !== null => $child->qty,
                    default => (int) round($child->qty * $to / $item->qty),
                };

                if ($childTo < $child->refunded_qty) {
                    return $refused(ItemsEditResult::BELOW_REFUNDED, $fields[$item->id] ?? "qty.{$item->id}");
                }

                $recount($child, $childTo, $removed);
            }

            $changes[] = [
                'op' => $removed ? 'remove' : 'qty',
                'item_id' => $item->id,
                'name' => (string) ($item->name[$order->locale] ?? reset($item->name) ?: ''),
                'options_label' => $item->options_label,
                'price' => $item->price,
                'qty_from' => $item->qty,
                'qty_to' => $to,
                'total_from' => $item->total,
                'total_to' => $total,
            ];
        }

        foreach ($lines as $new) {
            $line = $new['line'];
            $price = $line->price ?? 0;

            foreach ([$line, ...$new['children']] as $one) {
                $subtotal += $one->amount();
            }

            $changes[] = [
                'op' => 'add',
                'item_id' => null,
                'name' => $line->name,
                'options_label' => $line->line?->optionsLabel,
                'price' => $price,
                'qty_from' => 0,
                'qty_to' => $line->effectiveQty,
                'total_from' => 0,
                'total_to' => $price * $line->effectiveQty,
            ];
        }

        if ($changes === []) {
            return $refused(ItemsEditResult::NOTHING);
        }

        $left = $lines !== [] || $items->contains(static fn (OrderItem $item): bool => $item->parent_id === null
            && $item->removed_at === null
            && ! ($rows[$item->id]['removed'] ?? false)
            && ($rows[$item->id]['qty'] ?? $item->qty) > $item->refunded_qty);

        if (! $left) {
            return $refused(ItemsEditResult::EMPTY);
        }

        $after = clone $order;
        $after->subtotal = $subtotal;
        $after->adjustments_total = $adjustmentsTotal;
        $after->total = max(0, $subtotal + $adjustmentsTotal);
        $after->payable = max(0, $after->total - $order->tenders_total);
        $after->balance = Balance::stored($after);
        $balance = Balance::of($after);

        $warnings = [...$this->editWarnings($order, $after, $balance, $items, $rows, $lines, $adjustmentRows), ...$warnings];

        return new ItemsPlan(
            new ItemsEditResult($changes, $adjustments, $before, self::totals($after), $balance, $warnings, null, null, null, $dryRun),
            $rows,
            $amounts,
            array_values($lines),
            $cart,
            ((int) $items->max('position')) + 1,
        );
    }

    /**
     * What the manager should know before saving (§37.7): not refusals, lines of the dialog.
     *
     * @param  EloquentCollection<int, OrderItem>  $items
     * @param  array<int, array{qty: int, adjustments_total: int, total: int, removed: bool}>  $rows
     * @param  array<string, array{line: ResolvedLine, children: list<ResolvedLine>}>  $lines
     * @param  EloquentCollection<int, OrderAdjustment>  $adjustments
     * @return list<array<string, mixed>>
     */
    private function editWarnings(Order $order, Order $after, Balance $balance, EloquentCollection $items, array $rows, array $lines, EloquentCollection $adjustments): array
    {
        $warnings = [];

        if ($order->paid_at !== null) {
            $warnings[] = ['code' => ItemsEditResult::WARN_PAID, 'paid' => $order->paid_total, 'payable' => $after->payable, 'due' => $balance->due, 'over' => $balance->over];
        }

        // A discount of the whole order — a coupon, a rule on the cart — is not counted again.
        if ($after->subtotal !== $order->subtotal) {
            foreach ($adjustments as $adjustment) {
                if ($adjustment->item_id === null && $adjustment->amount < 0) {
                    $warnings[] = ['code' => ItemsEditResult::WARN_ADJUSTMENT_KEPT, 'adjustment_id' => $adjustment->id, 'label' => $adjustment->label, 'amount' => $adjustment->amount, 'subtotal' => $order->subtotal];
                }
            }
        }

        foreach (OrderBenefit::query()->where('order_id', $order->id)->orderBy('id')->get() as $benefit) {
            $warnings[] = ['code' => ItemsEditResult::WARN_BENEFIT_KEPT, 'key' => $benefit->key, 'label' => $benefit->label];
        }

        if ($order->delivery !== null) {
            $shipped = $items->contains(static fn (OrderItem $item): bool => $item->shippable && ($rows[$item->id]['qty'] ?? $item->qty) > 0)
                || array_any($lines, static fn (array $new): bool => array_any([$new['line'], ...$new['children']], static fn (ResolvedLine $line): bool => $line->line->shippable ?? true));

            if (! $shipped) {
                $warnings[] = ['code' => ItemsEditResult::WARN_NOTHING_SHIPPABLE];
            }
        }

        if ($balance->tendersOver > 0) {
            $warnings[] = ['code' => ItemsEditResult::WARN_TENDERS_OVER, 'amount' => $balance->tendersOver];
        }

        return $warnings;
    }

    /**
     * The lines added, in a cart of their own (§29.2): a row of `carts` nobody is given the token
     * of, counted for an order — the provider's price now, its options, limits and availability.
     * The cart's refusals point at the edit's field, `add.N.*`.
     *
     * @param  list<array<string, mixed>>  $add
     * @return array{Cart, CartState, array<int|string, int>} the cart, its count, and the index in `add` by the cart line's id
     *
     * @throws ValidationException|CartNotOrderable
     */
    private function cartOfEdit(Order $order, array $add): array
    {
        try {
            $added = $this->carts->add(null, array_values($add), $order->locale);
        } catch (ValidationException $refused) {
            $errors = [];

            foreach ($refused->errors() as $key => $messages) {
                $errors[str_starts_with($key, 'items') ? 'add'.substr($key, 5) : $key] = $messages;
            }

            throw ValidationException::withMessages($errors);
        }

        $state = $this->calculator->calculate($added->cart, $order->locale, Purpose::Order);
        $this->orderable($added->cart, $state);

        // The site's currency changed since the order was placed: its lines are another money.
        if ($state->currency !== $order->currency) {
            throw new CartNotOrderable(CartNotOrderable::CURRENCY, $state);
        }

        $index = [];

        foreach ($added->added as $n => $id) {
            $index[(string) $id] ??= $n;
        }

        return [$added->cart, $state, $index];
    }

    /** The edit written: the lines, the discounts, the money, the edit's row, the history, the events. */
    private function writeEdit(Order $locked, ItemsEdit $edit, ItemsPlan $plan, ?string $reason, ?string $message, ?int $adminId): ItemsEditResult
    {
        $at = CarbonImmutable::now();
        $number = $locked->items_revision + 1;
        $result = $plan->result;

        foreach ($plan->items as $id => $row) {
            OrderItem::query()->whereKey($id)->update([
                'qty' => $row['qty'],
                'adjustments_total' => $row['adjustments_total'],
                'total' => $row['total'],
                ...($row['removed'] ? ['removed_at' => $at] : []),
            ]);
        }

        foreach ($plan->amounts as $id => $amount) {
            OrderAdjustment::query()->whereKey($id)->update(['amount' => $amount]);
        }

        $ids = $this->writeAddedItems($locked, $plan->lines, $number, $plan->position);
        $added = array_map(static fn (array $new): ?int => $ids[(string) $new['line']->key] ?? null, $plan->lines);
        $changes = array_map(static function (array $change) use (&$added): array {
            if ($change['op'] === 'add') {
                $change['item_id'] = array_shift($added);
            }

            return $change;
        }, $result->changes);

        $locked->fill([
            'subtotal' => $result->after['subtotal'],
            'adjustments_total' => $result->after['adjustments_total'],
            'total' => $result->after['total'],
            'payable' => $result->after['payable'],
            'items_revision' => $number,
            'items_changed_at' => $at,
        ]);
        $locked->balance = Balance::stored($locked);
        $locked->save();

        $revision = OrderRevision::query()->create([
            'order_id' => $locked->id,
            'number' => $number,
            'changes' => $changes,
            'adjustments' => $result->adjustments,
            'before' => $result->before,
            'after' => self::totals($locked),
            'reason' => $reason,
            'message' => $message,
            'source' => (string) self::cut($edit->source, 64),
            'admin_id' => $adminId,
            'created_at' => $at,
        ]);

        $this->line($locked, StatusChange::ITEMS, $edit->source, $adminId, $at, self::cut("revision#{$number}".($reason !== null ? ": {$reason}" : ''), 1000));
        $money = Container::getInstance()->make(Money::class);
        $this->journal->record($locked, 'updated', [
            'items_revision' => [(string) ($number - 1), (string) $number],
            'total' => [$money->format($result->before['total'], $locked->currency), $money->format($locked->total, $locked->currency)],
        ]);

        // The cart of the lines added goes now; a refusal below brings it back with the rest.
        if ($plan->cart !== null) {
            $this->carts->clear($plan->cart);
        }

        $data = RevisionData::of($revision);
        $locked->unsetRelations();

        // What must happen with the edit or not at all; a refusal rolls all of it back.
        $this->events->dispatch(new OrderItemsChanging($locked, $data));

        $balance = Balance::of($locked);
        $this->events->dispatch(new OrderItemsChanged($this->snapshot($locked), $data, $balance, $edit->notify, $edit->source, $adminId, $at));

        return new ItemsEditResult($changes, $result->adjustments, $result->before, self::totals($locked), $balance, $result->warnings, null, null, $number, false);
    }

    /**
     * The lines an edit adds, after the order's own, a query a level as at placing; `revision` says
     * which edit brought them.
     *
     * @param  list<array{line: ResolvedLine, children: list<ResolvedLine>}>  $lines
     * @return array<string, int> the order line's id by the cart line's key
     */
    private function writeAddedItems(Order $order, array $lines, int $revision, int $position): array
    {
        $ids = [];
        $keys = [];

        foreach ([false, true] as $children) {
            $rows = [];

            foreach ($lines as $new) {
                foreach ($children ? $new['children'] : [$new['line']] as $line) {
                    $keys[$position] = (string) $line->key;
                    $rows[] = [
                        ...$this->itemRow($order, $line, $position++, $children ? $ids[(string) $line->parentId] ?? null : null, 0),
                        'revision' => $revision,
                    ];
                }
            }

            if ($rows === []) {
                continue;
            }

            OrderItem::query()->insert($rows);

            foreach (OrderItem::query()->where('order_id', $order->id)->where('revision', $revision)->pluck('id', 'position') as $at => $id) {
                $ids[$keys[(int) $at]] = (int) $id;
            }
        }

        return $ids;
    }

    /**
     * The money of the order in one row, as an edit keeps it before and after (§38.1).
     *
     * @return array{subtotal: int, adjustments_total: int, total: int, tenders_total: int, payable: int, balance: int}
     */
    private static function totals(Order $order): array
    {
        return [
            'subtotal' => $order->subtotal,
            'adjustments_total' => $order->adjustments_total,
            'total' => $order->total,
            'tenders_total' => $order->tenders_total,
            'payable' => $order->payable,
            'balance' => $order->balance,
        ];
    }

    /** What a payment would record, and the balance before and after it — the dry run's answer and the payment's plan. */
    private function paymentPlan(Order $order, int $amount, ?string $reference, ?string $note, CarbonImmutable $at, bool $dryRun): PaymentRecord
    {
        $refusal = match (true) {
            $order->cancelled_at !== null => PaymentRecord::CANCELLED,
            $order->paid_at === null => PaymentRecord::NOT_PAID,
            default => null,
        };

        $after = clone $order;
        $after->paid_total += $refusal === null ? $amount : 0;

        return new PaymentRecord($amount, $reference, $note, $at, Balance::of($order), Balance::of($after), $refusal, $dryRun);
    }

    /**
     * The history line of a top-up: «1500», «1500 [bank-77]», «1500 [bank-77]: by card at the
     * desk» — the panel reads the money and the reference back from it.
     */
    public static function paymentNote(int $amount, ?string $reference, ?string $note): string
    {
        $line = (string) $amount
            .($reference !== null ? ' ['.str_replace(']', ')', $reference).']' : '')
            .($note !== null ? ': '.$note : '');

        return mb_substr($line, 0, 1000);
    }

    /** A line of the history that is not a move: a note, a failed payment, a block, details. */
    private function line(Order $order, string $kind, string $source, ?int $adminId, CarbonImmutable $at, ?string $note = null): void
    {
        StatusChange::query()->insert([
            'order_id' => $order->id,
            'kind' => $kind,
            'source' => (string) self::cut($source, 64),
            'admin_id' => $adminId,
            'note' => $note,
            'created_at' => $at,
        ]);
    }

    /** The order as an event carries it, read again after what was just written. */
    private function snapshot(Order $order): OrderData
    {
        $order->unsetRelations();

        return OrderData::of($order);
    }

    /**
     * The fields `updateDetails()` may change, as they are now.
     *
     * @return array{customer_first_name: string, customer_last_name: string|null, customer_phone: string|null, customer_email: string|null, recipient: array{first_name: string, last_name: string|null, phone: string|null}|null, comment: string|null, manager_note: string|null}
     */
    private function details(Order $order): array
    {
        return [
            'customer_first_name' => $order->customer_first_name,
            'customer_last_name' => $order->customer_last_name,
            'customer_phone' => $order->customer_phone,
            'customer_email' => $order->customer_email,
            'recipient' => $order->recipient,
            'comment' => $order->comment,
            'manager_note' => $order->manager_note,
        ];
    }

    /** A value as the journal shows it: the recipient as one line, not as json. */
    private static function shown(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = implode(', ', array_filter([trim(($value['first_name'] ?? '').' '.($value['last_name'] ?? '')), $value['phone'] ?? null], static fn (mixed $part): bool => is_string($part) && $part !== ''));
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Trimmed, cut to the column, and null when nothing is left. */
    private static function text(?string $value, int $length): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value !== null && $value !== '' ? mb_substr($value, 0, $length) : null;
    }

    private function byHash(string $hash): ?Order
    {
        return Order::query()->where('idempotency_key', $hash)->first();
    }

    /**
     * Who did it, when a person of the panel or their agent did (§5.3); the checkout, an
     * integration and a command are known by the source alone.
     */
    private function adminId(string $source): ?int
    {
        $guard = $this->config->get('webx-auth.guard');

        if (! in_array($source, ['panel', 'mcp'], true) || ! is_string($guard) || $guard === '' || $this->config->get("auth.guards.{$guard}") === null) {
            return null;
        }

        $id = $this->auth->guard($guard)->id();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    private static function hash(string $key): string
    {
        return hash('sha256', $key);
    }

    private static function cut(?string $value, int $length): ?string
    {
        return $value !== null ? mb_substr($value, 0, $length) : null;
    }
}
