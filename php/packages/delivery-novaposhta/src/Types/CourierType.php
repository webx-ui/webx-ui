<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Types;

use WebxUi\Delivery\Asks;

/** `novaposhta.courier`: the buyer gets the parcel at the door, on a street of the carrier's directory (§7.1). */
final class CourierType extends NovaPoshtaType
{
    public const string KEY = 'novaposhta.courier';

    protected function kind(): string
    {
        return 'courier';
    }

    public function asks(): Asks
    {
        return Asks::Address;
    }
}
