<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

/**
 * The totals line «Delivery cost» of the checkout (decision 7, §9.3): an amount — the same the
 * chain added as its delivery adjustment, never added twice — «Free» with the old price crossed
 * out, or «By carrier rates» with an estimate for information. Words and amounts are ready to
 * print in the cart's language and currency.
 */
final readonly class DeliveryCost
{
    public function __construct(
        public string $method,
        public Quote $quote,
        public string $label,
        /** The amount written, «Free» or «By carrier rates». */
        public string $text,
        public ?string $amountFormatted,
        public ?string $wasFormatted,
        public ?string $estimateFormatted,
    ) {}

    /** In the total already, as the chain's delivery adjustment. */
    public function inTotal(): bool
    {
        return $this->quote->inTotal;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'label' => $this->label,
            'kind' => $this->quote->kind->value,
            'text' => $this->text,
            'amount' => $this->quote->amount,
            'amount_formatted' => $this->amountFormatted,
            'was' => $this->quote->was,
            'was_formatted' => $this->wasFormatted,
            'estimate' => $this->quote->estimate,
            'estimate_formatted' => $this->estimateFormatted,
            'in_total' => $this->quote->inTotal,
            'paid_by_store' => $this->quote->isPaidByStore(),
        ];
    }
}
