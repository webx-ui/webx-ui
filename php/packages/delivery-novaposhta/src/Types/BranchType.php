<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Types;

use WebxUi\Delivery\Asks;

/** `novaposhta.branch`: the buyer picks the parcel up at a branch (§7.1). */
final class BranchType extends NovaPoshtaType
{
    public const string KEY = 'novaposhta.branch';

    protected function kind(): string
    {
        return 'branch';
    }

    public function asks(): Asks
    {
        return Asks::Point;
    }

    public function pointKinds(): array
    {
        return ['branch'];
    }
}
