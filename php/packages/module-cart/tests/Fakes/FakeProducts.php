<?php

declare(strict_types=1);

namespace WebxUi\Cart\Tests\Fakes;

use WebxUi\Cart\Contracts\Purchasable;
use WebxUi\Cart\PurchasableLine;

/** A plain provider: a record is what the test says it is; a missing one is gone. */
final class FakeProducts implements Purchasable
{
    /** @var list<list<int>> every batch it was asked about */
    public array $calls = [];

    /** @param  array<int, PurchasableLine|null>  $lines */
    public function __construct(public array $lines = []) {}

    public function resolve(array $refs, string $locale): array
    {
        $this->calls[] = $refs;
        $found = [];

        foreach ($refs as $ref) {
            if (isset($this->lines[$ref])) {
                $found[$ref] = $this->lines[$ref];
            }
        }

        return $found;
    }
}
