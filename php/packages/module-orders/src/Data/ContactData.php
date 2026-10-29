<?php

declare(strict_types=1);

namespace WebxUi\Orders\Data;

/** A buyer or a recipient as the order keeps them; a recipient has no e-mail. */
final readonly class ContactData
{
    public function __construct(
        public string $firstName,
        public ?string $lastName,
        public ?string $phone,
        public ?string $email,
    ) {}

    /** «First Last» — for letters, the list and search (decision 40). */
    public function name(): string
    {
        return trim($this->firstName.' '.($this->lastName ?? ''));
    }
}
