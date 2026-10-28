<?php

declare(strict_types=1);

namespace WebxUi\Admin\Visitors;

use Illuminate\Support\Str;

/**
 * A visitor as other modules see them: what a shop needs to fill in an order and to tell one
 * customer from another, and nothing a module could leak — no password, no tokens.
 */
final readonly class Visitor
{
    /**
     * @param  bool  $verified  the e-mail is confirmed
     * @param  list<string>  $groups  codes of the customer groups the visitor is in
     */
    public function __construct(
        public int $id,
        public string $email,
        public bool $verified,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $phone = null,
        public ?string $locale = null,
        public array $groups = [],
    ) {}

    /** First and last name, or the part of the e-mail before "@" for a visitor who gave neither. */
    public function name(): string
    {
        $name = trim(($this->firstName ?? '').' '.($this->lastName ?? ''));

        return $name !== '' ? $name : Str::before($this->email, '@');
    }

    public function inGroup(string $code): bool
    {
        return in_array($code, $this->groups, true);
    }
}
