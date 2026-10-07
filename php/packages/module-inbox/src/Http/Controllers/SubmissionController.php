<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Support\Authors;
use WebxUi\Inbox\Exceptions\NobodyToNotify;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Http\Resources\SubmissionResource;
use WebxUi\Inbox\Http\Resources\SubmissionRowResource;
use WebxUi\Inbox\Mail\Notifier;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Status;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;
use WebxUi\Inbox\Models\SubmissionValue;
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
        $request->validate($list->rules());

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
            // Where on the site this form has been sent from, for the placement filter —
            // carried by the list so that the filter costs no second request.
            'placements' => $list->placements(),
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

    /**
     * The notification once more, to whoever the form names now (§9).
     *
     * For a letter that failed, one stuck in a queue nobody works, or one that went to a spam
     * folder. A form that names nobody is refused out loud rather than answered "done".
     */
    public function notify(Request $request, Submission $submission, Notifier $notifier): JsonResponse
    {
        $submission->loadMissing('form');

        if ($notifier->send($submission, $this->adminId($request)) === 0) {
            throw new NobodyToNotify((string) $submission->form->slug);
        }

        return ApiResponse::data($this->one($request, $submission->refresh()));
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

        // First, because it is the part that can still be refused: a status moved before a
        // correction was turned down would be half of a request that answered 422.
        if (array_key_exists('values', $validated) && is_array($validated['values'])) {
            $this->correct($submission, $validated['values'], $admin);
        }

        if (array_key_exists('status_id', $validated)) {
            $this->moveTo($submission, (int) $validated['status_id'], $admin);
        }

        if (array_key_exists('assignee_id', $validated)) {
            $this->assignTo($submission, $validated['assignee_id'] === null ? null : (int) $validated['assignee_id'], $admin);
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
     * Only the answer is touched: the snapshot beside it says what was asked and in what shape,
     * and rewriting that would turn a correction into a forgery. That shape is also what the
     * correction is checked against — an e-mail answer stays an address, a date a date, a
     * choice one of the choices — and every change is a line in the log with who made it and
     * what it was before. Answers whose field is not in what was sent are left alone, so a
     * form can be corrected one value at a time; a name the submission has no answer under is
     * refused rather than ignored, because it is a typo the caller would never hear about.
     *
     * All or nothing: everything is checked before anything is written.
     *
     * @param  array<string, mixed>  $values  machine name → the corrected answer
     */
    private function correct(Submission $submission, array $values, ?int $admin): void
    {
        $answers = $submission->values->keyBy('name');
        $changes = [];
        $errors = [];

        foreach ($values as $name => $sent) {
            $answer = $answers->get((string) $name);

            if (! $answer instanceof SubmissionValue) {
                $errors['values.'.$name] = [(string) trans('webx-inbox::errors.no-such-answer')];

                continue;
            }

            $corrected = $this->corrected($answer, $sent, 'values.'.$name);

            if (isset($corrected['error'])) {
                $errors['values.'.$name] = [$corrected['error']];

                continue;
            }

            $changes[] = [$answer, $corrected];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($changes as [$answer, [$text, $payload]]) {
            if ($answer->value === $text && $answer->payload === $payload) {
                continue;
            }

            $was = $answer->readable();
            $answer->forceFill(['value' => $text, 'payload' => $payload])->save();
            $submission->log(SubmissionEvent::VALUE, $was, $answer->readable(), $admin, $answer->name);
        }

        $submission->unsetRelation('values');
        $submission->unsetRelation('events');
    }

    /**
     * One corrected answer as it will be stored — the text and the payload beside it — or why
     * it cannot be.
     *
     * @return array{0: string|null, 1: array<int|string, mixed>|null}|array{error: string}
     */
    private function corrected(SubmissionValue $answer, mixed $sent, string $key): array
    {
        $type = FieldType::tryFrom($answer->type);

        // A file is bytes on a disk and a consent is a box the visitor ticked: neither is a
        // typo somebody in the office fixes.
        if ($type === FieldType::File || $type === FieldType::Consent) {
            return ['error' => (string) trans('webx-inbox::errors.not-correctable')];
        }

        if ($sent === null || $sent === '' || $sent === []) {
            return [null, null];
        }

        $field = $answer->field;

        if ($type !== null && $type->hasChoices() && $field instanceof Field) {
            return $this->chosen($field, $sent, $key);
        }

        $rules = match ($type) {
            FieldType::Email => ['string', 'email:rfc', $this->rules->reachableDomain(...), 'max:255'],
            FieldType::Date => ['string', 'date_format:Y-m-d'],
            FieldType::Tel => ['string', 'max:64'],
            default => ['string', 'max:20000'],
        };

        // Under a flat key: `values.email` would be read as a path into an array that is not
        // there, and the rules would pass over a value they never found.
        $check = $this->validator->make(
            ['value' => is_scalar($sent) ? (string) $sent : $sent],
            ['value' => $rules],
            [],
            ['value' => (string) ($answer->label ?: $key)],
        );

        if ($check->fails()) {
            return ['error' => (string) $check->errors()->first('value')];
        }

        return [(string) $sent, null];
    }

    /**
     * A list answer, corrected to one of the choices the field offers — by its value or by
     * the label the panel shows. The text becomes the label and the payload the values, the
     * same two the intake writes, so the payload never goes on naming the old choice.
     *
     * @return array{0: string, 1: list<string>}|array{error: string}
     */
    private function chosen(Field $field, mixed $sent, string $key): array
    {
        $choices = $field->choices();
        $picked = [];

        foreach ($field->isMultiple() && is_array($sent) ? $sent : [$sent] as $one) {
            if (! is_scalar($one)) {
                return ['error' => (string) trans('validation.in', ['attribute' => (string) $field->title ?: $key])];
            }

            $one = (string) $one;
            $value = array_key_exists($one, $choices) ? $one : array_search($one, $choices, true);

            if ($value === false) {
                return ['error' => (string) trans('validation.in', ['attribute' => (string) $field->title ?: $key])];
            }

            $picked[] = (string) $value;
        }

        return [implode(', ', array_map(static fn (string $value): string => $choices[$value], $picked)), $picked];
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
