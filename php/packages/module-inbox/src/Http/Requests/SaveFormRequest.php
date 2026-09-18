<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Panel\FormOptions;

/**
 * A form as its editor saves it: what it is called, where it answers, and its settings (§5).
 *
 * The settings are not validated key by key. They cannot be: `thank-you.heading` is one key
 * with a dot in it, and a rule named after it would be read as a path (CLAUDE.md §4). What
 * happens instead is that the shape of the two that could be wrong — the recipients and the
 * captcha — is checked here by hand, and everything else is taken through the white list in
 * {@see FormOptions}, which drops what it does not know.
 */
final class SaveFormRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
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
                Rule::unique('inbox_forms', 'slug')->ignore($this->form()?->getKey()),
            ],
            'title' => ['required'],
            'is_enabled' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array'],
            // The one setting worth refusing rather than dropping: an address nobody will
            // ever be written to reads exactly like one that works.
            'options.recipients' => ['nullable', 'array', 'max:20', $this->recipientsAreAddressable(...)],
        ];
    }

    /**
     * Every recipient is either somebody with an account or an address that is an address.
     *
     * Written as a closure rather than as `options.recipients.*.email`, because a recipient is
     * one of two shapes and a rule per key would demand both halves of each.
     */
    private function recipientsAreAddressable(string $attribute, mixed $value, callable $fail): void
    {
        if (is_array($value) && count(FormOptions::recipients($value)) !== count($value)) {
            $fail((string) trans('webx-inbox::errors.recipient-shape'));
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.regex' => (string) trans('webx-inbox::errors.slug-shape')];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $title = $this->input('title');
        $options = $this->input('options');

        return [
            'slug' => (string) $this->string('slug'),
            // An array is the whole language map, which is how the panel edits a title;
            // a plain string is one language, and `HasTranslations` puts it in the one
            // being written.
            'title' => is_array($title) ? $title : (string) $title,
            'is_enabled' => $this->boolean('is_enabled', true),
            'options' => FormOptions::clean(is_array($options) ? $options : []),
        ];
    }

    /** The form being edited, or null while one is being created. */
    private function form(): ?Form
    {
        $form = $this->route('form');

        return $form instanceof Form ? $form : null;
    }
}
