<?php

declare(strict_types=1);

namespace WebxUi\Users\Mail;

use WebxUi\Users\Models\User;

/** «Sign-in with Google was linked» to the address of the account (spec §9.6, §9.7). */
final class ProviderLinked extends Letter
{
    /** @param  string  $provider  the provider's label: a dictionary key or plain words */
    public function __construct(
        User $user,
        public string $provider,
        public string $link,
    ) {
        parent::__construct($user);
    }

    protected function letter(): string
    {
        return 'provider-linked';
    }

    protected function subjectWords(): array
    {
        return ['provider' => (string) __($this->provider)];
    }

    protected function data(): array
    {
        return ['provider' => (string) __($this->provider), 'link' => $this->link];
    }
}
