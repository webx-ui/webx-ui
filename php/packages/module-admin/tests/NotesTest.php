<?php

declare(strict_types=1);

namespace WebxUi\Admin\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebxUi\Admin\Notes\Note;
use WebxUi\Admin\Notes\NoteTypes;
use WebxUi\Admin\Tests\Fixtures\Editor;
use WebxUi\Admin\Tests\Fixtures\Ticket;

/**
 * Notes on any record, through one address.
 *
 * The two things worth a test are the two that would be quiet if they broke: the type in the
 * address being an alias rather than a class name, and the permission being the record's own
 * answer — a shared endpoint that guessed would hand one section's notes to the other
 * section's readers.
 */
final class NotesTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate')->run();

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('subject');
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(NoteTypes::class)->register('ticket', Ticket::class);
    }

    #[Test]
    public function it_writes_and_reads_a_feed_under_the_alias(): void
    {
        $ticket = Ticket::query()->create(['subject' => 'The lift']);

        $this->actingAs(new Editor(['tickets.update']))
            ->postJson('/api/cms/entities/ticket/'.$ticket->getKey().'/notes', ['body' => 'Called back, no answer.'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Called back, no answer.')
            ->assertJsonPath('data.is_mine', true)
            ->assertJsonPath('data.author.id', 1);

        // The alias and not the class name: a namespace in a column is one nobody may rename.
        $this->assertSame('ticket', Note::query()->sole()->entity_type);

        $this->actingAs(new Editor(['tickets.update']))
            ->getJson('/api/cms/entities/ticket/'.$ticket->getKey().'/notes')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function the_record_says_which_permission_its_notes_are_behind(): void
    {
        $ticket = Ticket::query()->create(['subject' => 'The lift']);

        // A right to something else is not a right to this.
        $this->actingAs(new Editor(['inbox.update']))
            ->getJson('/api/cms/entities/ticket/'.$ticket->getKey().'/notes')
            ->assertForbidden();

        $this->actingAs(new Editor(['inbox.update']))
            ->postJson('/api/cms/entities/ticket/'.$ticket->getKey().'/notes', ['body' => 'Hello'])
            ->assertForbidden();

        $this->assertSame(0, Note::query()->count());
    }

    #[Test]
    public function a_type_nobody_registered_is_not_a_type(): void
    {
        $this->actingAs(new Editor(['tickets.update']))
            ->getJson('/api/cms/entities/invoice/1/notes')
            ->assertNotFound();
    }

    #[Test]
    public function a_note_is_edited_and_deleted_by_whoever_wrote_it(): void
    {
        $ticket = Ticket::query()->create(['subject' => 'The lift']);
        $note = $ticket->addNote('Called back, no answer.', 1);

        $this->actingAs(new Editor(['tickets.update'], 2, 'Somebody else'))
            ->putJson('/api/cms/notes/'.$note->getKey(), ['body' => 'Rewritten'])
            ->assertForbidden();

        $this->actingAs(new Editor(['tickets.update'], 2, 'Somebody else'))
            ->deleteJson('/api/cms/notes/'.$note->getKey())
            ->assertForbidden();

        $this->actingAs(new Editor(['tickets.update']))
            ->putJson('/api/cms/notes/'.$note->getKey(), ['body' => 'Called back, will ring tomorrow.'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Called back, will ring tomorrow.');

        $this->actingAs(new Editor(['tickets.update']))
            ->deleteJson('/api/cms/notes/'.$note->getKey())
            ->assertNoContent();

        $this->assertSame(0, Note::query()->count());
    }

    #[Test]
    public function the_feed_of_one_record_is_not_the_feed_of_another(): void
    {
        $one = Ticket::query()->create(['subject' => 'The lift']);
        $two = Ticket::query()->create(['subject' => 'The door']);

        $one->addNote('About the lift', 1);
        $two->addNote('About the door', 1);

        $this->actingAs(new Editor(['tickets.update']))
            ->getJson('/api/cms/entities/ticket/'.$two->getKey().'/notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'About the door');
    }
}
