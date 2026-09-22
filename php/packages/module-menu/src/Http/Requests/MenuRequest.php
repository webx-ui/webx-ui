<?php

declare(strict_types=1);

namespace WebxUi\Menu\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A menu of somebody's own: a key and a name (§9).
 *
 * The name is written in the language the panel is open in and nothing else. It is only ever
 * read in the panel, so a dialog asking for ten of them before the menu exists would be ten
 * fields nobody fills in.
 */
final class MenuRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // The key of a menu being renamed is its own, so the row it is on is left out of the
        // uniqueness check — otherwise saving a menu without touching its key would refuse.
        $current = $this->route('key');

        return [
            'key' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:64',
                // The spelling a template writes inside `menu('…')`: lower case, no spaces.
                'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/',
                Rule::unique('menus', 'key')->ignore(is_string($current) ? $current : '', 'key'),
            ],
            'title' => ['required', 'string', 'max:190'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['key.regex' => (string) __('webx-menu::errors.key-shape')];
    }

    public function key(): string
    {
        return trim((string) $this->input('key'));
    }

    public function title(): string
    {
        return trim((string) $this->input('title'));
    }
}
