<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Tests;

use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Notes\Note;
use WebxUi\Admin\Testing\CountsQueries;
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
    use CountsQueries;

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

    /**
     * A face rather than initials where the author has a photograph — found on a demo site, where
     * the sidebar had it and the feed did not — and one query for the authors of the whole feed.
     */
    #[Test]
    public function the_feed_carries_each_authors_photograph_in_one_query(): void
    {
        $submission = $this->submission($this->form());
        $reader = $this->editor();
        $authors = [$this->editor(), $this->editor(), $this->editor()];

        foreach ($authors as $index => $author) {
            $author->forceFill(['avatar' => $index === 2 ? null : "avatars/{$index}.jpg"])->save();
            Note::query()->create([
                'entity_type' => Submission::MORPH,
                'entity_id' => $submission->getKey(),
                'admin_id' => $author->getKey(),
                'body' => "Note {$index}",
            ]);
        }

        $this->actingAs($reader, 'cms');

        $feed = $this->assertNoRepeatedQueries(
            fn () => $this->getJson($this->notes($submission))->assertOk()->json('data'),
            'every author of the feed is read in one query',
        );

        $avatars = array_column(array_column($feed, 'author'), 'avatar', 'id');

        $this->assertSame('avatars/0.jpg', $avatars[$authors[0]->getKey()]);
        $this->assertSame('avatars/1.jpg', $avatars[$authors[1]->getKey()]);
        $this->assertNull($avatars[$authors[2]->getKey()], 'no photograph is initials, not an error');
    }

    /** The note stays when its author goes; the feed draws initials rather than failing. */
    #[Test]
    public function a_note_by_a_deleted_administrator_still_reads(): void
    {
        $submission = $this->submission($this->form());
        $gone = $this->editor();
        $gone->forceFill(['avatar' => 'avatars/gone.jpg'])->save();

        Note::query()->create([
            'entity_type' => Submission::MORPH,
            'entity_id' => $submission->getKey(),
            'admin_id' => $gone->getKey(),
            'body' => 'Before I left.',
        ]);
        $gone->roles()->detach();
        $gone->delete();

        $this->actingAs($this->editor(), 'cms')
            ->getJson($this->notes($submission))
            ->assertOk()
            ->assertJsonPath('data.0.author.name', null)
            ->assertJsonPath('data.0.author.avatar', null);
    }

    private function notes(Submission $submission): string
    {
        return '/api/cms/entities/'.Submission::MORPH.'/'.$submission->getKey().'/notes';
    }
}
