<?php

declare(strict_types=1);

namespace WebxUi\Auth\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Auth\Profile\EmailChange;

/**
 * The letter that proves a new sign-in address is somebody's: one link, and what it does.
 *
 * Not queued. The person is looking at the form that sent it, and "the letter did not leave" is
 * something they should hear there and then, not discover an hour later in an empty inbox.
 */
final class EmailChangeLink extends Mailable
{
    public function __construct(
        public readonly CmsUser $user,
        public readonly string $link,
    ) {
        // The language they read the panel in, which is the one they asked from.
        $this->locale($user->locale ?? app()->getLocale());
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) trans('webx-auth::profile.email-letter-subject', [
            'site' => (string) config('app.name'),
        ]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'webx-auth::mail.email-change',
            with: [
                'name' => $this->user->name,
                'link' => $this->link,
                'minutes' => EmailChange::MINUTES,
                'site' => (string) config('app.name'),
            ],
        );
    }
}
