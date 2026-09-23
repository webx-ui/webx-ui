<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Mcp;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Notes\Note;
use WebxUi\Admin\Support\Authors;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Inbox\Exceptions\InboxException;
use WebxUi\Inbox\Http\Controllers\FormController;
use WebxUi\Inbox\Http\Resources\FieldResource;
use WebxUi\Inbox\Http\Resources\FormResource;
use WebxUi\Inbox\Http\Resources\StatusResource;
use WebxUi\Inbox\Http\Resources\SubmissionResource;
use WebxUi\Inbox\Http\Resources\SubmissionRowResource;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Status;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;
use WebxUi\Inbox\Panel\FieldInput;
use WebxUi\Inbox\Panel\FormInput;
use WebxUi\Inbox\Submissions\ListQuery;
use WebxUi\Mcp\Exceptions\ToolFailure;
use WebxUi\Mcp\Tool;

/**
 * What an agent can do with the forms of a site and what came in through them (§14).
 *
 * The same doors the panel uses: the rules of {@see FormInput} and {@see FieldInput} decide
 * what a form may be, {@see ListQuery} builds the list with the same filters and the same
 * subqueries, {@see SubmissionResource} answers with the same shape, and a status change
 * writes the same line in the log. A form an agent saved is a form the panel would have
 * accepted.
 *
 * Two things are deliberately missing. **Receiving a submission** is not here: the intake is a
 * public door with antispam in front of it, and a second way in that skipped both would be the
 * one somebody points at a mailing list (§14). **Deleting a submission** is not here either —
 * it takes the files with it and it is the one act in this module nobody can undo; the panel
 * asks a person, and `webx:inbox:prune` is the deliberate, configured version of it (§15).
 */
final class InboxTools
{
    /** A page of submissions, which is the panel's own default. */
    private const PER_PAGE = 25;

