<?php

declare(strict_types=1);

namespace WebxUi\Cart;

/** A line as a provider with options sees it (ResolvesWithOptions). */
final class CartRef
{
    /**
     * @param  string  $key  the line's id, or a batch key before the write
     * @param  array<string, mixed>  $options  as stored, never null — [] when empty
     */
    public function __construct(
        public readonly string $key,
        public readonly int $ref,
        public readonly array $options,
    ) {}
}
