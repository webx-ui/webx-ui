<?php

declare(strict_types=1);

namespace WebxUi\Orders\Blocks;

use WebxUi\Orders\Refusal;

/** The block is not changed, and nothing is written; `result` is what a dry run would have said. */
final class BlockChangeRefused extends Refusal
{
    public function __construct(public readonly BlockChangeResult $result)
    {
        parent::__construct((string) $result->refusal, match ($result->refusal) {
            BlockChangeResult::STATE => 'A received, cancelled or refunded order is not changed.',
            default => 'The change is the block the order has.',
        });
    }
}
