<?php

declare(strict_types=1);

namespace WebxUi\Users\Mail;

use WebxUi\Users\Models\User;

/** The link that confirms the address (spec §8.1). */
final class VerifyEmail extends Letter
{
    public function __construct(
        User $user,
        public string $link,
        public int $hours,
    ) {
        parent::__construct($user);
    }

    protected function letter(): string
    {
        return 'verify';
    }

    protected function data(): array
    {
        return ['link' => $this->link, 'hours' => $this->hours];
    }
}
