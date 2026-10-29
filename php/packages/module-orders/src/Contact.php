<?php

declare(strict_types=1);

namespace WebxUi\Orders;

/**
 * Who buys or who receives, as the order keeps it: the first and the last name apart, because a
 * carrier's waybill needs them apart (decision 40). A recipient has no e-mail — letters go to the
 * buyer.
 *
 * What a form makes required is the checkout's; the order needs a first name and a phone or an
 * e-mail to reach the buyer by. `Orders::place()` checks it and answers like a form (§6.1).
 */
final readonly class Contact
{
    public function __construct(
        public string $firstName,
        public ?string $lastName = null,
        public ?string $phone = null,
        public ?string $email = null,
    ) {}

    /** The same contact, trimmed, an empty field as null and the e-mail in lower case. */
    public function normalised(): self
    {
        return new self(
            trim($this->firstName),
            self::blank($this->lastName),
            self::blank($this->phone),
            ($email = self::blank($this->email)) !== null ? mb_strtolower($email) : null,
        );
    }

    /** Digits only, for search by a phone however it was written; null — no phone. */
    public function phoneKey(): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $this->phone) ?? '';

        return $digits === '' ? null : substr($digits, 0, 20);
    }

    /** «First Last». */
    public function name(): string
    {
        return trim($this->firstName.' '.($this->lastName ?? ''));
    }

    private static function blank(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
