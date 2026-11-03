<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use RuntimeException;

/**
 * «Problem resolved» the attempt cannot take (spec §9.4): it has no problem, or the problem is a
 * hold's — a capture or a release puts that right.
 */
final class ProblemRefused extends RuntimeException
{
    public const string NONE = 'none';

    public const string HOLD = 'hold';

    public function __construct(public readonly string $reason)
    {
        parent::__construct((string) trans('webx-payments::errors.problem.'.$reason));
    }
}
