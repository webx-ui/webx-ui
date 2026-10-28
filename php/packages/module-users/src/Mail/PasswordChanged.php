<?php

declare(strict_types=1);

namespace WebxUi\Users\Mail;

use WebxUi\Users\Models\User;

/** «Your password was changed» to the address of the account (spec §7 item 4). */
final class PasswordChanged extends Letter
{
    public function __construct(
        User $user,
        public string $link,
    ) {
        parent::__construct($user);
    }

    protected function letter(): string
    {
        return 'password-changed';
    }

    protected function data(): array
    {
        return ['link' => $this->link];
    }
}
