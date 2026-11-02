<?php

declare(strict_types=1);

namespace WebxUi\Delivery;

/** What the buyer chose, checked: the point or the address resolved to stored records (§6.1). */
final readonly class Selection
{
    public function __construct(
        public Method $method,
        public LocalityRef $locality,
        /** Asks::Point. */
        public ?PointRef $point = null,
        /** Asks::Address. */
        public ?Address $address = null,
    ) {}
}
