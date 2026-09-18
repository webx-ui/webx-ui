<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;
use WebxUi\Inbox\Models\SubmissionFile;
use WebxUi\Inbox\Models\SubmissionValue;

/**
 * One submission, open.
 *
 * The answers read with the words they were asked in — `label` and `type` are the snapshot
 * written at the time (§2.2), and this resource never goes looking at the field for them: a
 * submission from a year ago has to read as it did, even where the field has since been
 * renamed or thrown away.
 *
 * @mixin Submission
 */
final class SubmissionResource extends JsonResource
{
    /**
     * @param  array<int, string>  $authors  admin id → name, for the log
     * @param  array{previous: int|null, next: int|null}|null  $around  the neighbours in the list
     */
    public function __construct(
        Submission $submission,
        private readonly array $authors = [],
        private readonly ?array $around = null,
    ) {
        parent::__construct($submission);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Submission $submission */
        $submission = $this->resource;

        return [
            'id' => (int) $submission->getKey(),
            'form' => [
                'id' => (int) $submission->form_id,
                'slug' => $submission->form?->slug,
                'title' => $submission->form?->getTranslations('title'),
            ],
            'status' => $submission->status === null ? null : new StatusResource($submission->status),
            'assignee' => AdminBrief::of($submission->assignee),
            'values' => $submission->values->map($this->value(...))->values()->all(),
            'files' => $submission->files->map($this->file(...))->values()->all(),
            'meta' => $submission->meta ?? [],
            'events' => $submission->events->map($this->event(...))->values()->all(),
            'is_read' => $submission->read_at !== null,
            'source' => $submission->source,
            'notified_at' => $submission->notified_at?->toAtomString(),
            'notify_error' => $submission->notify_error,
            // Which submission is before and after this one *in the list it was opened from*,
            // so the arrows in its head walk the same filter the reader was looking at.
            'previous_id' => $this->around['previous'] ?? null,
            'next_id' => $this->around['next'] ?? null,
            'created_at' => $submission->created_at?->toAtomString(),
            'updated_at' => $submission->updated_at?->toAtomString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function value(SubmissionValue $value): array
    {
        return [
            'id' => (int) $value->getKey(),
            'field_id' => $value->field_id,
            'name' => $value->name,
            'label' => $value->label,
            'type' => $value->type,
            'value' => $value->value,
            'payload' => $value->payload,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function file(SubmissionFile $file): array
    {
        return [
            'id' => (int) $file->getKey(),
            'field_id' => $file->field_id,
            'name' => $file->name,
            'size' => $file->size,
            'mime' => $file->mime,
            // Through the panel, never straight at the disk (§8): the bytes are behind the
            // same permission as the submission, for as long as the reader has it.
            'url' => route('webx.inbox.files.show', [
                'submission' => $file->submission_id,
                'file' => $file->getKey(),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function event(SubmissionEvent $event): array
    {
        return [
            'id' => (int) $event->getKey(),
            'type' => $event->type,
            'from' => $event->from,
            'to' => $event->to,
            // Null is the system — the submission arriving, the notification going out.
            'author' => $event->admin_id === null ? null : [
                'id' => $event->admin_id,
                'name' => $this->authors[$event->admin_id] ?? null,
            ],
            'created_at' => $event->created_at?->toAtomString(),
        ];
    }
}
