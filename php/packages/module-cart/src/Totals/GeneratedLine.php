<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

use InvalidArgumentException;

/** A line a rule adds while counting. Its price is the provider's or at least 1 — never zero. */
final class GeneratedLine
{
    /**
     * @param  string  $source  'rule#12' — also the key declined_rules holds
     * @param  string  $type  a Purchasables type
     * @param  int|null  $parentId  attach under a stored line without a parent
     * @param  int|null  $price  null — the provider's price; else ≥ 1
     * @param  string|null  $label  "Gift", shown beside the name
     */
    public function __construct(
        public readonly string $source,
        public readonly string $type,
        public readonly int $refId,
        public readonly int $qty = 1,
        public readonly ?int $parentId = null,
        public readonly ?int $price = null,
        public readonly bool $declinable = true,
        public readonly ?string $label = null,
    ) {
        // A gift at exactly zero is not allowed; the real minimum is the rules module's setting.
        if ($price !== null && $price < 1) {
            throw new InvalidArgumentException("The price of a generated line is at least 1, [{$source}] gave {$price}.");
        }

        if ($qty < 1) {
            throw new InvalidArgumentException("The qty of a generated line is at least 1, [{$source}] gave {$qty}.");
        }
    }
}
