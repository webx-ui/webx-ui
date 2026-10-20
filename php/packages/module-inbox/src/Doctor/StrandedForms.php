<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Doctor;

use WebxUi\Admin\Doctor\Check;
use WebxUi\Admin\Doctor\Diagnosis;
use WebxUi\Inbox\Notifications\Channels;

/**
 * Forms set to tell their channels on a site that no longer has `webx-ui/module-notifications`.
 *
 * Nothing is lost — such a form writes its letter instead (`Channels::through()`) — but the
 * people who chose channels are waiting for a message in a chat that will not come, and the
 * letter goes to whatever addresses were left on the form from before. A warning in the command
 * a deploy already runs is where somebody removing a package hears about it.
 */
final class StrandedForms implements Check
{
    private const string SUBJECT = 'Inbox';

    public function __construct(private readonly Channels $channels) {}

    public function run(): array
    {
        $found = [];

        foreach ($this->channels->stranded() as $form) {
            $found[] = Diagnosis::warn(self::SUBJECT, sprintf(
                'form "%s" (#%d) is set to notify through channels, and webx-ui/module-notifications is not installed — its submissions go by letter to the addresses of the form. Install the module again, or switch the form to "By letter to addresses" in the panel.',
                $form->slug,
                (int) $form->getKey(),
            ));
        }

        return $found;
    }
}
