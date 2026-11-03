<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Settings;

use WebxUi\Delivery\DeliveryException;

/** A save of a carrier's settings with a value its account does not have: field → words. */
final class SettingsRejected extends DeliveryException
{
    /** @param  array<string, string>  $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode(' ', array_map(
            static fn (string $field, string $words): string => "{$field}: {$words}",
            array_keys($errors),
            $errors,
        )));
    }
}
