<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use LogicException;

/** An online type whose gateway is not registered — a satellite registers both (spec §6.2). */
final class UnknownGateway extends LogicException
{
    public static function named(string $key): self
    {
        return new self("Payment gateway [{$key}] is not registered: an online type comes with its gateway (Gateways::register).");
    }
}
