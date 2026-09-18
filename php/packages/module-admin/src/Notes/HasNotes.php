<?php

declare(strict_types=1);

namespace WebxUi\Admin\Notes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A record administrators can write notes on.
 *
 * The one thing a model has to say for itself is {@see notesPermission()}, and it is abstract
 * on purpose: the notes of every kind of record travel through one controller and one address,
 * so a model that did not name its permission would have its notes read by anybody who could
 * read anybody else's — somebody with a right to the submissions would be reading the notes on
 * the orders. Making it a contract of the trait means that gap cannot be left open by
 * forgetting something.
 *
 * Pairs with {@see Notable}: a model takes the trait and declares the interface, which is what
 * lets the one shared controller check a type it has resolved out of an address.
 *
 * @mixin Model
 */
trait HasNotes
{
    /**
     * The feed, oldest first: it is read as a conversation and not as a list of records.
     *
     * @return MorphMany<Note, $this>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'entity', 'entity_type', 'entity_id')->orderBy('id');
    }

    /**
     * The feed itself — what {@see Notable} promises, and what a reader of it wants.
     *
     * @return list<Note>
     */
    public function noteFeed(): array
    {
        return $this->notes()->get()->all();
    }

    /** The permission that both reads and writes the notes of this kind of record. */
    abstract public function notesPermission(): string;

    /** Write one, and tell the record it happened. */
    public function addNote(string $body, ?int $adminId = null): Note
    {
        /** @var Note $note */
        $note = $this->notes()->create(['admin_id' => $adminId, 'body' => $body]);

        $this->noteAdded($note);

        return $note;
    }

    /**
     * What the record does about a note of its own — a line in its log, a timestamp, nothing.
     *
     * A hook rather than a model event, because a note is written on the note's table and the
     * record itself is not touched: `saved` on the record would never fire.
     */
    protected function noteAdded(Note $note): void
    {
        //
    }
}
