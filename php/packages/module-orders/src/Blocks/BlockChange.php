<?php

declare(strict_types=1);

namespace WebxUi\Orders\Blocks;

/**
 * A block replaced whole by a manager after placing — the buyer chose the wrong pick-up point,
 * the street was mistyped — with the block's price in the total, when it has one: the
 * adjustment of the block's `charge` source goes and this one takes its place.
 */
final readonly class BlockChange
{
    /**
     * @param  string  $block  'delivery' | 'payment'
     * @param  array<string, mixed>  $value  the new block, whole — as its owner writes it at placing
     * @param  int|null  $amount  the price the buyer pays the shop for it, in minor units; null or 0 —
     *                            nothing in the total («Free», «By carrier rates»)
     * @param  string  $label  the adjustment's label, in the order's language
     * @param  array<string, mixed>  $meta  the adjustment's meta, as at placing
     * @param  string|null  $reason  why — for the managers, never shown to the buyer
     */
    public function __construct(
        public string $block,
        public array $value,
        public ?int $amount = null,
        public string $label = '',
        public array $meta = [],
        public ?string $reason = null,
        public string $source = 'panel',
    ) {}
}
