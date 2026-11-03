<?php

declare(strict_types=1);

namespace WebxUi\Payments;

/**
 * Whether a method may be offered (spec §7.2). The buyer never sees an unavailable method; the
 * reason — a key of `webx-payments::reasons` — is for the manager and the agent.
 */
final readonly class Availability
{
    /** @param  array<string, scalar>  $replace */
    private function __construct(
        public bool $available,
        public ?string $reason = null,
        public array $replace = [],
    ) {}

    public static function yes(): self
    {
        return new self(true);
    }

    /** @param  array<string, scalar>  $replace */
    public static function no(string $reasonKey, array $replace = []): self
    {
        return new self(false, $reasonKey, $replace);
    }

    /** The reason in words, in the language asked for; null — available. */
    public function message(?string $locale = null): ?string
    {
        if ($this->reason === null) {
            return null;
        }

        // A type's own reason is a full key of its dictionary; the base's are short.
        $key = str_contains($this->reason, '::') ? $this->reason : 'webx-payments::reasons.'.$this->reason;

        return (string) trans($key, $this->replace, $locale);
    }
}
