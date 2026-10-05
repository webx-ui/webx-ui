<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Mail;

use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Support\Carbon;
use Psr\Log\LoggerInterface;
use Throwable;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;
use WebxUi\Localization\Locales;

/**
 * Telling the people named on the form that something arrived.
 *
 * This is the first thing in the whole ecosystem that sends mail, which is why the failure
 * mode was decided before the feature: a submission is already saved when this runs, and
 * everything that goes wrong here becomes a mark on it (§2.10). An enquiry that reached the
 * database and not the inbox is a nuisance; a 500 to the visitor is a lost customer.
 *
 * Each recipient is written to separately because each reads in their own language: an
 * administrator in the language they keep the panel in, an address typed into the form in the
 * language the submission arrived in (§9).
 */
final class Notifier
{
    public function __construct(
        private readonly MailFactory $mailer,
        private readonly Locales $locales,
        private readonly LoggerInterface $log,
    ) {}

    public function send(Submission $submission): void
    {
        $recipients = $this->recipients($submission);

        if ($recipients === []) {
            // Nothing to send is not a failure — a form read only in the panel works — but it
            // must not look like a letter still waiting in the queue either: the log says the
            // form names nobody who would receive one, and `notified_at` stays empty because
            // nobody was.
            $submission->log(SubmissionEvent::NO_RECIPIENTS);

            return;
        }

        $sent = 0;
        $error = null;

        foreach ($recipients as [$address, $locale]) {
            try {
                $this->mailer->mailer()
                    ->to($address)
                    ->locale($locale)
                    ->send(new SubmissionReceived($submission));

                $sent++;
            } catch (Throwable $exception) {
                $error = $exception->getMessage();

                $this->log->error('webx-inbox: could not notify {address} about submission {id}.', [
                    'address' => $address,
                    'id' => $submission->getKey(),
                    'error' => $error,
                ]);
            }
        }

        $submission->forceFill([
            // Set even when some of the letters failed: it says an attempt was made and when,
            // and the error beside it says how it went.
            'notified_at' => Carbon::now(),
            'notify_error' => $error,
        ])->save();

        if ($sent > 0) {
            $submission->log(SubmissionEvent::NOTIFIED, null, (string) $sent);
        }
    }

    /**
     * Who is written to, and in what language.
     *
     * An administrator's address comes from their account rather than from the form, so
     * changing it in one place changes it everywhere; a form that names somebody who has since
     * been deleted or switched off simply has one recipient fewer — {@see Recipients} says
     * which, for the panel, MCP and the audit to show.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function recipients(Submission $submission): array
    {
        $recipients = [];
        $siteLocale = $this->siteLocale($submission);

        foreach (Recipients::resolve($submission->form) as $recipient) {
            if (! $recipient['receives'] || $recipient['email'] === null) {
                continue;
            }

            $admin = $recipient['admin'] ?? null;

            $recipients[] = [
                $recipient['email'],
                $admin instanceof CmsUser ? $this->locales->resolvePanel($admin->panelLocale()) : $siteLocale,
            ];
        }

        return $recipients;
    }

    /** The language the submission was sent in, which is the site's if it did not say. */
    private function siteLocale(Submission $submission): string
    {
        $locale = ($submission->meta ?? [])['locale'] ?? null;

        return is_string($locale) && $this->locales->has($locale)
            ? $locale
            : $this->locales->defaultCode();
    }
}
