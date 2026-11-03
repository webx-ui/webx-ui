<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use RuntimeException;
use Throwable;

/**
 * The gateway did not answer, or answered with an error the payment cannot go on from: a timeout,
 * a 5xx, an answer that is not what its API returns (spec §6.3). The message is words for the
 * panel and the log — never the body, which is in the exchange journal (§15.2).
 */
final class GatewayUnavailable extends RuntimeException
{
    public static function timeout(?Throwable $previous = null): self
    {
        return new self('The gateway did not answer in time', 0, $previous);
    }

    public static function answered(int $status, ?Throwable $previous = null): self
    {
        return new self("The gateway answered with HTTP {$status}", 0, $previous);
    }

    public static function malformed(string $what = 'answer'): self
    {
        return new self("The gateway's {$what} is not what its API returns");
    }

    public static function because(string $words, ?Throwable $previous = null): self
    {
        return new self($words, 0, $previous);
    }
}
