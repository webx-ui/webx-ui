<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

/** A method names a type nobody registered — its satellite was removed, or the key is wrong. */
final class UnknownMethodType extends DeliveryException
{
    /** @param  list<string>  $known */
    public static function named(string $key, array $known): self
    {
        return new self(sprintf(
            'Delivery method type %s is not installed. Known types: %s.',
            $key,
            $known === [] ? 'none' : implode(', ', $known),
        ));
    }
}
