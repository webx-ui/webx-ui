<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use RuntimeException;

/** A method names a type nobody registered: its satellite is not installed (spec §5). */
final class UnknownMethodType extends RuntimeException
{
    /** @param  list<string>  $known */
    public static function named(string $key, array $known): self
    {
        return new self("No payment method type is called [{$key}]. Registered: ".($known === [] ? 'none' : implode(', ', $known)).'.');
    }
}
