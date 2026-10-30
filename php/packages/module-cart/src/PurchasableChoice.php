<?php

declare(strict_types=1);

namespace WebxUi\Cart;

/**
 * One record found by `FindsPurchasables::find()`: what it is and costs now, the line it would
 * make, and the choices a line of it must name — a size, a colour — which go into the line's
 * `options` the way a buy button sends them.
 */
final readonly class PurchasableChoice
{
    /**
     * @param  list<ChoiceOption>  $options  empty — the record is bought as it is
     * @param  string|null  $hint  what tells two records of one name apart: a category, a brand
     */
    public function __construct(
        public int $ref,
        public PurchasableLine $line,
        public array $options = [],
        public ?string $hint = null,
    ) {}
}
