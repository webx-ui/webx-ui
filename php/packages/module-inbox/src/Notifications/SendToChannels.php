<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Notifications;

use WebxUi\Inbox\Events\SubmissionStored;
use WebxUi\Inbox\Models\Submission;

/**
 * A submission from the site goes to the channels its form chose (NOTIFICATIONS §15.3).
 *
 * Not for one sent again inside the duplicate window — that is a double click, and a messenger
 * pinging twice for it is noise — and not for one typed in by hand: whoever typed it knows. Only for
 * a form set to channels (`Channels::through()`): the other kind is written a letter by the intake.
 *
 * Without `webx-ui/module-notifications` this does nothing: the event is still fired for the
 * site's own listeners.
 */
final class SendToChannels
{
    public function __construct(private readonly Channels $channels) {}

    public function handle(SubmissionStored $event): void
    {
        if ($event->repeated || $event->submission->source !== Submission::SOURCE_WEB) {
            return;
        }

        $this->channels->send($event->submission);
    }
}
