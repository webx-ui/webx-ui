<?php

declare(strict_types=1);

namespace WebxUi\Cart;

/**
 * What a line holds now. New fields only ever come last and optional, so a provider builds it
 * with named arguments and never breaks when one is added.
 */
final class PurchasableLine
{
    /**
     * @param  int  $price  minor units of $currency; 0 — price on request
     * @param  string  $currency  ISO 4217, upper case
     * @param  string|null  $image  absolute or root-relative URL
     * @param  int|null  $maxQty  null — no stock to check
     * @param  array<string, string>|null  $names  locale → name in every language of the site, for
     *                                             the snapshot a line keeps; null — $name alone
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $sku,
        public readonly int $price,
        public readonly string $currency,
        public readonly ?string $optionsLabel = null,
        public readonly ?string $image = null,
        public readonly ?string $url = null,
        public readonly bool $shippable = true,
        public readonly ?int $maxQty = null,
        public readonly bool $available = true,
        public readonly ?array $names = null,
    ) {}

    /** Whether it can be bought: listed as available and with a price — 0 is «price on request». */
    public function buyable(): bool
    {
        return $this->available && $this->price > 0;
    }
}
