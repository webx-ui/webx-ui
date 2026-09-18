<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Models\Status;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;

/**
 * The submissions as the panel reads and works them (§11, §12).
 */
final class PanelSubmissionsTest extends TestCase
{
    #[Test]
    public function the_list_carries_the_columns_the_form_asked_for(): void
    {
        $form = $this->form();
        $submission = $this->filled($form, ['name' => 'Ada', 'email' => 'ada@example.test', 'message' => 'Hello']);

        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api('forms/'.$form->getKey().'/submissions'))
            ->assertOk();

        // The list says what it is: no second request for the form to find out what the
        // columns are.
        $this->assertSame(['name', 'email'], array_column($response->json('columns'), 'key'));

        $row = $response->json('data.0');

        $this->assertSame($submission->getKey(), $row['id']);
        $this->assertSame('Ada', $row['values']['name']);
        $this->assertSame('ada@example.test', $row['values']['email']);
        // `message` is not `in_table`, so it is not a column and does not travel.
        $this->assertArrayNotHasKey('message', $row['values']);
        $this->assertFalse($row['is_read']);
        $this->assertSame('new', $row['status']['key']);
    }

    #[Test]
    public function spam_stays_out_of_the_list_until_its_own_tab_is_opened(): void
    {
        $form = $this->form();
        $this->filled($form, ['name' => 'Ada']);
        $this->filled($form, ['name' => 'A robot'], ['status_id' => Status::spam()?->getKey()]);

        $editor = $this->editor();

        $all = $this->actingAs($editor, 'cms')
            ->getJson($this->api('forms/'.$form->getKey().'/submissions'))
            ->assertOk();

        $this->assertCount(1, $all->json('data'));
        $this->assertSame(1, $all->json('counts.all'), 'the count agrees with what the tab shows');
        $this->assertSame(1, $all->json('counts.statuses.spam'), 'and the spam tab still says how much is in it');

        $spam = $this->actingAs($editor, 'cms')
            ->getJson($this->api('forms/'.$form->getKey().'/submissions?view=spam'))
            ->assertOk();

        $this->assertCount(1, $spam->json('data'));
        $this->assertSame('A robot', $spam->json('data.0.values.name'));
    }

    #[Test]
    public function the_unread_tab_is_what_nobody_has_opened(): void
    {
        $form = $this->form();
        $this->filled($form, ['name' => 'Ada']);
        $this->filled($form, ['name' => 'Grace'], ['read_at' => now()]);

        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api('forms/'.$form->getKey().'/submissions?view=unread'))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Ada', $response->json('data.0.values.name'));
        $this->assertSame(1, $response->json('counts.unread'));
    }

    #[Test]
    public function the_search_looks_at_every_answer_and_not_only_the_columns(): void
    {
        $form = $this->form();
        $this->filled($form, ['name' => 'Ada', 'message' => 'The lift is broken']);
        $this->filled($form, ['name' => 'Grace', 'message' => 'All well']);

        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api('forms/'.$form->getKey().'/submissions?search=lift'))
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Ada', $response->json('data.0.values.name'));
    }

    #[Test]
    public function opening_one_marks_it_read_and_brings_its_neighbours(): void
    {
        $form = $this->form();
        $older = $this->filled($form, ['name' => 'Ada']);
        $middle = $this->filled($form, ['name' => 'Grace']);
        $newer = $this->filled($form, ['name' => 'Alan']);

        $response = $this->actingAs($this->editor(), 'cms')
            ->getJson($this->api('submissions/'.$middle->getKey()))
            ->assertOk();

        $this->assertTrue($response->json('data.is_read'));
        $this->assertNotNull($middle->refresh()->read_at);

        // Newest first, so "previous" is the one above it in the list.
        $this->assertSame($newer->getKey(), $response->json('data.previous_id'));
        $this->assertSame($older->getKey(), $response->json('data.next_id'));

        $values = array_column((array) $response->json('data.values'), null, 'name');
        $this->assertSame('Grace', $values['name']['value'] ?? null, 'the answers read with the words they were asked in');
        $this->assertSame('Name', $values['name']['label'] ?? null);
    }

    #[Test]
    public function the_status_and_the_assignee_change_and_the_log_says_so(): void
    {
        $form = $this->form();
        $submission = $this->filled($form, ['name' => 'Ada']);

        $editor = $this->editor();
        $done = Status::query()->where('key', 'done')->sole();

        $this->actingAs($editor, 'cms')
            ->putJson($this->api('submissions/'.$submission->getKey()), [
                'status_id' => $done->getKey(),
                'assignee_id' => $editor->getKey(),
            ])
            ->assertOk()
            ->assertJsonPath('data.status.key', 'done')
            ->assertJsonPath('data.assignee.id', $editor->getKey());

        $log = $submission->events()->get();

        $status = $log->firstWhere('type', SubmissionEvent::STATUS);
        $this->assertNotNull($status);
        // The key and not the id: read months later, the row it pointed at may be gone.
        $this->assertSame('new', $status->from);
        $this->assertSame('done', $status->to);

        $assignee = $log->firstWhere('type', SubmissionEvent::ASSIGNEE);
        $this->assertNotNull($assignee);
        $this->assertSame($editor->name, $assignee->to);
        $this->assertSame($editor->getKey(), $assignee->admin_id);
    }

    #[Test]
    public function a_typo_in_an_answer_is_corrected_without_touching_the_snapshot(): void
    {
        $form = $this->form();
        $submission = $this->filled($form, ['name' => 'Ada', 'email' => 'ada@example.test']);

        $this->actingAs($this->editor(), 'cms')
            ->putJson($this->api('submissions/'.$submission->getKey()), [
                'values' => ['email' => 'ada@example.org'],
            ])
            ->assertOk();

        $submission->refresh();

        $email = $submission->value('email');
        $name = $submission->value('name');

        $this->assertNotNull($email);
        $this->assertSame('ada@example.org', $email->value);
        $this->assertSame('Email', $email->label, 'the question stays as it was asked');
        $this->assertNotNull($name);
        $this->assertSame('Ada', $name->value, 'and nothing else is touched');
    }

    #[Test]
    public function a_submission_can_be_typed_in_by_hand(): void
    {
        $form = $this->form();

        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('forms/'.$form->getKey().'/submissions'), [
                'fields' => ['name' => 'Ada', 'email' => 'ada@example.test'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.source', Submission::SOURCE_PANEL)
            // Whoever typed it in has read it: otherwise it lands unread in its own list.
            ->assertJsonPath('data.is_read', true);

        $this->assertSame(1, Submission::query()->count());

        // The same rules as the public door, because it is the same intake.
        $this->actingAs($this->editor(), 'cms')
            ->postJson($this->api('forms/'.$form->getKey().'/submissions'), ['fields' => ['name' => '']])
            ->assertJsonValidationErrors('fields.name');
    }

    #[Test]
    public function a_pile_is_moved_or_thrown_away_at_once(): void
    {
        $form = $this->form();
        $one = $this->filled($form, ['name' => 'Ada']);
        $two = $this->filled($form, ['name' => 'Grace']);

        $editor = $this->editor();
        $spam = Status::spam();

        $this->actingAs($editor, 'cms')
            ->postJson($this->api('submissions/mass'), [
                'ids' => [$one->getKey(), $two->getKey()],
                'action' => 'status',
                'status_id' => $spam?->getKey(),
            ])
            ->assertOk()
            ->assertJsonPath('data.count', 2);

        $this->assertSame($spam?->getKey(), $one->refresh()->status_id);
        // Row by row, so the log is written — a mass update would have been silent.
        $this->assertSame('spam', $two->events()->where('type', SubmissionEvent::STATUS)->sole()->to);

        $this->actingAs($editor, 'cms')
            ->postJson($this->api('submissions/mass'), [
                'ids' => [$one->getKey(), $two->getKey()],
                'action' => 'delete',
            ])
            ->assertOk();

        $this->assertSame(0, Submission::query()->count());
    }

    #[Test]
    public function deleting_a_submission_takes_its_attachments_off_the_disk(): void
    {
        $form = $this->form('plans', [
            ['name' => 'name', 'type' => FieldType::Text, 'is_required' => true],
            ['name' => 'plan', 'type' => FieldType::File],
        ]);

        $this->post($this->intake($form->slug), [
            'fields' => ['name' => 'Ada', 'plan' => UploadedFile::fake()->create('plan.pdf', 12)],
        ]);

        $submission = Submission::query()->sole();
        $file = $submission->files()->sole();

        Storage::disk('inbox')->assertExists($file->path);

        $this->actingAs($this->editor(), 'cms')
            ->deleteJson($this->api('submissions/'.$submission->getKey()))
            ->assertNoContent();

        // The database cascade raises no model event, so the bytes would otherwise stay with
        // nothing left pointing at them.
        Storage::disk('inbox')->assertMissing($file->path);
    }

    #[Test]
    public function the_export_is_every_field_and_not_only_the_columns(): void
    {
        $form = $this->form();
        $this->filled($form, ['name' => 'Ada', 'email' => 'ada@example.test', 'message' => 'The lift is broken']);

        $response = $this->actingAs($this->editor(), 'cms')
            ->get($this->api('forms/'.$form->getKey().'/submissions/export'))
            ->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Message (message)', $csv, 'a column the list has no room for is exactly what an export is opened for');
        $this->assertStringContainsString('ada@example.test', $csv);
        $this->assertStringContainsString('The lift is broken', $csv);
    }

    #[Test]
    public function reading_and_answering_are_two_different_rights(): void
    {
        $form = $this->form();
        $submission = $this->filled($form, ['name' => 'Ada']);

        $reader = $this->editor(['inbox.view']);

        $this->actingAs($reader, 'cms')
            ->getJson($this->api('forms/'.$form->getKey().'/submissions'))
            ->assertOk();

        $this->actingAs($reader, 'cms')
            ->putJson($this->api('submissions/'.$submission->getKey()), ['status_id' => Status::spam()?->getKey()])
            ->assertForbidden();

        $this->actingAs($reader, 'cms')
            ->deleteJson($this->api('submissions/'.$submission->getKey()))
            ->assertForbidden();

        $this->actingAs($reader, 'cms')
            ->postJson($this->api('submissions/mass'), ['ids' => [$submission->getKey()], 'action' => 'delete'])
            ->assertForbidden();

        // And somebody with neither right has no section at all.
        $this->actingAs($this->editor(['pages.view']), 'cms')
            ->getJson($this->api('forms/'.$form->getKey().'/submissions'))
            ->assertForbidden();
    }

    /**
     * A submission with the answers written down, the way the intake would have written them.
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
}
