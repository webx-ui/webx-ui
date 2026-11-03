<?php

declare(strict_types=1);

namespace WebxUi\Payments\Orders;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use WebxUi\Orders\Contracts\MarksPaidByHand;
use WebxUi\Orders\Milestone;
use WebxUi\Orders\MilestoneMark;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Models\OrderMilestone;
use WebxUi\Orders\Orders;
use WebxUi\Payments\Payments;

/**
 * «Mark as paid» of the order's card and of the agent's tool is the payments' own mark (spec
 * §12.2): one action — the attempt `paid`, the event `internal/manual` of the journal and the
 * milestone, whichever button a manager pressed. A paid or a cancelled order goes to
 * `Orders::mark()` as it is: its silence or its refusal is the answer.
 */
final readonly class PaymentsMarkPaid implements MarksPaidByHand
{
    public function __construct(
        private Payments $payments,
        private Orders $orders,
    ) {}

    public function markPaid(Order $order, string $source, ?string $reference = null, ?CarbonInterface $at = null, ?string $note = null): MilestoneMark
    {
        $order->refresh();

        if ($order->paid_at !== null || $order->cancelled_at !== null) {
            return $this->orders->mark($order, Milestone::Paid, $source, $at, $reference, $note);
        }

        $this->payments->markPaid($order, $source, $reference, $at, $note);
        $row = OrderMilestone::query()->where('order_id', $order->id)->where('milestone', Milestone::Paid->value)->firstOrFail();
        $order->refresh();

        return new MilestoneMark(Milestone::Paid, MilestoneMark::MARKED, CarbonImmutable::instance($row->at), $row->source, $row->admin_id, $row->reference, $row->note);
    }
}
