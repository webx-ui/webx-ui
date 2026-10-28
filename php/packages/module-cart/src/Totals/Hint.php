<?php

declare(strict_types=1);

namespace WebxUi\Cart\Totals;

final class Hint
{
    /**
     * @param  string  $text  in the cart's language
     * @param  int|null  $missing  minor units still to go, for a progress bar
     */
    public function __construct(
        public readonly string $source,
        public readonly string $text,
        public readonly ?int $missing = null,
    ) {}
}
