<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use RuntimeException;

/**
 * A new attempt the order cannot take (spec §8.5, §9.4): it is cancelled, owes nothing, an
 * earlier attempt turned out paid, its money is held, or the method is not an online one whose
 * type is installed.
 */
final class BeginRefused extends RuntimeException
{
    public const string CANCELLED = 'cancelled';

    public const string NOTHING_DUE = 'nothing_due';

    public const string NOT_ONLINE = 'not_online';

    public const string PAID = 'paid';

    public const string HELD = 'held';

    public function __construct(public readonly string $reason)
    {
        parent::__construct((string) trans('webx-payments::errors.begin.'.$reason));
    }
}
