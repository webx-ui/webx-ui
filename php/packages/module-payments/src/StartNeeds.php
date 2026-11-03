<?php

declare(strict_types=1);

namespace WebxUi\Payments;

/**
 * What a type needs of the buyer and the order to open a payment (spec §6.3): the base fills only
 * these fields of `StartRequest` — personal data goes to a gateway that asks for it, and to no
 * other (decision 42).
 */
final readonly class StartNeeds
{
    public function __construct(
        public bool $email = false,
        public bool $phone = false,
        public bool $name = false,
        public bool $lines = false,
    ) {}
}
