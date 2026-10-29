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
use WebxUi\Orders\Data\StatusData;
use WebxUi\Orders\Data\TenderData;
use WebxUi\Orders\Events\OrderBlockUpdated;
use WebxUi\Orders\Events\OrderCancelled;
use WebxUi\Orders\Events\OrderDetailsChanged;
use WebxUi\Orders\Events\OrderPaid;
use WebxUi\Orders\Events\OrderPaymentFailed;
use WebxUi\Orders\Events\OrderPlaced;
use WebxUi\Orders\Events\OrderPlacing;
use WebxUi\Orders\Events\OrderPublicStatusChanged;
use WebxUi\Orders\Events\OrderReceived;
use WebxUi\Orders\Events\OrderRefunded;
use WebxUi\Orders\Events\OrderStatusChanged;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderAdjustment;
use WebxUi\Orders\Models\OrderBenefit;
use WebxUi\Orders\Models\OrderItem;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Models\OrderRefund;
use WebxUi\Orders\Models\OrderStatus;
use WebxUi\Orders\Models\OrderTender;
use WebxUi\Orders\Models\StatusChange;

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
    ): MilestoneMark {
        if (! $milestone->settableByStatus()) {
            throw new MilestoneRefused($milestone);
        }

        $adminId = $this->adminId($source);
        $at = $at !== null ? CarbonImmutable::instance($at) : CarbonImmutable::now();

        return $this->locked($order, function (Order $locked) use ($milestone, $source, $at, $reference, $note, $adminId): MilestoneMark {
            if ($this->forbidden($locked, $milestone)) {
                throw new OrderCancelledAlready("«{$milestone->value}»");
            }

            [$mark, $event] = $this->setMilestone($locked, $milestone, $source, $adminId, $at, self::cut($reference, 190), self::cut($note, 500));

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
            $this->validation->make(['reason' => $reason], ['reason' => ['required', 'string']])->validate();
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
     * bought is a refusal the checkout shows the buyer.
     *
     * @throws CartNotOrderable
     */
    private function orderable(Cart $cart, CartState $state): void
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

                $rows[] = $this->itemRow($order, $state, $line, $position, $children ? $ids[(string) $line->parentId] ?? null : null);
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
    private function itemRow(Order $order, CartState $state, ResolvedLine $line, int $position, ?int $parentId): array
    {
        $item = $line->line;
        $price = $line->price ?? 0;
        $adjustments = array_sum(array_map(
            static fn (Adjustment $adjustment): int => $adjustment->amount,
            $state->lineAdjustments[(string) $line->key] ?? [],
        ));
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
     * a form requires is the checkout's. Answered like a form, keyed by field.
     *
     * @throws ValidationException
     */
    private function validate(Contact $customer, ?Contact $recipient, ?string $comment, ?string $managerNote = null): void
    {
        $phone = ['nullable', 'string', 'max:32', 'regex:/^\+?[0-9][0-9 ().\-]*$/'];
        $data = [
            'customer' => ['first_name' => $customer->firstName, 'last_name' => $customer->lastName, 'phone' => $customer->phone, 'email' => $customer->email],
            'comment' => $comment,
            'manager_note' => $managerNote,
        ];
        $rules = [
            'customer.first_name' => ['required', 'string', 'max:190'],
            'customer.last_name' => ['nullable', 'string', 'max:190'],
            'customer.phone' => [...$phone, 'required_without:customer.email'],
            'customer.email' => ['nullable', 'string', 'max:190', 'email:filter', 'required_without:customer.phone'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'manager_note' => ['nullable', 'string', 'max:5000'],
        ];

        if ($recipient !== null) {
            $data['recipient'] = ['first_name' => $recipient->firstName, 'last_name' => $recipient->lastName, 'phone' => $recipient->phone];
            $rules += [
                'recipient.first_name' => ['required', 'string', 'max:190'],
                'recipient.last_name' => ['nullable', 'string', 'max:190'],
                'recipient.phone' => $phone,
            ];
        }

        $this->validation->make($data, $rules)->validate();
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
     * @return array{MilestoneMark, (Closure(OrderData): object)|null}
     */
    private function setMilestone(Order $locked, Milestone $milestone, string $source, ?int $adminId, CarbonImmutable $at, ?string $reference, ?string $note): array
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
        }

        $returned = $milestone === Milestone::Cancelled ? $this->returnTenders($locked) : [];
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
        $money = $wasPaid ? max(0, $order->payable - $order->refunded_total) : 0;
        $tenders = $this->tendersLeft($order);

        if ($dryRun) {
            $first = OrderMilestone::query()->where('order_id', $order->id)->where('milestone', Milestone::Cancelled->value)->first();

            $mark = match (true) {
                $first !== null => new MilestoneMark(Milestone::Cancelled, MilestoneMark::ALREADY_MARKED, CarbonImmutable::instance($first->at), $first->source, $first->admin_id, $first->reference, $first->note),
                $order->refunded_at !== null => new MilestoneMark(Milestone::Cancelled, MilestoneMark::REFUNDED, CarbonImmutable::instance($order->refunded_at), $source, $adminId),
                default => new MilestoneMark(Milestone::Cancelled, MilestoneMark::MARKED, $at, $source, $adminId, null, $reason),
            };
        } else {
            [$mark, $event] = $this->setMilestone($order, Milestone::Cancelled, $source, $adminId, $at, null, $reason);

            if ($event !== null) {
                $this->events->dispatch($event($this->snapshot($order)));
            }
        }

        // Silence gives nothing back.
        return $mark->cancelling($wasPaid, $money, $mark->marked() ? $tenders : [], $dryRun);
    }

    /** What a refund would record, checked against what is left — the dry run's answer and the refund's plan. */
    private function refundPlan(Order $order, RefundRequest $request, bool $dryRun): RefundResult
    {
        $moneyLeft = max(0, $order->payable - $order->refunded_total);
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

        $this->validation->make(['note' => $note], ['note' => ['required', 'string']])->validate();
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
