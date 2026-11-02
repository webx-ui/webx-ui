<?php

declare(strict_types=1);

namespace WebxUi\Geography\Matching;

/**
 * Which localities a carrier has already bound one of its records to (§4.3, decision of
 * 02.11.2026): a carrier never lists one town twice, so a locality another record of the same
 * carrier is bound to is not a candidate for the next one. Two villages of one name in one
 * region — told apart by the carrier only by a qualifier — stay two towns instead of one.
 *
 * The bindings are the delivery module's (`delivery_carrier_localities`), which binds its own
 * implementation; the library on its own keeps none ({@see NoCarrierBindings}) and only knows
 * what the page it matches has bound.
 */
interface CarrierBindings
{
    /**
     * The ids among `$localityIds` that a record of `$carrier` other than those of `$except` is
     * bound to.
     *
     * @param  list<int>  $localityIds
     * @param  list<string>  $except  external ids of the records being matched now
     * @return list<int>
     */
    public function taken(string $carrier, array $localityIds, array $except): array;
}
