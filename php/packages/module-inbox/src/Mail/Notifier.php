<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Mail;

use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Queue\SyncQueue;
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
 *
 * Two ways a letter goes. On `sync` the mailer sends it inline and answers with the outcome,
 * which is written down here. On a real queue the mailer only pushes a job: the submission is
 * marked queued, and each letter reports to {@see Delivery} once a worker has sent it or given
 * up — a pushed job is not a sent letter, and the submission must not say it is.
 */
final class Notifier
{
    public function __construct(
        private readonly MailFactory $mailer,
        private readonly QueueFactory $queue,
        private readonly Delivery $delivery,
        private readonly Locales $locales,
        private readonly LoggerInterface $log,
    ) {}

    /**
     * Write to everybody the form names; the number of letters attempted, 0 for nobody.
     *
     * @param  int|null  $adminId  who asked for it again, from the panel or through MCP
     */
    public function send(Submission $submission, ?int $adminId = null): int
    {
        $recipients = $this->recipients($submission);

        if ($recipients === []) {
            // Nothing to send is not a failure — a form read only in the panel works — but it
            // must not look like a letter still waiting in the queue either: the log says the
            // form names nobody who would receive one, and `notified_at` stays empty because
            // nobody was.
            $submission->log(SubmissionEvent::NO_RECIPIENTS, null, null, $adminId);

            return 0;
        }

        if ($this->queued(new SubmissionReceived($submission))) {
            $this->enqueue($submission, $recipients, $adminId);
        } else {
            $this->sendNow($submission, $recipients, $adminId);
        }

        return count($recipients);
    }

    /**
     * Whether a letter would go through a worker rather than inline.
     *
     * Asked the way `Mailable::queue()` picks its connection. The exact class and not
     * `instanceof`: the deferred and background drivers extend `SyncQueue` and still run the
     * job after the response, when nobody here is listening any more.
     */
    public function queued(SubmissionReceived $mail): bool
    {
        $connection = $mail->connection;

        if ($connection === null && method_exists($this->queue, 'resolveConnectionFromQueueRoute')) {
            $connection = $this->queue->resolveConnectionFromQueueRoute($mail);
        }

        return $this->queue->connection($connection)::class !== SyncQueue::class;
    }

    /**
     * Who is written to, and in what language.
     *
     * An administrator's address comes from their account rather than from the form, so
     * changing it in one place changes it everywhere; a form that names somebody who has since
     * been deleted or switched off simply has one recipient fewer — {@see Recipients} says
     * which, for the panel, MCP and the audit to show. An address named twice — an administrator
     * also typed in by hand — gets one letter.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function recipients(Submission $submission): array
    {
        $recipients = [];
        $siteLocale = $this->siteLocale($submission);

        foreach (Recipients::resolve($submission->form) as $recipient) {
            if (! $recipient['receives'] || $recipient['email'] === null) {
                continue;
            }

            $admin = $recipient['admin'] ?? null;

            $recipients[strtolower($recipient['email'])] ??= [
                $recipient['email'],
                $admin instanceof CmsUser ? $this->locales->resolvePanel($admin->panelLocale()) : $siteLocale,
            ];
        }

        return array_values($recipients);
    }

    /**
     * The inline way: the outcome is known as soon as the mailer answers.
     *
     * @param  list<array{0: string, 1: string}>  $recipients
     */
    private function sendNow(Submission $submission, array $recipients, ?int $adminId): void
    {
        $sent = 0;
        $error = null;
        $outcomes = [];

        foreach ($recipients as [$address, $locale]) {
            try {
                $this->mailer->mailer()
                    ->to($address)
                    ->locale($locale)
                    ->send(new SubmissionReceived($submission));

                $sent++;
                $outcomes[] = $this->outcome($address, Delivery::DELIVERED, null);
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
                $outcomes[] = $this->outcome($address, Delivery::FAILED, $error);

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
            'notify_queued_at' => null,
            'notify_recipients' => $outcomes,
        ])->save();

        if ($sent > 0) {
            $submission->log(SubmissionEvent::NOTIFIED, null, (string) $sent, $adminId);
        }
    }

    /**
     * The queued way: marked first and pushed after, so a quick worker finds the mark.
     *
     * A push that throws — the queue's own database or Redis is down, the mailer is not
     * configured — is a letter that never reached the queue, and it is failed on the spot.
     * The "queued" line is written after the pushes and counts only the letters that made it:
     * written before, it read "queued" beside "failed" at the same second for a letter that
     * was never anywhere near a queue.
     *
     * @param  list<array{0: string, 1: string}>  $recipients
     */
    private function enqueue(Submission $submission, array $recipients, ?int $adminId): void
    {
        $this->delivery->queued($submission, array_map(static fn (array $one): string => $one[0], $recipients));

        $pushed = 0;

        foreach ($recipients as [$address, $locale]) {
            try {
                $this->mailer->mailer()
                    ->to($address)
                    ->locale($locale)
                    ->send((new SubmissionReceived($submission))->reportingFor($address));

                $pushed++;
            } catch (Throwable $exception) {
                $this->delivery->failed((int) $submission->getKey(), $address, $exception->getMessage());
            }
        }

        if ($pushed > 0) {
            $submission->log(SubmissionEvent::NOTIFY_QUEUED, null, (string) $pushed, $adminId);
        }

        $submission->refresh();
    }

    /**
     * @return array{address: string, state: string, error: string|null, at: string}
     */
    private function outcome(string $address, string $state, ?string $error): array
    {
        return ['address' => $address, 'state' => $state, 'error' => $error, 'at' => Carbon::now()->toAtomString()];
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
