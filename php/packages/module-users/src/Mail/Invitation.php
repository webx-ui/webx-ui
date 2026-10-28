<?php

declare(strict_types=1);

namespace WebxUi\Users\Mail;

use WebxUi\Users\Models\User;

/**
 * The invitation of a visitor made in the panel (spec §16.2): the account is there, the password
 * is the visitor's to set — on the page of a reset link, which says «Set a password» for it.
 */
final class Invitation extends Letter
{
    public function __construct(
        User $user,
        public string $link,
        public int $days,
    ) {
        parent::__construct($user);
    }

    protected function letter(): string
    {
        return 'invite';
    }

    protected function data(): array
    {
        return ['link' => $this->link, 'days' => $this->days, 'email' => $this->user->email];
    }
}
