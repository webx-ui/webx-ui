<?php

declare(strict_types=1);

namespace WebxUi\Geography;

use Illuminate\Support\Collection;
use WebxUi\Geography\Models\GeoLocality;

/**
 * A locality like this one already exists: same country, region and type, one of its names or
 * aliases has the same key. The form offers the ones found instead; creating anyway takes
 * `force` (decision 42).
 */
final class DuplicateLocality extends GeographyException
{
    /** @param  Collection<int, GeoLocality>  $similar */
    public function __construct(public readonly Collection $similar)
    {
        parent::__construct(sprintf(
            'A locality like this already exists: %s. Pick it instead, or create anyway.',
            $similar->map(static fn (GeoLocality $locality): string => '#'.$locality->id.' '.$locality->label())->implode('; '),
        ));
    }
}
