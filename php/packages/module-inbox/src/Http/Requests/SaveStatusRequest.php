<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebxUi\Inbox\Models\Status;

/**
 * A state a submission can be in.
 *
 * The colour is a tone of `WxBadge` and not a hex value: the panel has a light theme and a
 * dark one, and a colour picked in one of them is unreadable in the other.
 *
 * Nothing here enforces "exactly one default" — the model does, by clearing the flag on the
 * others when one is saved with it. A rule here would instead refuse the save, which is the
 * wrong answer to somebody moving the default from one status to another.
 */
final class SaveStatusRequest extends FormRequest
{
    /** The tones `WxBadge` draws. */
    public const COLORS = ['default', 'primary', 'success', 'warning', 'info', 'danger'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'key' => [
                'required',
                'string',
                'max:32',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('inbox_statuses', 'key')->ignore($this->status()?->getKey()),
            ],
            'title' => ['required'],
            'color' => ['nullable', Rule::in(self::COLORS)],
            'is_default' => ['nullable', 'boolean'],
            'is_spam' => ['nullable', 'boolean'],
            'is_closed' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['key.regex' => (string) trans('webx-inbox::errors.slug-shape')];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $title = $this->input('title');

        return [
            'key' => (string) $this->string('key'),
            'title' => is_array($title) ? $title : (string) $title,
            'color' => (string) $this->string('color', 'default'),
            'is_default' => $this->boolean('is_default'),
            'is_spam' => $this->boolean('is_spam'),
            // Spam is closed whatever the form said: it is the one status nothing further is
            // ever expected of, and a spam status left open would keep counting towards the
            // work still to do.
            'is_closed' => $this->boolean('is_closed') || $this->boolean('is_spam'),
        ];
    }

    private function status(): ?Status
    {
        $status = $this->route('status');

        return $status instanceof Status ? $status : null;
    }
}
