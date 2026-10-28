<?php

declare(strict_types=1);

namespace WebxUi\Cart;

/**
 * One line of a batch on its way in: what was asked, and what its provider answered.
 *
 * @internal
 */
final class BatchRow
{
    public ?PurchasableLine $line = null;

    /** @param  array<string, mixed>  $options  normalised, [] when empty */
    public function __construct(
        public readonly int $index,
        public readonly string $type,
        public readonly int $ref,
        public int $qty,
        public readonly array $options,
        public readonly ?string $key,
        public readonly ?string $parent,
    ) {}
}
