<?php

declare(strict_types=1);

namespace WebxUi\Orders\Tests\Fakes;

use WebxUi\Cart\Contracts\Purchasable;
use WebxUi\Cart\PurchasableLine;

/** A provider whose records are what the test says they are now; a missing one is gone. */
final class FakeProducts implements Purchasable
{
    /** @param  array<int, PurchasableLine>  $lines */
    public function __construct(public array $lines = []) {}

    public function resolve(array $refs, string $locale): array
    {
        $found = [];

        foreach ($refs as $ref) {
            if (isset($this->lines[$ref])) {
                $found[$ref] = $this->lines[$ref];
            }
        }

        return $found;
    }
}
