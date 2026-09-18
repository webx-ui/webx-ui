<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Inbox\Models\Status;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;

/**
 * A pile of submissions dealt with at once: moved to a status, marked read, or thrown away.
 *
 * Row by row rather than in one `update`, deliberately. A mass update would not write the log
 * and a mass delete would leave the attachments on the disk — the database cascade raises no
 * model events — so the cheap version of this is the one that quietly loses things. A hundred
 * rows is the most anybody selects, and a hundred small writes is not a problem worth a bug.
 */
final class SubmissionMassController
{
    /** As many as one selection may carry. Past this it is a filter, not a selection. */
    private const LIMIT = 200;

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'max:'.self::LIMIT],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['status', 'read', 'unread', 'delete'])],
            'status_id' => ['required_if:action,status', 'integer', Rule::exists('inbox_statuses', 'id')],
        ]);

        /** @var list<int> $ids */
        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));
        $action = (string) $validated['action'];

        $admin = $request->user()?->getAuthIdentifier();
        $admin = is_numeric($admin) ? (int) $admin : null;

        $status = $action === 'status'
            ? Status::query()->findOrFail((int) $validated['status_id'])
            : null;

        $submissions = Submission::query()->with('status')->whereKey($ids)->get();

        foreach ($submissions as $submission) {
            match ($action) {
                'status' => $this->moveTo($submission, $status, $admin),
                'read' => $submission->markRead(),
                'unread' => $submission->forceFill(['read_at' => null])->save(),
                default => $submission->delete(),
            };
        }

        return ApiResponse::data(['count' => $submissions->count()]);
    }

    private function moveTo(Submission $submission, ?Status $status, ?int $admin): void
    {
        if ($status === null || (int) $submission->status_id === (int) $status->getKey()) {
            return;
        }

        $was = $submission->status?->key;

        $submission->forceFill(['status_id' => $status->getKey()])->save();
        $submission->log(SubmissionEvent::STATUS, $was, $status->key, $admin);
    }
}
