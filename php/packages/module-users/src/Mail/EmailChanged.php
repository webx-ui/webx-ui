<?php

declare(strict_types=1);

namespace WebxUi\Users\Mail;

use WebxUi\Users\Models\User;

/** «Your sign-in address was changed» to the address it was changed from (spec §8.5). */
final class EmailChanged extends Letter
{
    public function __construct(
        User $user,
        public string $email,
        public string $link,
    ) {
        parent::__construct($user);
    }

    protected function letter(): string
    {
        return 'email-changed';
    }

    protected function data(): array
    {
        return ['email' => $this->email, 'link' => $this->link];
    }
}
