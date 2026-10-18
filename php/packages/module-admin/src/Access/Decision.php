<?php

declare(strict_types=1);

namespace WebxUi\Admin\Access;

/**
 * What {@see AccessPolicy} answered, and why when it is no.
 *
 * The reason is a short code rather than words: a route turns it into a 401 or a 403 in the
 * reader's language, a tool into an error an agent reads, and the journal into a row — each in
 * its own way, from the same answer.
 */
final readonly class Decision
{
    /** Nobody the policy can ask about: no administrator behind the request. */
    public const UNAUTHENTICATED = 'unauthenticated';

    /** None of the permissions asked for is held. */
    public const FORBIDDEN = 'forbidden';

    /** The person may, the token they lent does not carry the scope. */
    public const TOKEN = 'token';

    /**
     * @param  list<string>  $required  what would have let the caller through: permissions, or scopes for {@see self::TOKEN}
     */
    private function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public array $required = [],
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    /**
     * @param  list<string>  $required
     */
    public static function deny(string $reason, array $required = []): self
    {
        return new self(false, $reason, $required);
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }
}
