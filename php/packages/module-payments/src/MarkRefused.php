<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use RuntimeException;

/** A manager's «Mark as paid» the order cannot take (spec §12.2): it is paid, or cancelled. */
final class MarkRefused extends RuntimeException
{
    public const string PAID = 'paid';

    public const string CANCELLED = 'cancelled';

    public function __construct(public readonly string $reason)
    {
        parent::__construct((string) trans('webx-payments::errors.mark.'.$reason));
    }
}
