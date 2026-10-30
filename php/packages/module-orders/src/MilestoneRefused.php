<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * A milestone `mark()` does not set (§5.1): «placed» and «awaiting payment» come only with the
 * order itself, «refunded» only with a refund — it has an amount.
 */
final class MilestoneRefused extends Refusal
{
    public function __construct(public readonly Milestone $milestone)
    {
        parent::__construct('not_markable', "The milestone «{$milestone->value}» is not set by hand: it comes with the order or with a refund.");
    }
}
