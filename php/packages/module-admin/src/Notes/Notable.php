<?php

declare(strict_types=1);

namespace WebxUi\Admin\Notes;

/**
 * A record that carries notes.
 *
 * {@see HasNotes} is the whole implementation; this exists so that the one controller which
 * serves every kind of note can say what it requires of a model and be checked on it — a type
 * resolved out of an address is not a type to take on trust.
 *
 * The feed is promised as a list and not as the relation it is built from: a relation is
 * generic in the model that declares it, so an interface could only name it as "some model"
 * and every implementation would then be narrowing it. What a reader of this contract wants is
 * the notes anyway.
 */
interface Notable
{
    /** @return list<Note> */
    public function noteFeed(): array;

    /** The permission that both reads and writes the notes of this record. */
    public function notesPermission(): string;

    public function addNote(string $body, ?int $adminId = null): Note;
}
