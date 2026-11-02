<?php

declare(strict_types=1);

namespace WebxUi\Delivery\Tests\Fakes;

use WebxUi\Cart\Contracts\Purchasable;
use WebxUi\Cart\PurchasableLine;

/** `test.goods` of the cart: ref 1 is a parcel at 50.00, ref 2 a service at 20.00 that does not travel. */
final class FakeGoods implements Purchasable
{
    public function resolve(array $refs, string $locale): array
    {
        $found = [];

        foreach ($refs as $ref) {
            $found[$ref] = match ($ref) {
                1 => new PurchasableLine('Parcel', 'P-1', 5000, 'EUR'),
                2 => new PurchasableLine('Assembly', 'S-2', 2000, 'EUR', shippable: false),
                default => null,
            };
        }

        return $found;
    }
}
