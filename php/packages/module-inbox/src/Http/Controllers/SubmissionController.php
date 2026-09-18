<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Support\Authors;
use WebxUi\Inbox\Http\Resources\SubmissionResource;
use WebxUi\Inbox\Http\Resources\SubmissionRowResource;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Status;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;
use WebxUi\Inbox\Submissions\Intake;
use WebxUi\Inbox\Submissions\ListQuery;
use WebxUi\Inbox\Submissions\Rules;

/**
 * What has come in through one form, and one of it opened (§11, §12).
 *
 * The list describes itself: the columns travel with the rows, because they are the form's own
 * `in_table` fields and the browser would otherwise have to fetch the form to know what it is
 * looking at. The counts travel too, for the tabs above it.
 */
final class SubmissionController
{
    public function __construct(
        private readonly Rules $rules,
        private readonly Intake $intake,
        private readonly ValidationFactory $validator,
    ) {}

    public function index(Request $request, Form $form): JsonResponse
    {
        $list = ListQuery::for($form);

        $page = $list->build($request)->paginate(
            min(100, max(5, (int) $request->integer('per_page', ListQuery::PER_PAGE))),
        );

        // Written out rather than handed to `JsonResource::collection()`, because every row
        // needs the columns and a resource collection has no way to pass a constructor
        // argument down to the resources it makes.
        $page->through(fn (Submission $submission): array => (new SubmissionRowResource($submission, $list->columns))->resolve($request));

        return new JsonResponse([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            // The list says what it is: the columns are the form's own `in_table` fields, and
            // without them the browser would have to fetch the form to know what it is drawing.
            'columns' => array_map($this->column(...), $list->columns),
            'counts' => $list->counts($request),
        ]);
    }

    public function show(Request $request, Submission $submission): JsonResponse
    {
        $submission->markRead();

        return ApiResponse::data($this->one($request, $submission));
    }

    /**
     * A submission typed in by hand — a call that came by telephone, a form filled in on paper.
     *
     * Through the same intake as the public door, so the same rules apply, the same snapshot
     * is written and the same log line appears: the only difference is the source, which is
     * what tells the two apart afterwards.
     */
    public function store(Request $request, Form $form): JsonResponse
    {
        $validated = $this->validator->make(
            $request->all(),
            $this->rules->for($form),
            [],
            $this->rules->attributes($form),
        )->validate();

        $values = $validated['fields'] ?? [];

        $submission = $this->intake->receive(
            $form,
            is_array($values) ? $values : [],
            $request,
            Submission::SOURCE_PANEL,
        );

        // Somebody who typed it in has read it. Otherwise it arrives in its own list as
        // unread, which is a badge only its author could clear.
        $submission->markRead();

        return ApiResponse::data($this->one($request, $submission), 201);
    }

    /** The status, the assignee, and a typo in an answer. */
    public function update(Request $request, Submission $submission): JsonResponse
    {
        $validated = $request->validate([
            'status_id' => ['sometimes', 'integer', Rule::exists('inbox_statuses', 'id')],
            'assignee_id' => ['sometimes', 'nullable', 'integer', Rule::exists('cms_users', 'id')],
            'values' => ['sometimes', 'array'],
        ]);

        $admin = $this->adminId($request);

        if (array_key_exists('status_id', $validated)) {
            $this->moveTo($submission, (int) $validated['status_id'], $admin);
        }

        if (array_key_exists('assignee_id', $validated)) {
            $this->assignTo($submission, $validated['assignee_id'] === null ? null : (int) $validated['assignee_id'], $admin);
        }

        if (array_key_exists('values', $validated) && is_array($validated['values'])) {
            $this->correct($submission, $validated['values']);
        }

        return ApiResponse::data($this->one($request, $submission->refresh()));
    }

    public function destroy(Submission $submission): JsonResponse
    {
        // Through the model, not the query builder: the files on the disk go with it, and the
        // database cascade would leave them behind without raising a single event (§8).
        $submission->delete();

        return ApiResponse::noContent();
    }

    /** One submission, with its neighbours in whatever list it was opened from. */
    private function one(Request $request, Submission $submission): SubmissionResource
    {
        $submission->load(['form', 'status', 'assignee', 'values', 'files', 'events']);

        $around = $submission->form === null
            ? null
            : ListQuery::for($submission->form)->neighbours($request, $submission);

        return new SubmissionResource(
            $submission,
            Authors::names($request->user(), $submission->events->pluck('admin_id')->all()),
            $around,
        );
    }

    /**
     * The status changes, and the log says what it was before.
     *
     * By key and not by id, because the log is read months later, when the status may have
     * been renamed and the row it pointed at may be gone.
     */
    private function moveTo(Submission $submission, int $statusId, ?int $admin): void
    {
        if ((int) $submission->status_id === $statusId) {
            return;
        }

        $was = $submission->status?->key;
        $status = Status::query()->findOrFail($statusId);

        $submission->forceFill(['status_id' => $status->getKey()])->save();
        $submission->log(SubmissionEvent::STATUS, $was, $status->key, $admin);
        $submission->setRelation('status', $status);
    }

    private function assignTo(Submission $submission, ?int $assigneeId, ?int $admin): void
    {
        if ($submission->assignee_id === $assigneeId) {
            return;
        }

        $was = $submission->assignee?->name;

        $submission->forceFill(['assignee_id' => $assigneeId])->save();
        $submission->unsetRelation('assignee')->load('assignee');

        $submission->log(SubmissionEvent::ASSIGNEE, $was, $submission->assignee?->name, $admin);
    }

    /**
     * A correction to what arrived — a telephone number with a digit missing, a name spelled
     * from a bad line.
     *
     * Only the text is touched: the snapshot beside it says what was asked and in what shape,
     * and rewriting that would turn a correction into a forgery. Answers whose field is not in
     * what was sent are left alone, so a form can be corrected one value at a time.
     *
     * @param  array<string, mixed>  $values  machine name → the corrected text
     */
    private function correct(Submission $submission, array $values): void
    {
        foreach ($submission->values as $value) {
            if (! array_key_exists($value->name, $values)) {
                continue;
            }

            $text = $values[$value->name];

            if (! is_scalar($text) && $text !== null) {
                continue;
            }

            $value->forceFill(['value' => $text === null ? null : (string) $text])->save();
        }

        $submission->unsetRelation('values');
    }

    /**
     * One column of the list, as the table will draw it.
     *
     * @return array<string, mixed>
     */
    private function column(Field $field): array
    {
        return [
            'key' => $field->key(),
            'label' => (string) $field->title,
            'type' => $field->type->value,
        ];
    }

    private function adminId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }
}
