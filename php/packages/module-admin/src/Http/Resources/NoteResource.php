<?php

declare(strict_types=1);

namespace WebxUi\Admin\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Admin\Notes\Note;
use WebxUi\Admin\Support\Authors;

/**
 * One note, with the name and the photograph of whoever wrote it and whether the reader is that
 * person.
 *
 * `is_mine` is answered here rather than compared in the browser: the panel knows the reader's
 * id, but a feed that decided for itself who may edit a line would be a rule written twice,
 * and the second copy is the one that drifts.
 *
 * @mixin Note
 */
final class NoteResource extends JsonResource
{
    /**
     * @param  array<int, array{name: string, avatar: string|null}>  $people  admin id → name and photograph,
     *                                                                        resolved for the whole feed at once
     */
    public function __construct(Note $note, private readonly array $people = [])
    {
        parent::__construct($note);
    }

    /**
     * The feed of a record, with every author named and pictured in one query.
     *
     * @param  iterable<array-key, Note>  $notes
     * @return list<self>
     */
    public static function feed(iterable $notes, mixed $reader): array
    {
        $notes = is_array($notes) ? $notes : iterator_to_array($notes);
        $people = Authors::people($reader, array_map(static fn (Note $note): ?int => $note->admin_id, $notes));

        return array_values(array_map(static fn (Note $note): self => new self($note, $people), $notes));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Note $note */
        $note = $this->resource;

        return [
            'id' => (int) $note->getKey(),
            'body' => $note->body,
            'author' => $note->admin_id === null ? null : [
                'id' => $note->admin_id,
                // Null where the account has been deleted: the note stays, its author does not.
                'name' => $this->people[$note->admin_id]['name'] ?? null,
                'avatar' => $this->people[$note->admin_id]['avatar'] ?? null,
            ],
            'is_mine' => $note->writtenBy($request->user()),
            'created_at' => $note->created_at?->toAtomString(),
            'updated_at' => $note->updated_at?->toAtomString(),
        ];
    }
}
