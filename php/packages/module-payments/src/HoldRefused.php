<?php

declare(strict_types=1);

namespace WebxUi\Payments;

use RuntimeException;

/**
 * A capture or a release of a hold the base will not ask the gateway for (spec §9.5): nothing is
 * held, the type has no hold, more than is held, a part where the gateway takes only all of it.
 */
final class HoldRefused extends RuntimeException
{
    public const string NOT_HELD = 'not_held';

    public const string NO_HOLD = 'no_hold';

    public const string AMOUNT = 'amount';

    public const string TOO_MUCH = 'too_much';

    public const string PARTIAL = 'partial';

    public const string BUSY = 'busy';

    public function __construct(public readonly string $reason)
    {
        parent::__construct((string) trans('webx-payments::errors.hold.'.$reason));
    }
}
