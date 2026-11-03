<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Types;

use WebxUi\Delivery\Asks;

/** `novaposhta.locker`: the buyer takes the parcel from a parcel locker (§7.1). */
final class LockerType extends NovaPoshtaType
{
    public const string KEY = 'novaposhta.locker';

    protected function kind(): string
    {
        return 'locker';
    }

    public function asks(): Asks
    {
        return Asks::Point;
    }

    public function pointKinds(): array
    {
        return ['locker'];
    }
}
