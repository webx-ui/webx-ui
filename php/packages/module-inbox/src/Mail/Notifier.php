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
     * been deleted simply has one recipient fewer.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function recipients(Submission $submission): array
    {
        $recipients = [];
        $siteLocale = $this->siteLocale($submission);

        foreach ($submission->form->recipients() as $recipient) {
            if (isset($recipient['admin_id'])) {
                $admin = CmsUser::query()->find($recipient['admin_id']);

                if ($admin === null || ! $admin->is_active) {
                    continue;
                }

                $recipients[] = [$admin->email, $this->locales->resolvePanel($admin->panelLocale())];

                continue;
            }

            $email = $recipient['email'] ?? null;

            if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $recipients[] = [$email, $siteLocale];
            }
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
