<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebxUi\Admin\Screens\ScreenValidator;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;

/**
 * A block type as the panel sends it: the row's own fields, and — when the editor touched
 * them — the content of the next version.
 *
 * `POST` needs a slug and a name and nothing else: a new type starts as an empty draft. `PUT`
 * takes whatever changed. The content travels under `content` so that a save of the settings
 * tab alone does not write a version that differs from the last one in nothing.
 */
final class BlockRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $current = $this->route('block');
        $currentId = $current instanceof Block ? $current->id : null;

        $groups = config('webx-blocks.groups', []);

        return [
            'slug' => [
                $creating ? 'required' : 'sometimes',
                'string',
                'max:64',
                'regex:/^[a-z][a-z0-9-]*$/',
                Rule::unique('blocks', 'slug')->ignore($currentId),
            ],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
            'group' => ['sometimes', 'string', Rule::in(is_array($groups) ? $groups : [])],
            'sort' => ['nullable', 'integer', 'between:-32768,32767'],
            'allow' => ['nullable', 'array'],
            'allow.*' => ['string', 'max:64', 'regex:/^[a-z][a-z0-9-]*$/'],
            'allowed_in' => ['nullable', 'array'],
            'allowed_in.*' => ['string', 'max:64', 'regex:/^[a-z][a-z0-9-]*$/'],
            'max_per_entity' => ['nullable', 'integer', 'min:1', 'max:32767'],
            'is_enabled' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string', 'max:255'],

            'content' => ['sometimes', 'array:'.implode(',', BlockVersion::CONTENT)],
            'content.schema' => ['sometimes', 'array', $this->schemaRule()],
            'content.template' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'content.styles' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'content.script' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'content.sample' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => (string) __('webx-blocks::validation.slug'),
            'slug.unique' => (string) __('webx-blocks::validation.slug-taken'),
            'group.in' => (string) __('webx-blocks::validation.group'),
            'allow.*.regex' => (string) __('webx-blocks::validation.slug'),
            'allowed_in.*.regex' => (string) __('webx-blocks::validation.slug'),
        ];
    }

    /**
     * The row's fields, only the ones that were sent.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $values = [];

        foreach (['slug', 'title', 'description', 'icon', 'group'] as $text) {
            if ($this->has($text)) {
                $value = $this->input($text);
                $value = is_string($value) ? trim($value) : null;
                $values[$text] = $value === '' ? null : $value;
            }
        }

        if ($this->has('sort')) {
            $values['sort'] = (int) $this->input('sort', 0);
        }

        foreach (['allow', 'allowed_in'] as $list) {
            if ($this->has($list)) {
                $value = $this->input($list);
                $values[$list] = is_array($value) ? array_values(array_unique(array_map('strval', $value))) : null;
            }
        }

        if ($this->has('max_per_entity')) {
            $max = $this->input('max_per_entity');
            $values['max_per_entity'] = $max === null || $max === '' ? null : (int) $max;
        }

        if ($this->has('is_enabled')) {
            $values['is_enabled'] = $this->boolean('is_enabled', true);
        }

        return $values;
    }

    /**
     * The next version's content, or null when the request carried none.
     *
     * @return array<string, mixed>|null
     */
    public function content(): ?array
    {
        $content = $this->input('content');

        if (! is_array($content)) {
            return null;
        }

        $kept = [];

        foreach (BlockVersion::CONTENT as $field) {
            if (! array_key_exists($field, $content)) {
                continue;
            }

            $kept[$field] = match ($field) {
                'schema' => is_array($content[$field]) ? array_values($content[$field]) : [],
                'sample' => is_array($content[$field]) ? $content[$field] : [],
                'script' => is_string($content[$field]) && trim($content[$field]) !== '' ? $content[$field] : null,
                default => is_string($content[$field]) ? $content[$field] : '',
            };
        }

        return $kept;
    }

    public function comment(): ?string
    {
        $comment = $this->input('comment');
        $comment = is_string($comment) ? trim($comment) : '';

        return $comment === '' ? null : $comment;
    }

    /**
     * A schema is a list of screen nodes, checked by the same rules a screen file is — and
     * every node needs an `id`, because the id is the field's key in the values.
     */
    private function schemaRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            $problems = ScreenValidator::screen($value);

            if ($problems !== []) {
                $fail((string) __('webx-blocks::validation.schema', ['problems' => implode('; ', array_slice($problems, 0, 3))]));
            }
        };
    }
}
