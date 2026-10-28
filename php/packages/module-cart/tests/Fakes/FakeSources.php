<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests\Fakes;

use Closure;
use WebxUi\Cart\Totals\AdjustmentSource;
use WebxUi\Cart\Totals\BenefitSource;
use WebxUi\Cart\Totals\CartContext;
use WebxUi\Cart\Totals\HintSource;
use WebxUi\Cart\Totals\LineSource;
use WebxUi\Cart\Totals\TenderSource;

/**
 * One source for every link of the chain: it answers whatever its closure returns, and remembers
 * the context it was given.
 */
final class FakeSources implements AdjustmentSource, BenefitSource, HintSource, LineSource, TenderSource
{
    /** @var list<CartContext> */
    public array $seen = [];

    /** @param  Closure(CartContext): array<mixed>  $answer */
    public function __construct(private readonly Closure $answer) {}

    public function lines(CartContext $cart): array
    {
        return $this->answer($cart);
    }

    public function adjustments(CartContext $cart): array
    {
        return $this->answer($cart);
    }

    public function tenders(CartContext $cart): array
    {
        return $this->answer($cart);
    }

    public function benefits(CartContext $cart): array
    {
        return $this->answer($cart);
    }

    public function hints(CartContext $cart): array
    {
        return $this->answer($cart);
    }

    /** @return list<mixed> */
    private function answer(CartContext $cart): array
    {
        $this->seen[] = $cart;

        /** @var list<mixed> */
        return ($this->answer)($cart);
    }
}
