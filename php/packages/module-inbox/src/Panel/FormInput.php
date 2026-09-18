<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Panel;

use Illuminate\Validation\Rule;
use WebxUi\Inbox\Http\Requests\SaveFormRequest;

/**
 * What a saved form has to be, and what it becomes — apart from the request that carried it.
 *
 * The panel arrives through {@see SaveFormRequest} and an agent
 * through `inbox_form_save`, and both have to be refused for the same reasons: a slug that is
 * not an address, a recipient nobody can be written to. Rules that lived in the form request
 * would be rules the agent's door does not have, which is the way a second door becomes a
 * second set of rules.
 */
final class FormInput
{
    /**
     * @param  int|null  $formId  the form being edited, so its own slug is not "taken"
     * @return array<string, mixed>
     */
    public static function rules(?int $formId = null): array
    {
        return [
            // Lower case, because it is an address: `webx/forms/Contact` and
            // `webx/forms/contact` would be two doors to one form on a case-sensitive server
            // and one door on the other kind.
            'slug' => [
                'required',
                'string',
                'max:96',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('inbox_forms', 'slug')->ignore($formId),
            ],
            'title' => ['required'],
            'is_enabled' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array'],
            // The one setting worth refusing rather than dropping: an address nobody will
            // ever be written to reads exactly like one that works.
            'options.recipients' => ['nullable', 'array', 'max:20', self::recipientsAreAddressable(...)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return ['slug.regex' => (string) trans('webx-inbox::errors.slug-shape')];
    }

    /**
     * The row, out of what arrived.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function values(array $input): array
    {
        $title = $input['title'] ?? null;
        $options = $input['options'] ?? null;

        return [
            'slug' => (string) ($input['slug'] ?? ''),
            // An array is the whole language map, which is how the panel edits a title;
            // a plain string is one language, and `HasTranslations` puts it in the one
            // being written.
            'title' => is_array($title) ? $title : (string) $title,
            'is_enabled' => self::boolean($input, 'is_enabled', true),
            'options' => FormOptions::clean(is_array($options) ? $options : []),
        ];
    }

    /**
     * Every recipient is either somebody with an account or an address that is an address.
     *
     * Written as a closure rather than as `options.recipients.*.email`, because a recipient is
     * one of two shapes and a rule per key would demand both halves of each.
     */
    private static function recipientsAreAddressable(string $attribute, mixed $value, callable $fail): void
    {
        if (is_array($value) && count(FormOptions::recipients($value)) !== count($value)) {
            $fail((string) trans('webx-inbox::errors.recipient-shape'));
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function boolean(array $input, string $key, bool $default = false): bool
    {
        return (bool) filter_var($input[$key] ?? $default, FILTER_VALIDATE_BOOLEAN);
    }
}
