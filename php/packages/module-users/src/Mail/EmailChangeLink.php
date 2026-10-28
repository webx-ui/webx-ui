<?php

declare(strict_types=1);

namespace WebxUi\Users\Mail;

use WebxUi\Users\Models\User;

/** The link to the new address that moves the sign-in there (spec §8.5). */
final class EmailChangeLink extends Letter
{
    public function __construct(
        User $user,
        public string $link,
        public string $email,
        public int $minutes,
    ) {
        parent::__construct($user);
    }

    protected function letter(): string
    {
        return 'email-change';
    }

    protected function data(): array
    {
        return ['link' => $this->link, 'email' => $this->email, 'minutes' => $this->minutes];
    }
}
