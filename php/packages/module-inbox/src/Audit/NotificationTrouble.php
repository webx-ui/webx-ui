<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Audit;

use Illuminate\Support\Carbon;
use WebxUi\Audit\Checks\AuditContext;
use WebxUi\Audit\Checks\Finding;
use WebxUi\Audit\Checks\ModuleCheck;
use WebxUi\Audit\Checks\Severity;
use WebxUi\Inbox\Models\Submission;

/**
 * Letters about submissions that did not leave (§9).
 *
 * Two findings. *Failed*: the mailer or the queue gave up — wrong mail settings, or a worker
 * still holding the ones it started with. *Queued for long*: the jobs were pushed and nobody
 * took them — no worker runs at all. Either way the people the form names have not heard of
 * enquiries the panel already holds, and nothing on the site says so.
 */
final class NotificationTrouble extends ModuleCheck
{
    /** How many submissions the table lists; the count says how many there are. */
    private const ROWS = 20;

    protected const ID = 'inbox.notification';

    protected const GROUP = 'inbox';

    protected const SEVERITY = Severity::ERROR;

    protected const NEEDS = ['database'];

    protected const NAMESPACE = 'webx-inbox';

    public function run(AuditContext $context): iterable
    {
        return $this->findings(
            $context->threshold('inbox_queued_minutes', 30),
            $context->threshold('inbox_failed_days', 30),
        );
    }

    /**
     * @return list<Finding>
     */
    public function findings(int $queuedMinutes, int $failedDays): array
    {
        $findings = [];

        $failed = Submission::query()
            ->with('form')
            ->whereNotNull('notify_error')
            ->where('created_at', '>=', Carbon::now()->subDays($failedDays))
            ->latest('id');

        $count = $failed->count();

        if ($count > 0) {
            $findings[] = $this->found('notify-failed', ['count' => $count, 'days' => $failedDays], key: 'failed', table: [
                'columns' => [Finding::column('record'), Finding::column('error'), Finding::column('edit', 'edit')],
                'rows' => $failed->limit(self::ROWS)->get()->map($this->row(...))->all(),
            ]);
        }

        $stuck = Submission::query()
            ->with('form')
            ->whereNull('notify_error')
            ->where('notify_queued_at', '<', Carbon::now()->subMinutes($queuedMinutes))
            ->oldest('notify_queued_at');

        $count = $stuck->count();

        if ($count > 0) {
            $findings[] = $this->found('notify-queued', ['count' => $count, 'minutes' => $queuedMinutes], severity: Severity::WARNING, key: 'queued', table: [
                'columns' => [Finding::column('record'), Finding::column('value'), Finding::column('edit', 'edit')],
                'rows' => $stuck->limit(self::ROWS)->get()->map(fn (Submission $submission): array => [
                    ...$this->row($submission),
                    'value' => $submission->notify_queued_at?->toAtomString(),
                ])->all(),
            ]);
        }

        return $findings;
    }

    /**
     * @return array<string, string|null>
     */
    private function row(Submission $submission): array
    {
        return [
            'record' => ($submission->form->slug ?? '?').' #'.$submission->getKey(),
            'error' => $submission->notify_error,
            'edit' => '/inbox/submissions/'.$submission->getKey(),
        ];
    }
}
