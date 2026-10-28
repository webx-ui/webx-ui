<?php

declare(strict_types=1);

namespace WebxUi\Cart;

use RuntimeException;

/** A line's type no provider is registered for — its module was removed, or never installed. */
final class UnknownPurchasable extends RuntimeException
{
    public static function type(string $type): self
    {
        return new self("No purchasable is registered as [{$type}].");
    }
}
