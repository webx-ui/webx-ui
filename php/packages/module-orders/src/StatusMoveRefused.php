<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * The order is not moved, and nothing of the move is written (§5.2): the status is off or
 * deleted, or its milestone is not allowed here — a status «Paid» never stands on a cancelled
 * order. `move` is what a dry run would have answered.
 */
final class StatusMoveRefused extends Refusal
{
    public function __construct(public readonly StatusMove $move)
    {
        parent::__construct((string) $move->refusal, match ($move->refusal) {
            StatusMove::INACTIVE => "The status «{$move->to->name}» is switched off.",
            StatusMove::DELETED => "The status «{$move->to->name}» is deleted.",
            default => "The status «{$move->to->name}» sets «{$move->milestone?->value}», which the cancelled order does not take.",
        });
    }
}