    /** More than a panel has forms, and a stop for the site that disagrees. */
    private const FORM_LIMIT = 200;

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<Tool>
     */
    public function all(): array
    {
        $form = [
            'type' => ['integer', 'string'],
            'description' => 'The form: its id, or its slug — "contact".',
        ];

        return [
            Tool::read(
                'forms_list',
                'Every form this site has: what it is called in each language, the slug it answers at, whether '
                .'it is switched on, how many submissions have come through it and how many nobody has read. '
                .'Read this first — a form is named by its slug everywhere else.',
                fn (array $arguments): array => $this->formsList($arguments),
                ['properties' => [
                    'disabled' => ['type' => 'boolean', 'description' => 'Include the forms that are switched off; true when omitted.'],
                ]],
                permission: ['inbox.view', 'inbox.manage'],
            ),

            Tool::read(
                'form_get',
                'One form with its questions: the type of each, whether it is required, the answers a list field '
                .'allows, and the name it travels under. This is the shape of the data the public intake expects '
                .'— the answer says where that door is and what a submission has to carry.',
                fn (array $arguments): array => $this->formGet($arguments),
                ['properties' => ['form' => $form], 'required' => ['form']],
                permission: 'inbox.manage',
            ),

            Tool::mutating(
                'form_save',
                'Create a form, or change one — its title, its slug, its settings and its questions in one call. '
                .'A key left out keeps what it had, and so does a field nobody mentioned: send only what changes. '
                .'A field is matched by its id or by its machine name; one that matches nothing is added at the '
                .'end, and one sent with remove: true is put aside, which leaves the answers already given '
                .'through it readable. Recipients that are not addresses and a slug that is not an address are '
                .'refused here exactly as they are in the panel.',
                fn (array $arguments): array => $this->attempt(fn (): array => $this->formSave($arguments)),
                ['properties' => [
                    'form' => $form + ['description' => 'The form to change; omit to create one.'],
                    'slug' => ['type' => 'string', 'description' => 'Where it answers: lower case, words joined by hyphens.'],
                    'title' => ['type' => ['string', 'object'], 'description' => 'One language as a string, or every language as { "en": "…" }.'],
                    'is_enabled' => ['type' => 'boolean', 'description' => 'A form that is off is a 404 to the site and keeps everything it has received.'],
                    'options' => ['type' => 'object', 'description' => 'The settings, as inbox_form_get returns them: recipients, the thank-you, the antispam. Unknown keys are dropped.'],
                    'fields' => [
                        'type' => 'array',
                        'description' => 'The questions to add or change.',
                        'items' => ['type' => 'object', 'properties' => [
                            'id' => ['type' => 'integer', 'description' => 'The field to change; omit for a new one.'],
                            'name' => ['type' => 'string', 'description' => 'The machine name: what the HTML calls it, and what CSV and the intake key it by. Letters, digits, - and _.'],
                            'type' => ['type' => 'string', 'description' => 'text · email · tel · textarea · select · radio · checkbox · consent · date · file · hidden.'],
                            'title' => ['type' => ['string', 'object'], 'description' => 'The label, in one language or in all of them.'],
                            'is_required' => ['type' => 'boolean'],
                            'in_table' => ['type' => 'boolean', 'description' => 'Show this answer as a column of the submission list.'],
                            'options' => ['type' => 'object', 'description' => 'What this type takes: choices for a list, maxlength for text, extensions for a file.'],
                            'remove' => ['type' => 'boolean', 'description' => 'Put the field aside: it leaves the form and stays readable in the submissions that used it.'],
                        ]],
                    ],
                ]],
                permission: 'inbox.manage',
            ),

            Tool::read(
                'list',
                'What has come in through one form, newest first. The same filters the panel has: a status by its '
                .'key, unread only, who it is assigned to, a date range, and a search that looks inside every '
                .'answer and not only the ones that are columns. Spam is left out unless you ask for it by status. '
                .'Each row carries the answers the form marks as columns; inbox_get opens one in full.',
                fn (array $arguments): array => $this->list($arguments),
                ['properties' => [
                    'form' => $form,
                    'view' => ['type' => 'string', 'description' => 'all · unread · the key of a status, as inbox_forms_list and the counts report them.'],
                    'assignee' => ['type' => ['integer', 'string'], 'description' => 'An administrator id, or "none" for the pile nobody has picked up.'],
                    'from' => ['type' => 'string', 'description' => 'On or after this date, YYYY-MM-DD.'],
                    'to' => ['type' => 'string', 'description' => 'On or before this date, YYYY-MM-DD.'],
                    'search' => ['type' => 'string', 'description' => 'Text in any answer, or a submission id.'],
                    'sort' => ['type' => 'string', 'description' => 'created_at · id · status · values.<field name>; a leading - reverses it. Newest first when omitted.'],
                    'page' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer', 'description' => '5 to 100; 25 when omitted.'],
                ], 'required' => ['form']],
                permission: 'inbox.view',
            ),

            Tool::read(
                'get',
                'One submission in full: every answer with the label and the type it was asked under, the files '
                .'with them, what was around it when it arrived — the page, the language, the address — the notes '
                .'administrators have left on it, and the log of everything that has happened to it. Reading it '
                .'does not mark it read: that is a person having looked.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->get($arguments, $user),
                ['properties' => [
                    'submission' => ['type' => 'integer', 'description' => 'The id, as inbox_list reports it.'],
                ], 'required' => ['submission']],
                permission: 'inbox.view',
            ),

            Tool::mutating(
                'set_status',
                'Move a submission along: its status, who is dealing with it, and a note about why — any of the '
                .'three, in one call. Each one that changes writes a line in the log under whoever the token '
                .'belongs to. The status is named by its key rather than its id, because a key is the same thing '
                .'on a site and on its copy.',
                fn (array $arguments, ?Authenticatable $user = null): array => $this->attempt(fn (): array => $this->setStatus($arguments, $user)),
                ['properties' => [
                    'submission' => ['type' => 'integer'],
                    'status' => ['type' => 'string', 'description' => 'The key of a status — new, in-progress, done, spam, as this site names them.'],
                    'assignee' => ['type' => ['integer', 'string', 'null'], 'description' => 'An administrator by id or by email address; null takes it off everybody.'],
                    'note' => ['type' => 'string', 'description' => 'A line for whoever picks this up next.'],
                ], 'required' => ['submission']],
                permission: 'inbox.update',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function formsList(array $arguments): array
    {
        $query = FormController::counted(Form::query()->withCount('fields'));

        if (($arguments['disabled'] ?? true) !== true) {
            $query->enabled();
        }

        $forms = $query->orderBy('position')->orderBy('id')->limit(self::FORM_LIMIT)->get();

        return [
            'count' => $forms->count(),
            'intake' => $this->intake('{slug}'),
            'forms' => $forms->map(fn (Form $form): array => $this->formSummary($form))->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function formGet(array $arguments): array
    {
        $form = $this->form($arguments['form'] ?? null);
        $request = $this->request();

        $fields = $form->fields->map(function (Field $field) use ($request): array {
            $shown = (new FieldResource($field))->resolve($request);
            // What the HTML actually calls it, spelled the way a submission has to spell it:
            // a field named `email` is `fields[email]` on the page and `fields.email` in the
            // errors that come back (§2.6).
            $shown['parameter'] = 'fields['.$field->key().']';
            $shown['choices'] = $field->choices();

            return $shown;
        })->values()->all();

        return [
            'form' => $this->formSummary($form),
            // The door a submission goes through, spelled out: an agent looking at a form is
            // usually looking at it to explain the form to somebody building a page.
            'intake' => $this->intake($form->slug),
            'fields' => $fields,
            'statuses' => $this->statuses(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function formSave(array $arguments): array
    {
        $form = array_key_exists('form', $arguments) ? $this->form($arguments['form']) : null;
        $sent = $this->fieldsSent($arguments);

        // What the form would be: what it is now, with what arrived written over it. A save
        // that named only `is_enabled` would otherwise arrive at the rules with no title and
        // be refused for something nobody was changing.
        $input = [...$this->formAsInput($form), ...array_intersect_key($arguments, array_flip(['slug', 'title', 'is_enabled', 'options']))];

        $this->validator()->make($input, FormInput::rules($form === null ? null : (int) $form->getKey()), FormInput::messages())->validate();

        $values = FormInput::values($input);
        $plan = array_map(fn (array $field): array => $this->fieldPlan($form, $field), $sent);

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'would' => $form === null ? 'create' : 'update',
                'slug' => $values['slug'],
                'fields' => array_map(static fn (array $one): array => [
                    'name' => $one['values']['name'],
                    'would' => $one['would'],
                ], $plan),
            ];
        }

        if ($form === null) {
            $form = new Form($values);
            // At the end of the column, where a new thing belongs.
            $form->position = (int) Form::query()->max('position') + 1;
            $form->save();
        } else {
            $form->update($values);
        }

        foreach ($plan as $one) {
            $this->applyField($form, $one);
        }

        return $this->formGet(['form' => (int) $form->refresh()->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return list<array<string, mixed>>
     */
    private function fieldsSent(array $arguments): array
    {
        $fields = $arguments['fields'] ?? [];

        if (! is_array($fields)) {
            throw new ToolFailure('`fields` is a list of questions, each an object.');
        }

        $sent = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                throw new ToolFailure('Every entry of `fields` is an object: at least a type and a title for a new one.');
            }

            $sent[] = $field;
        }

        return $sent;
    }

    /**
     * What one field of the call would do, checked before anything is written.
     *
     * Worked out for every field first and applied afterwards, so that a form is not half
     * saved when the fourth question turns out to name a type this package does not have.
     *
     * @param  array<string, mixed>  $sent
     * @return array{would: string, field: Field|null, values: array<string, mixed>}
     */
    private function fieldPlan(?Form $form, array $sent): array
    {
        $field = $form === null ? null : $this->matchField($form, $sent);

        if (($sent['remove'] ?? false) === true) {
            return $field === null
                ? throw new ToolFailure('A field to remove has to be one the form has: name it by id or by machine name.')
                : ['would' => 'remove', 'field' => $field, 'values' => ['name' => $field->key()]];
        }

        $input = [...$this->fieldAsInput($field), ...array_diff_key($sent, array_flip(['id', 'remove']))];

        $this->validator()->make(
            $input,
            FieldInput::rules($form === null ? null : (int) $form->getKey(), $field === null ? null : (int) $field->getKey()),
            FieldInput::messages(),
        )->validate();

        return [
            'would' => $field === null ? 'add' : 'update',
            'field' => $field,
            'values' => FieldInput::values($input),
        ];
    }

    /**
     * @param  array{would: string, field: Field|null, values: array<string, mixed>}  $plan
     */
    private function applyField(Form $form, array $plan): void
    {
        $field = $plan['field'];

        if ($plan['would'] === 'remove') {
            $field?->delete();

            return;
        }

        if ($field instanceof Field) {
            $field->update($plan['values']);

            return;
        }

        $made = new Field($plan['values']);
        $made->form_id = (int) $form->getKey();
        $made->position = (int) $form->fields()->max('position') + 1;
        $made->save();
    }

    /**
     * The field a call is about: the one it names by id, or the live one with that machine
     * name. A field put aside is not matched by name — it is gone from the form, and a save
     * that quietly resurrected it would be a save nobody asked for.
     *
     * @param  array<string, mixed>  $sent
     */
    private function matchField(Form $form, array $sent): ?Field
    {
        if (isset($sent['id']) && is_numeric($sent['id'])) {
            $field = $form->fields()->find((int) $sent['id']);

            return $field instanceof Field
                ? $field
                : throw new ToolFailure("Form [{$form->slug}] has no field with the id [{$sent['id']}].");
        }

        $name = trim((string) ($sent['name'] ?? ''));

        return $name === '' ? null : $form->fields()->where('name', $name)->first();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function list(array $arguments): array
    {
        $form = $this->form($arguments['form'] ?? null);
        $list = ListQuery::for($form);

        $request = $this->request([
            'view' => (string) ($arguments['view'] ?? ListQuery::VIEW_ALL),
            'assignee' => $arguments['assignee'] ?? null,
            'from' => $arguments['from'] ?? null,
            'to' => $arguments['to'] ?? null,
            'search' => $arguments['search'] ?? null,
            'sort' => $arguments['sort'] ?? null,
        ]);

        $perPage = min(100, max(5, (int) ($arguments['per_page'] ?? self::PER_PAGE)));
        $page = $list->build($request)->paginate($perPage, ['*'], 'page', max(1, (int) ($arguments['page'] ?? 1)));

        return [
            'form' => ['id' => (int) $form->getKey(), 'slug' => $form->slug],
            'columns' => array_map(static fn (Field $field): array => [
                'name' => $field->key(),
                'label' => (string) $field->title,
                'type' => $field->type->value,
            ], $list->columns),
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'counts' => $list->counts($request),
            'submissions' => array_map(
                fn (Submission $submission): array => (new SubmissionRowResource($submission, $list->columns))->resolve($request),
                $page->items(),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function get(array $arguments, ?Authenticatable $user): array
    {
        $submission = $this->submission($arguments['submission'] ?? null);
        $request = $this->request();

        $submission->load(['form', 'status', 'assignee', 'values', 'files', 'events']);

        $shown = (new SubmissionResource(
            $submission,
            Authors::names($user, $submission->events->pluck('admin_id')->all()),
        ))->resolve($request);

        // Notes are `module-admin`'s and travel on their own address in the panel; an agent
        // reading a submission is reading it to know what has been said about it, so they
        // come with it here rather than as a second call.
        $shown['notes'] = array_map(fn (Note $note): array => [
            'id' => (int) $note->getKey(),
            'body' => $note->body,
            'author' => $note->admin_id,
            'created_at' => $note->created_at?->toAtomString(),
        ], $submission->noteFeed());

        return $shown;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function setStatus(array $arguments, ?Authenticatable $user): array
    {
        $submission = $this->submission($arguments['submission'] ?? null);
        $status = array_key_exists('status', $arguments) ? $this->status((string) $arguments['status']) : null;
        // Null is a value here — "nobody" — so what says whether to touch the assignee at all
        // is whether the key was sent, and not what it holds.
        $reassign = array_key_exists('assignee', $arguments);
        $assignee = $reassign ? $this->assignee($arguments['assignee']) : null;
        $note = trim((string) ($arguments['note'] ?? ''));

        if ($status === null && ! $reassign && $note === '') {
            throw new ToolFailure('Nothing to do: name a status, an assignee or a note.');
        }

        if ($this->dryRun($arguments)) {
            return [
                'dry_run' => true,
                'submission' => (int) $submission->getKey(),
                'status' => $status === null ? null : ['from' => $submission->status?->key, 'to' => $status->key],
                'assignee' => $reassign ? ['to' => $assignee === null ? null : (int) $assignee->getKey()] : null,
                'note' => $note === '' ? null : $note,
            ];
        }

        $admin = $this->adminId($user);

        if ($status instanceof Status && (int) $submission->status_id !== (int) $status->getKey()) {
            $was = $submission->status?->key;
            $submission->forceFill(['status_id' => $status->getKey()])->save();
            $submission->log(SubmissionEvent::STATUS, $was, $status->key, $admin);
            $submission->setRelation('status', $status);
        }

        if ($reassign) {
            $this->assignTo($submission, $assignee, $admin);
        }

        if ($note !== '') {
            $submission->addNote($note, $admin);
        }

        return $this->get(['submission' => (int) $submission->getKey()], $user);
    }

    private function assignTo(Submission $submission, ?CmsUser $assignee, ?int $admin): void
    {
        $id = $assignee === null ? null : (int) $assignee->getKey();

        if ($submission->assignee_id === $id) {
            return;
        }

        $was = $submission->assignee?->name;

        $submission->forceFill(['assignee_id' => $id])->save();
        $submission->unsetRelation('assignee')->load('assignee');

        $submission->log(SubmissionEvent::ASSIGNEE, $was, $submission->assignee?->name, $admin);
    }

    /**
     * One form as an answer names it, with the two numbers the panel's column is read for.
     *
     * Every language at once rather than the one the panel happens to be in: an agent that
     * got one title has no way of knowing whether the others exist.
     *
     * @return array<string, mixed>
     */
    private function formSummary(Form $form): array
    {
        $summary = (new FormResource($form))->resolve($this->request());
        unset($summary['fields']);

        $count = $form->getAttribute('fields_count');
        $summary['fields_count'] = $count === null ? $form->fields()->count() : (int) $count;

        return $summary;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function statuses(): array
    {
        $request = $this->request();

        return Status::query()->orderBy('position')->get()
            ->map(static fn (Status $status): array => (new StatusResource($status))->resolve($request))
            ->values()
            ->all();
    }

    /**
     * The form values as the rules want to see them, so that a partial save is a partial save.
     *
     * @return array<string, mixed>
     */
    private function formAsInput(?Form $form): array
    {
        return $form === null ? [] : [
            'slug' => $form->slug,
            'title' => $form->getTranslations('title'),
            'is_enabled' => $form->is_enabled,
            'options' => $form->options ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldAsInput(?Field $field): array
    {
        return $field === null ? [] : [
            'name' => $field->name,
            'type' => $field->type->value,
            'title' => $field->getTranslations('title'),
            'placeholder' => $field->getTranslations('placeholder'),
            'help' => $field->getTranslations('help'),
            'options' => $field->options ?? [],
            'is_enabled' => $field->is_enabled,
            'is_required' => $field->is_required,
            'is_fullsize' => $field->is_fullsize,
            'in_table' => $field->in_table,
        ];
    }

    /**
     * Run a change, and turn a refusal into something the agent can read.
     *
     * Both kinds reach here written to be shown: an {@see InboxException} is a rule of the
     * module — a form with submissions is not deleted — and a `ValidationException` is the
     * same refusal the panel's editor would have got.
     *
     * @param  callable(): array<string, mixed>  $work
     * @return array<string, mixed>
     */
    private function attempt(callable $work): array
    {
        try {
            return $work();
        } catch (ValidationException $invalid) {
            $lines = [];

            foreach ($invalid->errors() as $field => $messages) {
                $lines[] = $field.': '.implode(' ', (array) $messages);
            }

            throw new ToolFailure('Not accepted — '.implode('; ', $lines));
        } catch (InboxException $refused) {
            throw new ToolFailure($refused->getMessage());
        }
    }

    private function form(mixed $reference): Form
    {
        $query = Form::query()->with('fields');

        if (is_int($reference) || (is_string($reference) && ctype_digit($reference))) {
            $form = FormController::counted($query)->find((int) $reference);

            return $form instanceof Form
                ? $form
                : throw new ToolFailure("No form has the id [{$reference}].");
        }

        if (! is_string($reference) || trim($reference) === '') {
            throw new ToolFailure('`form` is required: an id, or a slug like "contact".');
        }

        $form = FormController::counted($query)->where('slug', trim($reference))->first();

        return $form instanceof Form
            ? $form
            : throw new ToolFailure("No form answers at [{$reference}]. inbox_forms_list says what the slugs are.");
    }

    private function submission(mixed $reference): Submission
    {
        if (! is_int($reference) && ! (is_string($reference) && ctype_digit($reference))) {
            throw new ToolFailure('`submission` is the id of a submission, as inbox_list reports it.');
        }

        $submission = Submission::query()->find((int) $reference);

        return $submission instanceof Submission
            ? $submission
            : throw new ToolFailure("No submission has the id [{$reference}].");
    }

    private function status(string $key): Status
    {
        $status = Status::query()->where('key', trim($key))->first();

        if ($status instanceof Status) {
            return $status;
        }

        /** @var list<string> $keys */
        $keys = Status::query()->orderBy('position')->pluck('key')->all();

        throw new ToolFailure("This site has no status [{$key}]. It has: ".implode(', ', $keys).'.');
    }

    /** An administrator by id or by email — or nobody, which is a thing to be set to. */
    private function assignee(mixed $reference): ?CmsUser
    {
        if ($reference === null || $reference === '') {
            return null;
        }

        $query = CmsUser::query();

        $user = is_int($reference) || (is_string($reference) && ctype_digit($reference))
            ? $query->find((int) $reference)
            : $query->where('email', trim((string) $reference))->first();

        return $user instanceof CmsUser
            ? $user
            : throw new ToolFailure("No administrator matches [{$reference}]. Use an id or an email address, or null for nobody.");
    }

    /** Where a form on the site posts (§6). */
    private function intake(string $slug): string
    {
        return url('/'.trim((string) $this->container->make('config')->get('webx-inbox.path'), '/').'/'.$slug);
    }

    /**
     * A request carrying the filters, because that is what the panel's list reads them from.
     *
     * Made rather than taken from the container: under stdio there is no request with a query
     * string on it, and under HTTP the one there is belongs to the MCP call and not to a list.
     *
     * @param  array<string, mixed>  $query
     */
    private function request(array $query = []): Request
    {
        return Request::create('/', 'GET', array_filter($query, static fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function dryRun(array $arguments): bool
    {
        return (bool) ($arguments[Tool::DRY_RUN] ?? false);
    }

    private function adminId(?Authenticatable $user): ?int
    {
        $id = $user?->getAuthIdentifier();

        return is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null;
    }

    private function validator(): ValidationFactory
    {
        return $this->container->make(ValidationFactory::class);
    }
}
