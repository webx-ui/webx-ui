<?php

declare(strict_types=1);

namespace WebxUi\Geography\Matching;

/** How a binding was made (`method` of a binding, §4.3). */
enum BindingMethod: string
{
    /** One locality of the same country, region, type and name (step 3). */
    case AutoExact = 'auto_exact';

    /** One of several namesakes, or of another type, within the radius (steps 4–5). */
    case AutoCoords = 'auto_coords';

    /** The shared locality was made from this record (step 6 for the leading carrier, or a person). */
    case Created = 'created';

    /** A person bound it in the queue. Never overwritten by a sync. */
    case Manual = 'manual';
}
