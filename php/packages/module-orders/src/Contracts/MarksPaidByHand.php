<?php

declare(strict_types=1);

namespace WebxUi\Orders\Contracts;

use Carbon\CarbonInterface;
use WebxUi\Orders\MilestoneMark;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Refusal;

/**
 * A manager's «Mark as paid» done by the module that keeps the payments (spec §5.1): bound in the
 * container, the card's button and the agent's tool go through it rather than straight to
 * `Orders::mark()`, so that one action writes everything that module keeps of a payment — its
 * attempt, its journal — beside the milestone. Without a binding they set the milestone alone.
 *
 * The answer is the milestone's mark as `Orders::mark()` gives it: a repeat is the first mark.
 */
interface MarksPaidByHand
{
    /**
     * @throws Refusal the order may not be paid — a cancelled one, as `Orders::mark()` refuses it
     */
    public function markPaid(Order $order, string $source, ?string $reference = null, ?CarbonInterface $at = null, ?string $note = null): MilestoneMark;
}
