<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use WebxUi\Inbox\Models\Field;

/**
 * One question of a form, as its editor needs it.
 *
 * The words travel as whole language maps rather than as the line the panel happens to be
 * reading in: this is a form that edits every language at once, and a resource that answered
 * with one of them would quietly drop the rest on the next save.
 *
 * `key` is beside `name` because they are not the same thing: `name` is what somebody typed,
 * `key` is what the HTML will actually say — `f17` for a field nobody named (§2.6). The
 * embedding tab prints the second one.
 *
 * @mixin Field
 */
final class FieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Field $field */
        $field = $this->resource;

        return [
            'id' => (int) $field->getKey(),
            'form_id' => $field->form_id,
            'name' => $field->name,
            'key' => $field->key(),
            'type' => $field->type->value,
            'title' => $field->getTranslations('title'),
            'placeholder' => $field->getTranslations('placeholder'),
            'help' => $field->getTranslations('help'),
            'options' => $field->options ?? [],
            'is_enabled' => $field->is_enabled,
            'is_required' => $field->is_required,
            'is_fullsize' => $field->is_fullsize,
            'in_table' => $field->in_table,
            'position' => $field->position,
        ];
    }
}
