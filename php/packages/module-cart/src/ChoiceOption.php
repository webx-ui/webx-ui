<?php

declare(strict_types=1);

namespace WebxUi\Cart;

/**
 * A choice a line of a found record must name (`PurchasableChoice::$options`): the key of the
 * line's `options`, its label, and the values to pick from. A value comes back exactly as given —
 * an integer stays an integer — so the provider's `ChecksOptions` reads what it offered.
 */
final readonly class ChoiceOption
{
    /**
     * @param  list<array{value: string|int, label: string}>  $values
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $values,
    ) {}
}
