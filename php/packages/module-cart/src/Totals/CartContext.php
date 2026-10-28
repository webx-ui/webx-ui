<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

/**
 * What every source of the chain gets: an immutable snapshot. Steps 4 and 5 see the adjustments
 * gathered before them and the total; step 7 sees the tenders too.
 */
final class CartContext
{
    /**
     * @param  list<ResolvedLine>  $lines
     * @param  list<Adjustment>  $adjustments
     * @param  list<Tender>  $tenders
     */
    public function __construct(
        public readonly CartInfo $cart,
        public readonly array $lines,
        public readonly int $subtotal,
        public readonly Purpose $purpose,
        public readonly array $adjustments = [],
        public readonly int $total = 0,
        public readonly array $tenders = [],
    ) {}

    /** @param  list<ResolvedLine>  $lines */
    public function withLines(array $lines, int $subtotal): self
    {
        return new self($this->cart, $lines, $subtotal, $this->purpose, $this->adjustments, $subtotal, $this->tenders);
    }

    /** @param  list<Adjustment>  $adjustments */
    public function withAdjustments(array $adjustments, int $total): self
    {
        return new self($this->cart, $this->lines, $this->subtotal, $this->purpose, $adjustments, $total, $this->tenders);
    }

    /** @param  list<Tender>  $tenders */
    public function withTenders(array $tenders): self
    {
        return new self($this->cart, $this->lines, $this->subtotal, $this->purpose, $this->adjustments, $this->total, $tenders);
    }

    public function line(int|string $key): ?ResolvedLine
    {
        foreach ($this->lines as $line) {
            if ($line->key === $key) {
                return $line;
            }
        }

        return null;
    }
}
