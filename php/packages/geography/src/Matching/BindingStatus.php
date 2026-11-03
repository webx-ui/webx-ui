<?php

declare(strict_types=1);

namespace WebxUi\Geography\Matching;

/** Where a carrier's locality stands against the shared geography (`status` of a binding). */
enum BindingStatus: string
{
    /** Bound to a shared locality. */
    case Matched = 'matched';

    /** Waits for a person in the binding queue; `reason` says why. */
    case Pending = 'pending';

    /** A person said "do not bind": the matcher leaves it be from then on. */
    case Ignored = 'ignored';
}
