<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Panel;

use Illuminate\Validation\Rule;
use WebxUi\Inbox\Fields\FieldType;

/**
 * One question of a form, apart from the request that carried it — the pair of
 * {@see FormInput}, and for the same reason.
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
final class FieldInput
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?int $formId = null, ?int $fieldId = null): array
    {
        return [
            'name' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9_-]*$/i',
                Rule::unique('inbox_form_fields', 'name')
                    ->where('form_id', $formId)
                    ->whereNull('deleted_at')
                    ->ignore($fieldId),
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
    public static function messages(): array
    {
        return ['name.regex' => (string) trans('webx-inbox::errors.field-name-shape')];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function values(array $input): array
    {
        $type = FieldType::from((string) ($input['type'] ?? ''));
        $name = trim((string) ($input['name'] ?? ''));
        $options = $input['options'] ?? null;

        return [
            'name' => $name === '' ? null : $name,
            'type' => $type,
            'title' => self::words($input, 'title'),
            'placeholder' => self::words($input, 'placeholder'),
            'help' => self::words($input, 'help'),
            'options' => FieldOptions::clean($type, is_array($options) ? $options : []),
            'is_enabled' => self::boolean($input, 'is_enabled', true),
            'is_required' => self::boolean($input, 'is_required'),
            // Most fields take the whole line, and a form of half-width fields is the rarer
            // thing to ask for; the default matches the column of the migration.
            'is_fullsize' => self::boolean($input, 'is_fullsize', true),
            'in_table' => self::boolean($input, 'in_table'),
        ];
    }

    /**
     * A language map as the panel edits it, or one line for a caller that sent one.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|string
     */
    private static function words(array $input, string $key): array|string
    {
        $value = $input[$key] ?? null;

        return is_array($value) ? $value : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function boolean(array $input, string $key, bool $default = false): bool
    {
        return (bool) filter_var($input[$key] ?? $default, FILTER_VALIDATE_BOOLEAN);
    }
}
