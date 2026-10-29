<?php

declare(strict_types=1);

namespace WebxUi\Orders\Demo;

use WebxUi\Cart\Totals\Adjustment;
use WebxUi\Cart\Totals\AdjustmentSource;
use WebxUi\Cart\Totals\Benefit;
use WebxUi\Cart\Totals\BenefitSource;
use WebxUi\Cart\Totals\CartContext;
use WebxUi\Cart\Totals\GeneratedLine;
use WebxUi\Cart\Totals\LineSource;
use WebxUi\Cart\Totals\Tender;
use WebxUi\Cart\Totals\TenderSource;

/**
 * A rule and a wallet for one demo order at a time (spec §15): the discount with its gift line and
 * the wallet's tender come from the cart's chain like a neighbour's would, so `place()` freezes
 * them as it freezes any. The demo fills the answer before an order and empties it after; empty,
 * the source answers nothing to any other cart of the same process.
 */
final class DemoSources implements AdjustmentSource, BenefitSource, LineSource, TenderSource
{
    /** @var list<GeneratedLine> */
    public array $lines = [];

    /** @var list<Adjustment> */
    public array $adjustments = [];

    /** @var list<Tender> */
    public array $tenders = [];

    /** @var list<Benefit> */
    public array $benefits = [];

    public function forget(): void
    {
        $this->lines = $this->adjustments = $this->tenders = $this->benefits = [];
    }

    public function lines(CartContext $cart): array
    {
        return $this->lines;
    }

    public function adjustments(CartContext $cart): array
    {
        return $this->adjustments;
    }

    public function tenders(CartContext $cart): array
    {
        return $this->tenders;
    }

    public function benefits(CartContext $cart): array
    {
        return $this->benefits;
    }
}
