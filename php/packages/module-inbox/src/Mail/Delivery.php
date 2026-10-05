<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Mail;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Psr\Log\LoggerInterface;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;

/**
 * What became of the letters about one submission, once they went through a real queue (§9).
 *
 * On `sync` the mailer answers with the outcome and {@see Notifier} writes it down itself. On a
 * queue the mailer only answers "pushed", and what happened is known later and elsewhere — in a
 * worker, one job per letter, possibly two workers at once. So the submission is marked
 * *queued* before the jobs are pushed, and each job reports back here when its letter has left
 * or the queue has given up on it.
 *
 * Every report reads and writes the row under a lock: two letters settling at the same moment
 * must not each write their own copy of the list of recipients over the other's.
 */
final class Delivery
{
    public const QUEUED = 'queued';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly LoggerInterface $log,
    ) {}

    /**
     * Before the jobs are pushed: a worker that is quick enough must find this already written.
     *
     * A new attempt starts clean — the last one's error and delivery time belong to letters
     * that are no longer the ones in question.
     *
     * @param  list<string>  $addresses
     */
    public function queued(Submission $submission, array $addresses, ?int $adminId = null): void
    {
        $now = Carbon::now();

        $submission->forceFill([
            'notified_at' => null,
            'notify_error' => null,
            'notify_queued_at' => $now,
            'notify_recipients' => array_map(static fn (string $address): array => [
                'address' => $address,
                'state' => self::QUEUED,
                'error' => null,
                'at' => $now->toAtomString(),
            ], $addresses),
        ])->save();

        $submission->log(SubmissionEvent::NOTIFY_QUEUED, null, (string) count($addresses), $adminId);
    }

    public function delivered(int $submissionId, string $address): void
    {
        $this->settle($submissionId, $address, self::DELIVERED, null);
    }

    public function failed(int $submissionId, string $address, string $error): void
    {
        $this->log->error('webx-inbox: could not notify {address} about submission {id}.', [
            'address' => $address,
            'id' => $submissionId,
            'error' => $error,
        ]);

        $this->settle($submissionId, $address, self::FAILED, $error);
    }

    private function settle(int $submissionId, string $address, string $state, ?string $error): void
    {
        $this->db->transaction(function () use ($submissionId, $address, $state, $error): void {
            $submission = Submission::query()->lockForUpdate()->find($submissionId);

            // Deleted while its letters were in the queue: there is nobody left to tell.
            if (! $submission instanceof Submission) {
                return;
            }

            $now = Carbon::now();
            $recipients = array_values($submission->notify_recipients ?? []);
            $found = false;

            foreach ($recipients as $index => $recipient) {
                // Only a letter still waiting: a job run twice, or an attempt started over in
                // the meantime, must not rewrite a letter that has already been settled.
                if ($recipient['address'] === $address && $recipient['state'] === self::QUEUED) {
                    $recipients[$index] = ['address' => $address, 'state' => $state, 'error' => $error, 'at' => $now->toAtomString()];
                    $found = true;

                    break;
                }
            }

            if (! $found) {
                return;
            }

            $waiting = array_filter($recipients, static fn (array $one): bool => $one['state'] === self::QUEUED);
            $attributes = ['notify_recipients' => $recipients];

            if ($state === self::DELIVERED) {
                $attributes['notified_at'] = $now;
            } else {
                // The last error, as on `sync`; each letter keeps its own in the list.
                $attributes['notify_error'] = $error;
            }

            if ($waiting === []) {
                $attributes['notify_queued_at'] = null;
            }

            $submission->forceFill($attributes)->save();

            if ($state === self::FAILED) {
                $submission->log(SubmissionEvent::NOTIFY_FAILED, null, $address);
            }

            if ($waiting === []) {
                $sent = count(array_filter($recipients, static fn (array $one): bool => $one['state'] === self::DELIVERED));

                if ($sent > 0) {
                    $submission->log(SubmissionEvent::NOTIFIED, null, (string) $sent);
                }
            }
        });
    }
}
