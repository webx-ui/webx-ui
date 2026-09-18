<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Notes\Note;
use WebxUi\Inbox\Models\Submission;
use WebxUi\Inbox\Models\SubmissionEvent;

/**
 * Notes on a submission (§2.17).
 *
 * The feed itself belongs to `module-admin` and is tested there; what is tested here is the
 * half this module owns — that submissions are registered under an alias, that the permission
 * they name is the one that actually governs, and that a note lands in the submission's own log
 * rather than only in a table nobody looks at.
 */
final class PanelNotesTest extends TestCase
{
    #[Test]
    public function a_note_is_written_under_the_alias_and_shows_up_in_the_log(): void
    {
        $submission = $this->submission($this->form());
        $editor = $this->editor();

        $this->actingAs($editor, 'cms')
            ->postJson($this->notes($submission), ['body' => 'Rang back, will call again on Monday.'])
            ->assertCreated()
            ->assertJsonPath('data.author.name', $editor->name);

        $this->assertSame(Submission::MORPH, Note::query()->sole()->entity_type);

        // The log is what the card reads beside the feed: a note is something that happened.
        $event = $submission->events()->where('type', SubmissionEvent::NOTE)->sole();
        $this->assertSame($editor->getKey(), $event->admin_id);
    }

    #[Test]
    public function notes_are_behind_the_right_to_answer_and_not_the_right_to_read(): void
    {
        $submission = $this->submission($this->form());

        $this->actingAs($this->editor(['inbox.view']), 'cms')
            ->getJson($this->notes($submission))
            ->assertForbidden();

        $this->actingAs($this->editor(['inbox.view']), 'cms')
            ->postJson($this->notes($submission), ['body' => 'Hello'])
            ->assertForbidden();

        $this->assertSame(0, Note::query()->count());

        $this->actingAs($this->editor(['inbox.view', 'inbox.update']), 'cms')
            ->getJson($this->notes($submission))
            ->assertOk();
    }

    private function notes(Submission $submission): string
    {
        return '/api/cms/entities/'.Submission::MORPH.'/'.$submission->getKey().'/notes';
    }
}
