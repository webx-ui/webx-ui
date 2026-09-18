<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use WebxUi\Inbox\Models\Form;
use WebxUi\Inbox\Panel\FormInput;
use WebxUi\Inbox\Panel\FormOptions;

/**
 * A form as its editor saves it: what it is called, where it answers, and its settings (§5).
 *
 * The rules and the row are {@see FormInput}, because an agent saves a form through the same
 * pair and the two doors have to refuse the same things (§14). What is left here is the one
 * thing a request knows and a plain array does not: which form is being edited, so that its
 * own slug is not counted as taken.
 *
 * The settings are not validated key by key. They cannot be: `thank-you.heading` is one key
 * with a dot in it, and a rule named after it would be read as a path (CLAUDE.md §4). What
 * happens instead is that the shape of the two that could be wrong — the recipients and the
 * captcha — is checked by hand, and everything else is taken through the white list in
 * {@see FormOptions}, which drops what it does not know.
 */
final class SaveFormRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $form = $this->form();

        return FormInput::rules($form === null ? null : (int) $form->getKey());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return FormInput::messages();
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return FormInput::values($this->all());
    }

    /** The form being edited, or null while one is being created. */
    private function form(): ?Form
    {
        $form = $this->route('form');

        return $form instanceof Form ? $form : null;
    }
}
