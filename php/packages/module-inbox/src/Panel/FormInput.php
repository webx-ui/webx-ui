<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Panel;

use Closure;
use Illuminate\Validation\Rule;
use WebxUi\Auth\Models\CmsUser;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Http\Requests\SaveFormRequest;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;

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
     * @param  list<string>|null  $emailFields  the machine names of the form's e-mail fields
     *                                          as they will be after this save; the ones in the
     *                                          table when null
     * @return array<string, mixed>
     */
    public static function rules(?int $formId = null, ?array $emailFields = null): array
    {
        $form = $formId === null ? null : Form::query()->find($formId);
        $stored = $form instanceof Form ? (array) ($form->options ?? []) : [];

        $emailFields ??= $form instanceof Form
            ? $form->fields()->get()
                ->filter(static fn (Field $field): bool => $field->type === FieldType::Email)
                ->map(static fn (Field $field): string => $field->key())
                ->values()
                ->all()
            : [];

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
            'options.recipients' => ['nullable', 'array', 'max:20', self::recipientsAreAddressable(self::adminIds($stored))],
            // The field whose answer becomes the Reply-To. One the form does not have, or one
            // that is not an e-mail field, is a Reply-To quietly lost on every letter. What is
            // already stored passes: a field taken out since is not a reason to refuse saving
            // the rest of the settings.
            'options.email_field' => ['nullable', 'string', static function (string $attribute, mixed $value, Closure $fail) use ($stored, $emailFields): void {
                if ($value === ($stored['email_field'] ?? null) || in_array($value, $emailFields, true)) {
                    return;
                }

                $fail((string) trans('webx-inbox::errors.email-field'));
            }],
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
     * Every recipient is either somebody with an account or an address that is an address —
     * and the refusal says which one is neither, by its place in the list and what it held.
     *
     * Written as a closure rather than as `options.recipients.*.email`, because a recipient is
     * one of two shapes and a rule per key would demand both halves of each.
     *
     * An administrator id is checked only when it is new to the form. One already on it whose
     * account has since been deleted is shown as such (`Recipients`, the audit) and kept: that
     * is the form telling somebody, and a refusal to save anything else until they noticed
     * would be the wrong way to tell them. An id being added that never had an account is a
     * typo, refused like an address that is not one.
     *
     * @param  list<int>  $known  the administrators the form names already
     */
    private static function recipientsAreAddressable(array $known): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($known): void {
            if (! is_array($value)) {
                return;
            }

            foreach (array_values($value) as $index => $recipient) {
                $entry = '#'.($index + 1).' ('.self::describe($recipient).')';
                $kept = FormOptions::recipients([$recipient]);

                if ($kept === []) {
                    $fail((string) trans('webx-inbox::errors.recipient-shape', ['entry' => $entry]));

                    continue;
                }

                $admin = $kept[0]['admin_id'] ?? null;

                if (is_int($admin) && ! in_array($admin, $known, true) && ! CmsUser::query()->whereKey($admin)->exists()) {
                    $fail((string) trans('webx-inbox::errors.recipient-unknown', ['entry' => $entry]));
                }
            }
        };
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return list<int>
     */
    private static function adminIds(array $stored): array
    {
        return array_values(array_filter(array_map(
            static fn (array $recipient): ?int => $recipient['admin_id'] ?? null,
            FormOptions::recipients($stored['recipients'] ?? null),
        )));
    }

    /** One recipient as the refusal names it: what was sent, short. */
    private static function describe(mixed $recipient): string
    {
        $text = json_encode($recipient, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return mb_strimwidth($text === false ? '?' : $text, 0, 80, '…');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function boolean(array $input, string $key, bool $default = false): bool
    {
        return (bool) filter_var($input[$key] ?? $default, FILTER_VALIDATE_BOOLEAN);
    }
}
