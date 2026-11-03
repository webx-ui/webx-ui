<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

use InvalidArgumentException;

/**
 * The price of a method for one cart and locality (§9.1). Amounts are integers in minor units of
 * the cart's currency, as everywhere in the cart.
 */
final readonly class Quote
{
    /** Fixed with an amount above zero: it goes into what the buyer pays the shop. */
    public bool $inTotal;

    private function __construct(
        public QuoteKind $kind,
        /** Fixed only. */
        public ?int $amount,
        /** Free only: the price it would have been, shown crossed out. */
        public ?int $was,
        /** CarrierRates only: for information, never in the total. */
        public ?int $estimate,
        /** Unavailable only: for MCP and logs, never shown to a buyer. */
        public ?string $reason,
        public Payer $payer,
    ) {
        $this->inTotal = $kind === QuoteKind::Fixed && $amount > 0;
    }

    /** The buyer pays this to the shop; it goes into the order total. Zero is «Free». */
    public static function fixed(int $amount): self
    {
        if ($amount < 0) {
            throw new InvalidArgumentException("A delivery price is never negative, {$amount} given.");
        }

        return $amount === 0
            ? self::free()
            : new self(QuoteKind::Fixed, $amount, null, null, null, Payer::Store);
    }

    /** Nothing to pay; $was — the price it would have been, shown crossed out. */
    public static function free(?int $was = null, ?string $reason = null): self
    {
        return new self(QuoteKind::Free, null, $was !== null && $was > 0 ? $was : null, null, $reason, Payer::Store);
    }

    /** The buyer pays the carrier on receipt; $estimate — for information only, not in the total. */
    public static function carrierRates(?int $estimate = null): self
    {
        return new self(QuoteKind::CarrierRates, null, null, $estimate, null, Payer::Buyer);
    }

    /** Not offered for this cart and locality: the method is left out of the list (decision 7). */
    public static function unavailable(string $reason): self
    {
        return new self(QuoteKind::Unavailable, null, null, null, $reason, Payer::Buyer);
    }

    /**
     * What the shop pays for the buyer under `shipping.paid_by_store` (§9.4): «Free» with the price
     * it would have been crossed out, the sender paying on the waybill. A method that is free
     * already stays as it is; one not offered is not offered either way.
     */
    public function paidByStore(): self
    {
        return match ($this->kind) {
            QuoteKind::Fixed => self::free($this->amount, Benefits::PAID_BY_STORE),
            QuoteKind::CarrierRates => self::free($this->estimate, Benefits::PAID_BY_STORE),
            default => $this,
        };
    }

    /** Free because the cart's rules gave `shipping.paid_by_store` — the snapshot's `paid_by_store`. */
    public function isPaidByStore(): bool
    {
        return $this->kind === QuoteKind::Free && $this->reason === Benefits::PAID_BY_STORE;
    }

    public function available(): bool
    {
        return $this->kind !== QuoteKind::Unavailable;
    }

    /**
     * What {@see self::toStored()} gave — the cache keeps arrays, never objects.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromStored(array $row): self
    {
        $int = static fn (mixed $value): ?int => is_numeric($value) ? (int) $value : null;
        $reason = isset($row['reason']) && is_string($row['reason']) ? $row['reason'] : null;

        return match (QuoteKind::tryFrom((string) ($row['kind'] ?? ''))) {
            QuoteKind::Fixed => self::fixed(max(0, $int($row['amount'] ?? null) ?? 0)),
            QuoteKind::Free => self::free($int($row['was'] ?? null), $reason),
            QuoteKind::CarrierRates => self::carrierRates($int($row['estimate'] ?? null)),
            default => self::unavailable($reason ?? 'unknown'),
        };
    }

    /** @return array{kind: string, amount: int|null, was: int|null, estimate: int|null, reason: string|null} */
    public function toStored(): array
    {
        return [
            'kind' => $this->kind->value,
            'amount' => $this->amount,
            'was' => $this->was,
            'estimate' => $this->estimate,
            'reason' => $this->reason,
        ];
    }

    /** @return array{kind: string, price: int|null, was: int|null, estimate: int|null, in_total: bool, payer: string} */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'price' => match ($this->kind) {
                QuoteKind::Fixed => $this->amount,
                QuoteKind::Free => 0,
                default => null,
            },
            'was' => $this->was,
            'estimate' => $this->estimate,
            'in_total' => $this->inTotal,
            'payer' => $this->payer->value,
        ];
    }
}
