<?php

declare(strict_types=1);

namespace WebxUi\Blocks\Panel;

use Closure;
use Illuminate\Validation\Rule;
use WebxUi\Admin\Screens\ScreenValidator;
use WebxUi\Blocks\Models\Block;
use WebxUi\Blocks\Models\BlockVersion;

/**
 * A block type as it arrives from outside — the panel's form, an agent's tool call, a file
 * being imported — checked and normalised by one set of rules.
 *
 * Three doors, one lock: the slug pattern, the group list, the schema check and the size
 * limits are the same whoever is writing, and a file that imports is a file the panel would
 * have accepted. The row's fields and the content of the next version are separate on
 * purpose: the panel sends the content under `content` so that a save of the settings alone
 * does not write a version, while an agent and a file send everything flat.
 */
final class BlockInput
{
    /**
     * The rules of the row's own fields.
     *
     * @return array<string, list<mixed>>
     */
    public static function rowRules(bool $creating, ?int $ignoreId = null): array
    {
        $groups = config('webx-blocks.groups', []);
        $slug = ['string', 'max:64', 'regex:/^[a-z][a-z0-9-]*$/'];

        return [
            'slug' => [$creating ? 'required' : 'sometimes', ...$slug, Rule::unique('blocks', 'slug')->ignore($ignoreId)],
            'kind' => ['sometimes', 'string', Rule::in(Block::KINDS)],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
            'group' => ['sometimes', 'string', Rule::in(is_array($groups) ? $groups : [])],
            'sort' => ['nullable', 'integer', 'between:-32768,32767'],
            'allow' => ['nullable', 'array'],
            'allow.*' => $slug,
            'allowed_in' => ['nullable', 'array'],
            'allowed_in.*' => $slug,
            'max_per_entity' => ['nullable', 'integer', 'min:1', 'max:32767'],
            'is_enabled' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The rules of a version's content, under a prefix: `content.` where the panel sends it,
     * none where it comes flat.
     *
     * @return array<string, list<mixed>>
     */
    public static function contentRules(string $prefix = ''): array
    {
        return [
            $prefix.'schema' => ['sometimes', 'array', self::schemaRule()],
            $prefix.'template' => ['sometimes', 'nullable', 'string', 'max:200000'],
            $prefix.'styles' => ['sometimes', 'nullable', 'string', 'max:200000'],
            $prefix.'script' => ['sometimes', 'nullable', 'string', 'max:200000'],
            $prefix.'sample' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'slug.regex' => (string) __('webx-blocks::validation.slug'),
            'slug.unique' => (string) __('webx-blocks::validation.slug-taken'),
            'group.in' => (string) __('webx-blocks::validation.group'),
            'kind.in' => (string) __('webx-blocks::calls.kind'),
            'allow.*.regex' => (string) __('webx-blocks::validation.slug'),
            'allowed_in.*.regex' => (string) __('webx-blocks::validation.slug'),
        ];
    }

    /**
     * The row's fields, only the ones that were sent, trimmed and typed.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function values(array $input): array
    {
        $values = [];

        foreach (['slug', 'title', 'description', 'icon', 'group'] as $text) {
            if (array_key_exists($text, $input)) {
                $value = $input[$text];
                $value = is_string($value) ? trim($value) : null;
                $values[$text] = $value === '' ? null : $value;
            }
        }

        if (is_string($input['kind'] ?? null) && in_array($input['kind'], Block::KINDS, true)) {
            $values['kind'] = $input['kind'];
        }

        if (array_key_exists('sort', $input)) {
            $values['sort'] = (int) ($input['sort'] ?? 0);
        }

        foreach (['allow', 'allowed_in'] as $list) {
            if (array_key_exists($list, $input)) {
                $value = $input[$list];
                $values[$list] = is_array($value) ? array_values(array_unique(array_map('strval', $value))) : null;
            }
        }

        if (array_key_exists('max_per_entity', $input)) {
            $max = $input['max_per_entity'];
            $values['max_per_entity'] = $max === null || $max === '' ? null : (int) $max;
        }

        if (array_key_exists('is_enabled', $input)) {
            $values['is_enabled'] = filter_var($input['is_enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        }

        return $values;
    }

    /**
     * The next version's content, only the fields that were sent, in the shape a version keeps.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public static function content(array $content): array
    {
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

    /**
     * Why a type may not become what it is asked to become, or null when it may (§3.1 of the
     * components spec). A block that stands in content cannot turn into a component: the
     * picker would stop offering it while the pages kept it, and its form would be its input
     * rather than what an editor fills in. The other way is always fine — a block can be
     * called by a tag anyway.
     *
     * @param  array<string, int>  $usage  Slug → number of pages, {@see Usage::counts()}.
     */
    public static function kindRefusal(Block $block, ?string $kind, array $usage): ?string
    {
        if ($kind !== Block::KIND_COMPONENT || $block->kind === Block::KIND_COMPONENT || ! $block->exists) {
            return null;
        }

        $count = $usage[$block->slug] ?? 0;

        return $count > 0 ? (string) __('webx-blocks::calls.kind-in-use', ['count' => $count]) : null;
    }

    public static function comment(mixed $comment): ?string
    {
        $comment = is_string($comment) ? trim($comment) : '';

        return $comment === '' ? null : $comment;
    }

    /**
     * A schema is a list of screen nodes, checked by the same rules a screen file is — and
     * every node needs an `id`, because the id is the field's key in the values.
     */
    private static function schemaRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            $problems = ScreenValidator::screen($value);

            if ($problems !== []) {
                $fail((string) __('webx-blocks::validation.schema', ['problems' => implode('; ', array_slice($problems, 0, 3))]));
            }
        };
    }
}
