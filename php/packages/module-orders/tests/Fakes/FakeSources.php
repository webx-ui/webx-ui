<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Fakes;

use Closure;
use WebxUi\Cart\Totals\AdjustmentSource;
use WebxUi\Cart\Totals\BenefitSource;
use WebxUi\Cart\Totals\CartContext;
use WebxUi\Cart\Totals\LineSource;
use WebxUi\Cart\Totals\TenderSource;

/** A source of any link of the chain: it answers whatever its closure returns now. */
final class FakeSources implements AdjustmentSource, BenefitSource, LineSource, TenderSource
{
    /** @param  Closure(CartContext): array<mixed>  $answer */
    public function __construct(private readonly Closure $answer) {}

    public function lines(CartContext $cart): array
    {
        return ($this->answer)($cart);
    }

    public function adjustments(CartContext $cart): array
    {
        return ($this->answer)($cart);
    }

    public function tenders(CartContext $cart): array
    {
        return ($this->answer)($cart);
    }

    public function benefits(CartContext $cart): array
    {
        return ($this->answer)($cart);
    }
}
