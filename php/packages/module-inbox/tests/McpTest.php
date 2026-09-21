<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Status;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;
use WebxUi\Mcp\Registry\ToolRegistry;
use WebxUi\Mcp\Server\RegistryTool;
use WebxUi\Mcp\Server\WebxServer;

/**
 * The section by its other doors (§14): what an agent can do with the forms of a site and
 * with what has come in through them.
 */
final class McpTest extends TestCase
{
    #[Test]
    public function the_module_offers_six_tools_under_two_scopes(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        $this->assertSame(
            ['inbox_forms_list', 'inbox_form_get', 'inbox_form_save', 'inbox_list', 'inbox_get', 'inbox_set_status'],
            array_map(static fn ($tool): string => $tool->fullName(), $registry->toolsOf('inbox')),
        );

        $this->assertContains('inbox:read', $registry->scopes());
        $this->assertContains('inbox:write', $registry->scopes());

        // Receiving a submission is not one of them: the intake is a public door with the
        // antispam in front of it, and a second way in would go round both (§14).
        $this->assertSame([], array_filter(
            $registry->toolsOf('inbox'),
            static fn ($tool): bool => str_contains($tool->fullName(), 'submit') || str_contains($tool->fullName(), 'delete'),
        ));
    }

    #[Test]
    public function the_tools_are_behind_the_permissions_the_panel_asks_for_the_same_work(): void
    {
        $registry = $this->app->make(ToolRegistry::class);

        // Three permissions rather than two, and writing is split by what it writes: moving
        // a submission along is `inbox.update`, changing what a form asks is `inbox.manage`.
        $this->assertSame(['inbox.view', 'inbox.manage'], $registry->tool('inbox_forms_list')->permissions());
        $this->assertSame(['inbox.view'], $registry->tool('inbox_list')->permissions());
        $this->assertSame(['inbox.update'], $registry->tool('inbox_set_status')->permissions());
        $this->assertSame(['inbox.manage'], $registry->tool('inbox_form_save')->permissions());

        $form = $this->form();
        $reader = $this->editor(['inbox.view']);

        $this->agent('forms_list', [], $reader)->assertOk();
        $this->agent('list', ['form' => $form->slug], $reader)->assertOk();
        $this->agent('set_status', ['submission' => 1, 'status' => 'done'], $reader)->assertHasErrors(['[inbox.update]']);
        $this->agent('form_save', ['slug' => 'nope', 'title' => 'Nope'], $reader)->assertHasErrors(['[inbox.manage]']);

        // Somebody who designs the forms does not thereby read what came in through them.
        $designer = $this->editor(['inbox.manage']);

        $this->agent('forms_list', [], $designer)->assertOk();
        $this->agent('list', ['form' => $form->slug], $designer)->assertHasErrors(['[inbox.view]']);
    }

    #[Test]
    public function the_list_of_forms_says_what_has_come_in_and_what_nobody_has_read(): void
    {
        $form = $this->form();
        $this->filled($form, ['name' => 'Ada']);
        $this->filled($form, ['name' => 'Grace'], ['read_at' => now()]);
        $this->form('careers');

        $content = $this->content($this->agent('forms_list')->assertOk());

        $this->assertSame(2, $content['count']);
        $this->assertStringEndsWith('/webx/forms/{slug}', $content['intake']);

        $bySlug = array_column($content['forms'], null, 'slug');

        $this->assertSame(2, $bySlug['contact']['submissions_count']);
        $this->assertSame(1, $bySlug['contact']['unread_count']);
        $this->assertSame(3, $bySlug['contact']['fields_count']);
        // Every language at once: an agent that got one title cannot tell whether the others
        // exist.
        $this->assertSame(['en' => 'Contact'], $bySlug['contact']['title']);
        $this->assertSame(0, $bySlug['careers']['submissions_count']);
    }

    #[Test]
    public function one_form_answers_with_the_shape_the_intake_expects(): void
    {
        $form = $this->form('contact', [
            ['name' => 'email', 'type' => FieldType::Email, 'is_required' => true],
            ['name' => 'topic', 'type' => FieldType::Select, 'options' => ['choices' => [
                ['value' => 'sales', 'label' => ['en' => 'Sales']],
                ['value' => 'support', 'label' => ['en' => 'Support']],
            ]]],
            ['type' => FieldType::Textarea],
        ]);

        $content = $this->content($this->agent('form_get', ['form' => 'contact'])->assertOk());

        $this->assertSame((int) $form->getKey(), $content['form']['id']);
        $this->assertStringEndsWith('/webx/forms/contact', $content['intake']);

        [$email, $topic, $unnamed] = $content['fields'];

        $this->assertSame('fields[email]', $email['parameter']);
        $this->assertTrue($email['is_required']);
        $this->assertSame(['sales' => 'Sales', 'support' => 'Support'], $topic['choices']);
        // A field nobody named still has a name in the HTML, and this is where it is said
        // out loud (§2.6).
        $this->assertSame('fields[f'.$form->fields->last()?->getKey().']', $unnamed['parameter']);

        $this->assertSame(
            ['new', 'in-progress', 'done', 'rejected', 'spam'],
            array_column($content['statuses'], 'key'),
        );
    }

