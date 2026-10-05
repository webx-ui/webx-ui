<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\SentMessage;
use Illuminate\Queue\SerializesModels;
use Throwable;
use WebxUi\Inbox\Models\Submission;

/**
 * What a recipient gets when a submission arrives (§9).
 *
 * `ShouldQueue` and nothing else: a site with a queue gets one, a site on `sync` — which is
 * every fresh Laravel — sends it inline and notices nothing but the wait. Either way the
 * submission is already in the database by the time this exists (§2.10).
 *
 * Through a real queue, pushing the job is not sending the letter: the SMTP server is asked
 * later, in a worker, and only the letter itself is there to say how that went. Hence
 * `send()` and `failed()` below report to {@see Delivery}.
 *
 * The view is published like any other, so a site that wants its own letterhead has one
 * without this package knowing.
 */
class SubmissionReceived extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Who this letter is for, when it went through a real queue and has to say how it went.
     *
     * Null on `sync`, where {@see Notifier} hears the outcome from the mailer itself — reporting
     * from here too would write every letter down twice.
     */
    public ?string $reportFor = null;

    public function __construct(public readonly Submission $submission) {}

    /** Ask the letter to tell {@see Delivery} how it went, once a worker has tried it. */
    public function reportingFor(string $address): static
    {
        $this->reportFor = $address;

        return $this;
    }

    /**
     * Sent: the worker got this far without the transport throwing.
     *
     * @param  MailFactory|Mailer  $mailer
     */
    public function send($mailer): ?SentMessage
    {
        $sent = parent::send($mailer);

        if ($this->reportFor !== null) {
            // A `MessageSending` listener may stop a letter; that is not one that left.
            $sent === null
                ? app(Delivery::class)->failed((int) $this->submission->getKey(), $this->reportFor, 'The letter was stopped before it was sent.')
                : app(Delivery::class)->delivered((int) $this->submission->getKey(), $this->reportFor);
        }

        return $sent;
    }

    /**
     * The queue has given up on it: every try is spent. Called by the queued job, and never
     * between two tries — a letter that fails once and goes out on the second try is delivered.
     */
    public function failed(Throwable $exception): void
    {
        if ($this->reportFor !== null) {
            app(Delivery::class)->failed((int) $this->submission->getKey(), $this->reportFor, $exception->getMessage());
        }
    }

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
