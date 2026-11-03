<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use RuntimeException;

/**
 * The gateway answered and said no (spec §6.4): a capture above the held amount, a void of a
 * payment already captured, a cancel of a paid one. Words for the panel, never the body.
 */
final class GatewayRefused extends RuntimeException
{
    public static function because(string $words): self
    {
        return new self($words);
    }
}
