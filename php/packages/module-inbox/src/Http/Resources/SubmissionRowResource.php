<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Submissions\ListQuery;

/**
 * One line of the list.
 *
 * The answers arrive under `values`, keyed by the field's machine name, because that is what
 * the table's columns are keyed by — `values.email` is a path the table already knows how to
 * walk, so the browser needs no second mapping between a column and the row it draws.
 *
 * Deliberately thin: everything that is only worth reading once the submission is open — the
 * metadata, the log, the payload of a multiple answer — belongs to {@see SubmissionResource}
 * and would otherwise be carried twenty-five times a page for nobody.
 *
 * @mixin Submission
 */
final class SubmissionRowResource extends JsonResource
{
    /**
     * @param  list<Field>  $columns  the `in_table` fields whose answers the list carries
     */
    public function __construct(Submission $submission, private readonly array $columns = [])
    {
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
            'values' => $this->values($submission),
            'status' => $submission->relationLoaded('status') && $submission->status !== null
                ? new StatusResource($submission->status)
                : null,
            'assignee' => AdminBrief::of($submission->assignee),
            'is_read' => $submission->read_at !== null,
            'source' => $submission->source,
            'files_count' => (int) ($submission->getAttribute('files_count') ?? 0),
            'created_at' => $submission->created_at?->toAtomString(),
        ];
    }

    /**
     * The answers the list has columns for, by name.
     *
     * Read off the row rather than out of a relation: they came back as subqueries, one column
     * each, so a page of twenty-five costs one query and not twenty-six.
     *
     * @return array<string, string|null>
     */
    private function values(Submission $submission): array
    {
        $values = [];

        foreach ($this->columns as $field) {
            $value = $submission->getAttribute(ListQuery::alias($field));

            $values[$field->key()] = $value === null ? null : (string) $value;
        }

        return $values;
    }
}
