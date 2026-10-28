<?php

declare(strict_types=1);

namespace WebxUi\Users\Mail;

use WebxUi\Users\Models\User;

/** The link of «Forgot your password?» (spec §7). */
final class ResetPassword extends Letter
{
    public function __construct(
        User $user,
        public string $link,
        public int $minutes,
    ) {
        parent::__construct($user);
    }

    protected function letter(): string
    {
        return 'reset';
    }

    protected function data(): array
    {
        return ['link' => $this->link, 'minutes' => $this->minutes];
    }
}
