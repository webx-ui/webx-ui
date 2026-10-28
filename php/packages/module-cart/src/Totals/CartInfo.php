<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

/** The cart as a source sees it — never the model, so nobody writes through it. */
final class CartInfo
{
    /**
     * @param  int|null  $id  null — no cart yet (a quote, an empty state)
     * @param  list<string>  $declinedRules
     */
    public function __construct(
        public readonly ?int $id,
        public readonly string $locale,
        public readonly ?string $currency,
        public readonly array $declinedRules = [],
    ) {}
}
