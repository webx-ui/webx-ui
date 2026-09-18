<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Panel\FieldInput;

/**
 * One question of a form, as the dialog saves it.
 *
 * The rules and the row are {@see FieldInput} — an agent writes fields through the same pair
 * (§14), and a name refused in the panel has to be refused there too. What stays here is
 * which form and which field the address named.
 */
final class SaveFieldRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $field = $this->field();

        return FieldInput::rules($this->formId(), $field === null ? null : (int) $field->getKey());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return FieldInput::messages();
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return FieldInput::values($this->all());
    }

    private function formId(): ?int
    {
        $form = $this->route('form');

        if ($form instanceof Form) {
            return (int) $form->getKey();
        }

        return $this->field()?->form_id;
    }

    /** The field being edited, or null while one is being created. */
    private function field(): ?Field
    {
        $field = $this->route('field');

        return $field instanceof Field ? $field : null;
    }
}
