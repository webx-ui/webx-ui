<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use WebxUi\Inbox\Models\Submission;

/**
 * What a recipient gets when a submission arrives (§9).
 *
 * `ShouldQueue` and nothing else: a site with a queue gets one, a site on `sync` — which is
 * every fresh Laravel — sends it inline and notices nothing but the wait. Either way the
 * submission is already in the database by the time this exists (§2.10).
 *
 * The view is published like any other, so a site that wants its own letterhead has one
 * without this package knowing.
 */
class SubmissionReceived extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly Submission $submission) {}

    public function envelope(): Envelope
    {
        $title = (string) $this->submission->form->title;

        return new Envelope(
            subject: (string) trans('webx-inbox::mail.subject', [
                'form' => $title !== '' ? $title : $this->submission->form->slug,
            ]),
            // Answering the mail answers the person who wrote, when the form has a field that
            // says who that is (§9). It is a Reply-To and never a From: sending as somebody
            // else's address is how a domain loses its reputation.
            // `replyAddress` and not `replyTo`: `Mailable` has a public method of that name,
            // and a private one beside it is a fatal error at class load — the whole run, not
            // one test.
            replyTo: array_filter([$this->replyAddress()]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'webx-inbox::mail.submission',
            with: [
                'submission' => $this->submission,
                'form' => $this->submission->form,
                'values' => $this->submission->values,
                'files' => $this->submission->files,
                'meta' => $this->submission->meta ?? [],
                'url' => $this->panelUrl(),
            ],
        );
    }

    private function replyAddress(): ?Address
    {
        $name = $this->submission->form->option('email_field');

        if (! is_string($name) || $name === '') {
            return null;
        }

        $answer = $this->submission->value($name);

        if ($answer === null) {
            return null;
        }

        $address = (string) $answer->value;

        return filter_var($address, FILTER_VALIDATE_EMAIL) === false ? null : new Address($address);
    }

    /** Where the submission is in the panel, for the one button the letter has. */
    private function panelUrl(): string
    {
        $path = trim((string) config('webx-admin.path', 'cms'), '/');

        return url("{$path}/inbox/submissions/{$this->submission->getKey()}");
    }
}
