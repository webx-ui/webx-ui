<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\ConnectionInterface;
use WebxUi\Orders\Blocks\BlockChange;
use WebxUi\Orders\Blocks\BlockChangeRefused;
use WebxUi\Orders\Blocks\BlockChangeResult;
use WebxUi\Orders\Data\OrderData;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\OrderCancelledAlready;
use WebxUi\Orders\Orders;
use WebxUi\Payments\Contracts\CapturesPayments;
use WebxUi\Payments\Exchange\ExchangeContext;
use WebxUi\Payments\Exchange\ExchangeDecision;
use WebxUi\Payments\Exchange\ExchangeEntry;
use WebxUi\Payments\Exchange\ExchangeJournal;
use WebxUi\Payments\Exchange\ExchangeKind;
use WebxUi\Payments\Exchange\ExchangeSecrets;
use WebxUi\Payments\Exchange\Outcome;
use WebxUi\Payments\Models\Payment;
use WebxUi\Payments\Online\Checks;
use WebxUi\Payments\Online\Closings;
use WebxUi\Payments\Online\GatewayCalls;
use WebxUi\Payments\Online\Holds;
use WebxUi\Payments\Online\OrderContext;
use WebxUi\Payments\Online\Settlement;
use WebxUi\Payments\Types\BankTransfer;
use WebxUi\Payments\Types\OnReceipt;

/**
 * The payments of orders (spec §8–§12): a new online attempt (`begin()`, §8.2) and a manager's
 * «Mark as paid» of an order paid by transfer or on receipt (§12.2), and what the gateway says of
 * an attempt (`settle()`, `check()`, §9.1), a hold taken or let go (`capture()`, `void()`, §9.5),
 * a problem put right by a manager (§9.4), another method for an unpaid order (`switchMethod()`,
 * §8.5) and the cash a carrier collects on delivery (`cashOnDelivery()`, §11.3).
 */
