<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * The edit is not made, and nothing of it is written (§37.1). `result` is what a dry run would
 * have answered: the refusal, the field it points at, the order as it stands.
 */
final class ItemsEditRefused extends Refusal
{
    public function __construct(public readonly ItemsEditResult $result)
    {
        parent::__construct((string) $result->refusal, match ($result->refusal) {
            ItemsEditResult::STALE => 'The lines were edited meanwhile: open the order again.',
            ItemsEditResult::NOTHING => 'The edit changes nothing.',
            ItemsEditResult::EMPTY => 'No line would be left: cancel the order instead.',
            ItemsEditResult::STATE => 'A received, cancelled or refunded order is not edited: a return and a new order instead.',
            ItemsEditResult::CHILD => "The line «{$result->field}» is part of a bundle: change the bundle.",
            ItemsEditResult::GENERATED => "The line «{$result->field}» is a rule's gift: it may be taken out, not counted again.",
            ItemsEditResult::BELOW_REFUNDED => "The line «{$result->field}» keeps at least the units refunded of it.",
            default => "The line «{$result->field}» is not in the order.",
        });
    }
}
