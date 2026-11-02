<?php

declare(strict_types=1);

namespace WebxUi\Delivery\NovaPoshta\Tests\Fakes;

use WebxUi\Cart\Contracts\Purchasable;
use WebxUi\Cart\PurchasableLine;

/** `test.parcel` of the cart: ref 1 is a parcel at 500.00 — something for the delivery step to carry. */
final class FakeParcel implements Purchasable
{
    public function resolve(array $refs, string $locale): array
    {
        $found = [];

        foreach ($refs as $ref) {
            $found[$ref] = $ref === 1 ? new PurchasableLine('Parcel', 'P-1', 50000, 'UAH') : null;
        }

        return $found;
    }
}
