<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Inbox\Models\Form;

/**
 * One form: a row of the left column, and the record its editor saves.
 *
 * Two counts travel with it, because they are what the column is for — how much has ever come
 * through this form, and how much of it nobody has looked at yet. They are counted in the
 * query that lists the forms, so a panel with thirty of them still costs two queries rather
 * than sixty-one; a form loaded on its own has neither, and says so with a null rather than
 * with a zero that would be a claim.
 *
 * `fields` is here only when they were loaded: the column does not need them and the editor
 * cannot do without them.
 *
 * @mixin Form
 */
final class FormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Form $form */
        $form = $this->resource;

        return [
            'id' => (int) $form->getKey(),
            'slug' => $form->slug,
            'title' => $form->getTranslations('title'),
            'is_enabled' => $form->is_enabled,
            'options' => $form->options ?? [],
            'position' => $form->position,
            'submissions_count' => $this->count($form, 'submissions_count'),
            'unread_count' => $this->count($form, 'unread_count'),
            'fields' => FieldResource::collection($this->whenLoaded('fields')),
            'created_at' => $form->created_at?->toAtomString(),
            'updated_at' => $form->updated_at?->toAtomString(),
        ];
    }

    /** A number the query asked for, or null where nobody counted. */
    private function count(Form $form, string $attribute): ?int
    {
        $value = $form->getAttribute($attribute);

        return $value === null ? null : (int) $value;
    }
}