final readonly class Payments
{
    public function __construct(
        private Orders $orders,
        private ExchangeJournal $journal,
        private ConnectionInterface $db,
        private GatewayCalls $calls,
        private Settlement $settlement,
        private Checks $checks,
        private Holds $holds,
        private Closings $closings,
        private MethodTypes $types,
        private Repository $config,
        private Auth $auth,
    ) {}

    /**
     * The only door from «the gateway says» to the attempt and the order (§9.1): one transaction,
     * no network. The caller writes the result into its event of the journal.
     */
    public function settle(Payment $payment, GatewayStatus $status): SettleResult
    {
        return $this->settlement->settle($payment, $status);
    }

    /**
     * Asks the gateway and settles (§9.1): the return, a notification to poke, the page «we are
     * checking». Null — another request holds the attempt's lock: its row is the answer. A dry run
     * asks the gateway all the same and answers what would change, writing nothing of the attempt.
     *
     * @throws GatewayUnavailable
     */
    public function check(Payment $payment, string $source, ?string $trace = null, bool $complete = false, int $wait = 0, bool $dryRun = false): ?SettleResult
    {
        return $this->checks->check($payment, $source, $trace, $complete, $wait, $dryRun);
    }

    /**
     * Takes a held payment (§9.5): all of it (`null`), or a part when the type captures partially.
     * Paid — the order gets its milestone of what was taken; the gateway refused — the attempt
     * stays `authorized` with the problem `capture_failed`. A dry run says what would be taken
     * and asks nobody.
     *
     * @param  string  $source  'panel' | 'mcp' | 'api' | 'code'
     *
     * @throws HoldRefused
     * @throws GatewayUnavailable the gateway did not answer: the attempt is as it was
     */
    public function capture(Payment $payment, ?int $amount, string $source, bool $dryRun = false): SettleResult
    {
        return $this->holds->capture($payment, $amount, $source, $dryRun);
    }

    /**
     * Lets a hold go (§9.5): the attempt `cancelled`, the order «payment did not go through» when
     * it was its last attempt.
     *
     * @throws HoldRefused
     * @throws GatewayRefused
     * @throws GatewayUnavailable
     */
    public function void(Payment $payment, string $source, bool $dryRun = false): SettleResult
    {
        return $this->holds->void($payment, $source, $dryRun);
    }

    /**
     * «Problem resolved» (§9.4): the money was given back or the payment marked by hand. The
     * attempt keeps its status and its problem; `meta` gets when, who and the note, the order's
     * history a line. A hold's problem is resolved by a capture or a release, not here.
     *
     * @throws ProblemRefused
     */
    public function resolveProblem(Payment $payment, string $source, ?string $note = null, bool $dryRun = false): Payment
    {
        $payment->refresh();

        if ($payment->problem === null) {
            throw new ProblemRefused(ProblemRefused::NONE);
        }

        if (in_array($payment->problem, SettleResult::HOLD_PROBLEMS, true)) {
            throw new ProblemRefused(ProblemRefused::HOLD);
        }

        if ($dryRun) {
            return $payment;
        }

        $note = self::text($note, 500);
        $adminId = $this->adminId($source);

        $this->db->transaction(function () use ($payment, $source, $note, $adminId): void {
            /** @var Payment $row */
            $row = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $row->meta = [...($row->meta ?? []), 'resolved_at' => CarbonImmutable::now()->toIso8601String(), 'resolved_by' => $adminId, 'resolved_source' => $source, 'resolved_note' => $note];
            $row->save();
            $payment->setRawAttributes($row->getAttributes(), true);

            /** @var Order $order */
            $order = Order::query()->findOrFail($row->order_id);
            $this->orders->note($order, "The problem «{$row->problem}» of payment {$row->uuid} is resolved".($note !== null ? ": {$note}" : ''), $source);
        });

        return $payment;
    }

    /**
     * Another method for an unpaid order (§8.5): the buyer of an order «on receipt» pays online
     * now, a manager switches a transfer to a card. The order's block `payment` is replaced, the
     * unfinished attempts are closed (§9.4), the order awaits payment or stops awaiting it, and an
     * online method gets its attempt at once — the caller sends the buyer to the gateway.
     *
     * @return Payment|null the new attempt of an online method; null — an offline one
     *
     * @throws BeginRefused
     */
    public function switchMethod(Order $order, Method $method, string $source): ?Payment
    {
        $type = $this->types->has($method->type) ? $this->types->get($method->type) : throw new BeginRefused(BeginRefused::NOT_ONLINE);
        $order->refresh();
        $this->refuseClosed($order);

        if ($this->closings->close($order, $source)) {
            throw new BeginRefused(BeginRefused::PAID);
        }

        $context = OrderContext::of($order, $this->orders->balance($order)->due);

        try {
            $this->orders->changeBlock($order, new BlockChange('payment', $type->snapshot($method, $context)->toArray(), source: $source));
        } catch (BlockChangeRefused $refused) {
            if ($refused->result->refusal !== BlockChangeResult::NOTHING) {
                throw new BeginRefused(BeginRefused::CANCELLED);
            }
        }

        if ($type->awaitsPayment()) {
            $this->orders->awaitPayment($order, $source);
        } else {
            $this->orders->stopAwaitingPayment($order, $source);
        }

        return $type->kind() === Kind::Online ? $this->begin($order->refresh(), $method, $source) : null;
    }

    /**
     * The cash a carrier collects for the shop (§11.3), for the waybill: an order «on receipt»
     * delivered by a carrier, not picked up at the shop, with money still due; null otherwise.
     * Delivery calls it when this class exists.
     */
    public function cashOnDelivery(OrderData $order): ?int
    {
        $payment = $order->payment ?? [];
        $delivery = $order->delivery;

        if (($payment['type'] ?? null) !== OnReceipt::KEY || $delivery === null || ($delivery['carrier'] ?? 'store') === 'store') {
            return null;
        }

        $due = max(0, $order->balance);

        return $due > 0 ? $due : null;
    }

    /**
     * A new attempt for an order (§8.2, §8.5): a row `created` for what the order owes now
     * (decision 29) — the gateway is called on GET /pay/go/{uuid}, where a failure has a page of
     * its own. The earlier attempts are dealt with first (§9.4): the ones that never went to the
     * gateway are closed; `pending` ones are asked — one paid already refuses the new attempt —
     * and closed at the gateway where the type can. Money held refuses it too.
     *
     * @param  string  $source  'checkout' | 'pay-page' | 'panel' | 'mcp' | 'api'
     *
     * @throws BeginRefused
     */
    public function begin(Order $order, Method $method, string $source): Payment
    {
        $type = $this->calls->type($method->type) ?? throw new BeginRefused(BeginRefused::NOT_ONLINE);
        // An online type without its gateway is a satellite's mistake: loud here, not at the gateway.
        $this->calls->gateway($type);

        // The network goes before the transaction (decision 39).
        if ($this->closings->close($order, $source)) {
            throw new BeginRefused(BeginRefused::PAID);
        }

        return $this->db->transaction(function () use ($order, $method, $type, $source): Payment {
            $fresh = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->refuseClosed($fresh);

            $due = $this->orders->balance($fresh)->due;

            Payment::query()
                ->where('order_id', $fresh->id)
                ->where('status', PaymentStatus::Created->value)
                ->update(['status' => PaymentStatus::Cancelled->value, 'failure_reason' => 'replaced by a new attempt', 'updated_at' => CarbonImmutable::now()]);

            return Payment::query()->create([
                'order_id' => $fresh->id,
                'method_key' => $method->key,
                'type' => $type->key(),
                'amount' => $due,
                'currency' => $fresh->currency,
                'status' => PaymentStatus::Created,
                // A hold only where the type can capture it later (§9.5).
                'hold' => $type instanceof CapturesPayments && (bool) ($method->settings['hold'] ?? false),
                'source' => $source,
            ]);
        });
    }

    /**
     * The money is in (§12.2): an attempt `paid` for what the order owes, from the manager's
     * source, and the order's milestone «paid» with the document's number — in one transaction —
     * then an event `internal/manual` in the exchange journal, with no body: who and when.
     * The manager does not type an amount: no partial payment by transfer in v1 (decision 23).
     *
     * @param  string  $source  'panel' | 'mcp' | 'api'
     *
     * @throws MarkRefused the order is paid already, or cancelled
     */
    public function markPaid(
        Order $order,
        string $source,
        ?string $reference = null,
        ?CarbonInterface $paidAt = null,
        ?string $note = null,
    ): Payment {
        $paidAt = $paidAt !== null ? CarbonImmutable::instance($paidAt) : CarbonImmutable::now();
        $reference = self::text($reference, 190);

        $payment = $this->db->transaction(function () use ($order, $source, $reference, $paidAt, $note): Payment {
            $fresh = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($fresh->cancelled_at !== null) {
                throw new MarkRefused(MarkRefused::CANCELLED);
            }

            if ($fresh->paid_at !== null) {
                throw new MarkRefused(MarkRefused::PAID);
            }

            $due = $this->orders->balance($fresh)->due;

            try {
                $mark = $this->orders->mark($fresh, Milestone::Paid, $source, $paidAt, $reference, $note);
            } catch (OrderCancelledAlready) {
                throw new MarkRefused(MarkRefused::CANCELLED);
            }

            if ($mark->alreadyMarked()) {
                throw new MarkRefused(MarkRefused::PAID);
            }

            $block = is_array($fresh->payment) ? $fresh->payment : [];

            return Payment::query()->create([
                'order_id' => $fresh->id,
                'method_key' => is_string($block['method'] ?? null) ? $block['method'] : BankTransfer::METHOD,
                'type' => is_string($block['type'] ?? null) ? $block['type'] : BankTransfer::KEY,
                'amount' => $due,
                'currency' => $fresh->currency,
                'status' => PaymentStatus::Paid,
                'paid_amount' => $due,
                'paid_at' => $paidAt,
                'source' => $source,
                'admin_id' => $mark->adminId,
                'meta' => $reference !== null ? ['reference' => $reference] : null,
            ]);
        });

        $this->journal($payment, $source, $reference);

        return $payment;
    }

    /**
     * A cancelled order, one that owes nothing, one whose money is held: no new attempt, no other
     * method.
     *
     * @throws BeginRefused
     */
    private function refuseClosed(Order $order): void
    {
        if ($order->cancelled_at !== null) {
            throw new BeginRefused(BeginRefused::CANCELLED);
        }

        if ($this->orders->balance($order)->due <= 0) {
            throw new BeginRefused(BeginRefused::NOTHING_DUE);
        }

        if (Payment::query()->where('order_id', $order->id)->where('status', PaymentStatus::Authorized->value)->exists()) {
            throw new BeginRefused(BeginRefused::HELD);
        }
    }

    /** Who clicked: an administrator of the panel or the agent, as `Orders` takes it. */
    private function adminId(string $source): ?int
    {
        $guard = $this->config->get('webx-auth.guard');

        if (! in_array($source, ['panel', 'mcp'], true) || ! is_string($guard) || $guard === '' || $this->config->get("auth.guards.{$guard}") === null) {
            return null;
        }

        $id = $this->auth->guard($guard)->id();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    /** The manual mark in the journal (§20.1): no bodies, the decision only. */
    private function journal(Payment $payment, string $source, ?string $reference): void
    {
        $secrets = ExchangeSecrets::none();
        $id = $this->journal->record(new ExchangeEntry(
            kind: ExchangeKind::Manual,
            trace: ExchangeContext::newTrace(),
            methodKey: $payment->method_key,
            paymentId: $payment->id,
            orderId: $payment->order_id,
            source: $source,
            startedAt: CarbonImmutable::now(),
            durationMs: 0,
        ), $secrets);

        if ($id === null) {
            return;
        }

        $who = $payment->admin_id !== null ? "admin #{$payment->admin_id}" : $source;

        $this->journal->decide($id, new ExchangeDecision(
            outcome: Outcome::Applied,
            paymentId: $payment->id,
            orderId: $payment->order_id,
            statusBefore: null,
            statusAfter: PaymentStatus::Paid->value,
            message: "marked paid by {$who}".($reference !== null ? ", document {$reference}" : ''),
        ), $secrets);
    }

    private static function text(?string $text, int $max): ?string
    {
        $text = $text !== null ? trim($text) : null;

        return $text === null || $text === '' ? null : mb_substr($text, 0, $max);
    }
}
