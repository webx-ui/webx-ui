<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Panel\FieldOptions;

/**
 * One question of a form, as the dialog saves it.
 *
 * The machine name is the part with rules behind it (§2.6). It has to survive being written
 * into HTML as `fields[name]` and read back out as `fields.name`, which is why a dot is
 * refused: a name with one in it would make the validator of the intake look for a nested
 * array and report the error under a key nothing on the page has.
 *
 * It also has to be unique — among the live fields of this form only. A deleted field keeps
 * its name in the table so that the answers pointing at it still read (§2.3), and a name
 * reserved for ever by a field nobody can see is a name whose owner cannot be found.
 */
final class SaveFieldRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_-]*$/i',
                Rule::unique('inbox_form_fields', 'name')
                    ->where('form_id', $this->formId())
                    ->whereNull('deleted_at')
                    ->ignore($this->field()?->getKey()),
            ],
            'type' => ['required', Rule::enum(FieldType::class)],
            'title' => ['required'],
            'placeholder' => ['nullable'],
            'help' => ['nullable'],
            'options' => ['nullable', 'array'],
            'is_enabled' => ['nullable', 'boolean'],
            'is_required' => ['nullable', 'boolean'],
            'is_fullsize' => ['nullable', 'boolean'],
            'in_table' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.regex' => (string) trans('webx-inbox::errors.field-name-shape')];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $type = FieldType::from((string) $this->string('type'));
        $name = trim((string) $this->string('name'));
        $options = $this->input('options');

        return [
            'name' => $name === '' ? null : $name,
            'type' => $type,
            'title' => $this->words('title'),
            'placeholder' => $this->words('placeholder'),
            'help' => $this->words('help'),
            'options' => FieldOptions::clean($type, is_array($options) ? $options : []),
            'is_enabled' => $this->boolean('is_enabled', true),
            'is_required' => $this->boolean('is_required'),
            // Most fields take the whole line, and a form of half-width fields is the rarer
            // thing to ask for; the default matches the column of the migration.
            'is_fullsize' => $this->boolean('is_fullsize', true),
            'in_table' => $this->boolean('in_table'),
        ];
    }

    /**
     * A language map as the panel edits it, or one line for a caller that sent one.
     *
     * @return array<string, mixed>|string
     */
    private function words(string $key): array|string
    {
        $value = $this->input($key);

        return is_array($value) ? $value : (string) $value;
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
