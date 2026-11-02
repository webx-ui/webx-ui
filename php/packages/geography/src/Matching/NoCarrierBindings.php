<?php

declare(strict_types=1);

namespace WebxUi\Geography\Matching;

/** The library on its own stores no bindings: only the page being matched takes a locality. */
final class NoCarrierBindings implements CarrierBindings
{
    public function taken(string $carrier, array $localityIds, array $except): array
    {
        return [];
    }
}
