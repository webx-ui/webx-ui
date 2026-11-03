<?php

declare(strict_types=1);

namespace WebxUi\Geography\Matching;

/** Why a carrier's locality waits for a person (`reason` of a pending binding, §4.3). */
enum QueueReason: string
{
    /** The region is neither a region of the country nor an alias of one (step 2). */
    case RegionUnknown = 'region_unknown';

    /** Several namesakes and the coordinates do not pick one (step 4). */
    case Ambiguous = 'ambiguous';

    /** Only a locality of another type has the name, and not within the radius (step 5). */
    case KindMismatch = 'kind_mismatch';

    /** Nothing has the name, and the carrier does not lead the country (step 6). */
    case NotFound = 'not_found';
}
