<?php

declare(strict_types=1);

namespace WebxUi\Users\Social;

/**
 * Who came back from a provider (spec §9.1). Kept in the session while the visitor finishes the
 * registration or signs in with the password to link it (§9.5, §9.7), so it is plain data.
 */
final readonly class SocialProfile
{
    public function __construct(
        public string $id,
        public ?string $email,
        public bool $emailVerified,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $phone = null,
    ) {}

    /** @return array<string, string|bool|null> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'emailVerified' => $this->emailVerified,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'phone' => $this->phone,
        ];
    }

    /** @param  array<array-key, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $text = static fn (string $key): ?string => is_string($data[$key] ?? null) && $data[$key] !== '' ? $data[$key] : null;

        return new self(
            id: (string) $text('id'),
            email: $text('email'),
            emailVerified: ($data['emailVerified'] ?? false) === true,
            firstName: $text('firstName'),
            lastName: $text('lastName'),
            phone: $text('phone'),
        );
    }
}