    #[Test]
    public function a_form_and_its_questions_are_written_in_one_call(): void
    {
        $content = $this->content($this->agent('form_save', [
            'slug' => 'callback',
            'title' => ['en' => 'Call me back'],
            'options' => ['recipients' => [['email' => 'sales@example.test']]],
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'title' => ['en' => 'Name'], 'is_required' => true, 'in_table' => true],
                ['name' => 'phone', 'type' => 'tel', 'title' => ['en' => 'Telephone'], 'in_table' => true],
            ],
        ])->assertOk());

        $this->assertSame('callback', $content['form']['slug']);
        $this->assertTrue($content['form']['is_enabled']);
        $this->assertSame([['email' => 'sales@example.test']], $content['form']['options']['recipients']);
        $this->assertSame(['name', 'phone'], array_column($content['fields'], 'name'));
        // In the order they were sent, each at the end of what was there — the same thing the
        // panel's dialog does with a new question.
        $this->assertSame([1, 2], array_column($content['fields'], 'position'));
    }

    #[Test]
    public function a_save_that_names_one_thing_changes_one_thing(): void
    {
        $form = $this->form();

        $content = $this->content($this->agent('form_save', [
            'form' => 'contact',
            'is_enabled' => false,
        ])->assertOk());

        $this->assertFalse($content['form']['is_enabled']);
        // The title and the slug were never sent and are still there: a partial save that
        // arrived at the rules with no title would be refused for something nobody touched.
        $this->assertSame(['en' => 'Contact'], $content['form']['title']);
        $this->assertCount(3, $content['fields']);
        $this->assertSame($form->fields->pluck('id')->all(), array_column($content['fields'], 'id'));
    }

    #[Test]
    public function a_field_is_matched_by_its_machine_name_and_put_aside_rather_than_destroyed(): void
    {
        $form = $this->form();
        $submission = $this->filled($form, ['name' => 'Ada', 'message' => 'Hello']);
        $message = $form->fields->firstWhere('name', 'message');

        $this->agent('form_save', [
            'form' => 'contact',
            'fields' => [
                ['name' => 'name', 'title' => ['en' => 'Your name']],
                ['name' => 'message', 'remove' => true],
            ],
        ])->assertOk();

        $this->assertSame(['en' => 'Your name'], $form->fields()->where('name', 'name')->first()?->getTranslations('title'));
        $this->assertTrue(Field::withTrashed()->find($message?->getKey())?->trashed());

        // The answer given through it reads on, with the label it was asked under (§2.3).
        $this->assertSame('Hello', $submission->values()->where('name', 'message')->value('value'));
    }

    #[Test]
    public function a_save_is_refused_for_the_same_reasons_the_panel_refuses_one(): void
    {
        $this->form();

        $this->agent('form_save', ['slug' => 'Contact Us', 'title' => 'Contact us'])
            ->assertHasErrors()
            ->assertSee('slug');

        // The address another form already answers at.
        $this->agent('form_save', ['slug' => 'contact', 'title' => 'Another'])->assertHasErrors();

        // A recipient nobody can be written to reads exactly like one that works.
        $this->agent('form_save', [
            'form' => 'contact',
            'options' => ['recipients' => [['email' => 'not-an-address']]],
        ])->assertHasErrors();

        // And nothing was half written: the fourth question naming a type this package does
        // not have stops the whole call.
        $this->agent('form_save', [
            'form' => 'contact',
            'fields' => [['name' => 'extra', 'type' => 'wysiwyg', 'title' => 'Extra']],
        ])->assertHasErrors();

        $this->assertNull(Form::query()->where('slug', 'contact')->first()?->fields()->where('name', 'extra')->first());
    }

    #[Test]
    public function a_dry_run_reports_what_it_would_do_and_writes_nothing(): void
    {
        $content = $this->content($this->agent('form_save', [
            'slug' => 'callback',
            'title' => 'Call me back',
            'fields' => [['name' => 'phone', 'type' => 'tel', 'title' => 'Telephone']],
            'dry_run' => true,
        ])->assertOk());

        $this->assertTrue($content['dry_run']);
        $this->assertSame('create', $content['would']);
        $this->assertSame([['name' => 'phone', 'would' => 'add']], $content['fields']);
        $this->assertNull(Form::query()->where('slug', 'callback')->first());
    }

    #[Test]
    public function the_submissions_of_a_form_come_with_their_columns_and_their_counts(): void
    {
        $form = $this->form();
        $this->filled($form, ['name' => 'Ada', 'email' => 'ada@example.test', 'message' => 'Hello']);
        $this->filled($form, ['name' => 'A robot'], ['status_id' => Status::spam()?->getKey()]);

        $content = $this->content($this->agent('list', ['form' => 'contact'])->assertOk());

        $this->assertSame(['name', 'email'], array_column($content['columns'], 'name'));
        // Spam is out of the list by design, and the count agrees with what the list shows.
        $this->assertSame(1, $content['total']);
        $this->assertSame(1, $content['counts']['all']);
        $this->assertSame(1, $content['counts']['statuses']['spam']);
        $this->assertSame('Ada', $content['submissions'][0]['values']['name']);
        $this->assertArrayNotHasKey('message', $content['submissions'][0]['values']);

        // The search looks inside every answer, and not only the ones that are columns.
        $found = $this->content($this->agent('list', ['form' => 'contact', 'search' => 'Hello'])->assertOk());
        $this->assertSame(1, $found['total']);

        $spam = $this->content($this->agent('list', ['form' => 'contact', 'view' => 'spam'])->assertOk());
        $this->assertSame('A robot', $spam['submissions'][0]['values']['name']);
    }

    #[Test]
    public function one_submission_opens_with_its_answers_notes_and_log(): void
    {
        $form = $this->form();
        $submission = $this->filled($form, ['name' => 'Ada', 'email' => 'ada@example.test']);
        $submission->log(SubmissionEvent::CREATED);
        $submission->addNote('Called back, no answer.', (int) $this->editor()->getKey());

        $content = $this->content($this->agent('get', ['submission' => (int) $submission->getKey()])->assertOk());

        $this->assertSame('contact', $content['form']['slug']);
        $this->assertSame(['Ada', 'ada@example.test'], array_column($content['values'], 'value'));
        // The words the question was asked in, as they were at the time (§2.2).
        $this->assertSame(['Name', 'Email'], array_column($content['values'], 'label'));
        $this->assertSame('Called back, no answer.', $content['notes'][0]['body']);
        $this->assertContains('note', array_column($content['events'], 'type'));

        // Reading is not a person having looked: the badge is still there.
        $this->assertFalse($content['is_read']);
        $this->assertNull($submission->refresh()->read_at);
    }

    #[Test]
    public function a_status_an_assignee_and_a_note_travel_in_one_call(): void
    {
        $form = $this->form();
        $submission = $this->filled($form, ['name' => 'Ada']);
        $agent = $this->editor();
        $owner = $this->editor();

        $content = $this->content($this->agent('set_status', [
            'submission' => (int) $submission->getKey(),
            'status' => 'in-progress',
            'assignee' => $owner->email,
            'note' => 'Mine.',
        ], $agent)->assertOk());

        $this->assertSame('in-progress', $content['status']['key']);
        $this->assertSame((int) $owner->getKey(), $content['assignee']['id']);
        $this->assertSame('Mine.', $content['notes'][0]['body']);

        $log = $submission->refresh()->events()->get();

        $this->assertSame(['status', 'assignee', 'note'], $log->pluck('type')->all());

        $moved = $log->firstWhere('type', 'status');

        $this->assertInstanceOf(SubmissionEvent::class, $moved);
        $this->assertSame('new', $moved->from);
        // Under whoever the token belongs to, and not under nobody.
        $this->assertSame((int) $agent->getKey(), $moved->admin_id);

        // Nobody is a value: null takes it off everybody.
        $this->agent('set_status', ['submission' => (int) $submission->getKey(), 'assignee' => null], $agent)->assertOk();
        $this->assertNull($submission->refresh()->assignee_id);
    }

    #[Test]
    public function a_status_this_site_does_not_have_is_said_out_loud(): void
    {
        $form = $this->form();
        $submission = $this->filled($form, ['name' => 'Ada']);

        $this->agent('set_status', ['submission' => (int) $submission->getKey(), 'status' => 'archived'])
            ->assertHasErrors()
            ->assertSee('in-progress');

        // And a call that names nothing to change says so rather than answering cheerfully.
        $this->agent('set_status', ['submission' => (int) $submission->getKey()])->assertHasErrors();

        $this->assertSame('new', $submission->refresh()->status?->key);
    }

    /**
     * A submission with its answers written the way the intake writes them.
     *
     * @param  array<string, string>  $values
     * @param  array<string, mixed>  $attributes
     */
    private function filled(Form $form, array $values, array $attributes = []): Submission
    {
        $submission = $this->submission($form, $attributes);

        foreach ($form->fields as $field) {
            if (! array_key_exists($field->key(), $values)) {
                continue;
            }

            $submission->values()->create([
                'form_id' => $form->getKey(),
                'field_id' => $field->getKey(),
                'name' => $field->key(),
                'label' => (string) $field->title,
                'type' => $field->type->value,
                'value' => $values[$field->key()],
            ]);
        }

        return $submission->refresh();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function agent(string $tool, array $arguments = [], ?CmsUser $as = null): TestResponse
    {
        $registry = $this->app->make(ToolRegistry::class);
        $bound = new RegistryTool($registry->tool('inbox_'.$tool));

        return $as instanceof CmsUser
            ? WebxServer::actingAs($as, 'cms')->tool($bound, $arguments)
            : WebxServer::tool($bound, $arguments);
    }

    /**
     * @return array<string, mixed>
     */
    private function content(TestResponse $response): array
    {
        $decoded = null;

        $response->assertStructuredContent(static function (AssertableJson $json) use (&$decoded): void {
            $decoded = $json->etc()->toArray();
        });

        return is_array($decoded) ? $decoded : [];
    }
}
