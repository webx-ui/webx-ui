<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Screens;

use WebxUi\Admin\Screens\ChecksNewValues;
use WebxUi\Admin\Screens\FieldType;
use WebxUi\Inbox\Models\Form;

/**
 * `wx-inbox-form`: one form of the Inbox, by its slug — what `<x-webx-inbox::form slug="…" />`
 * takes, and what the blocks that print a form have always kept as typed text. The value stays
 * the slug, so a block written before the picker reads the same and nothing has to be migrated.
 *
 * A slug no form has is refused, but only when it is being written ({@see ChecksNewValues}): a
 * form deleted after the page was saved must not stop the page from being saved again — the
 * picker draws it as missing and the editor takes it out — while a slug typed by an agent or
 * mistyped by hand is the form that silently never appears, which is what this type is for.
 * A form that is switched off is a form: the site prints nothing for it until it is back.
 *
 * Empty is "no form", stored as null.
 */
final class FormFieldType implements ChecksNewValues, FieldType
{
    /** How many slugs a refusal lists: enough to pick from, not a page of them. */
    private const LISTED = 20;

    /**
     * @param  array<string, mixed>  $node
     * @return list<mixed>
     */
    public function rules(array $node): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function store(mixed $value, array $node): ?string
    {
        return self::slug($value);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    public function resolve(mixed $stored, array $node, ?string $locale = null): ?string
    {
        return self::slug($stored);
    }

    /**
     * A slug no form has, in a value written now — with the slugs there are, so that whoever
     * wrote it, an agent most of all, can put the right one in the next call.
     *
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    public function newValueProblems(mixed $value, array $node): array
    {
        $slug = self::slug($value);

        if ($slug === null || Form::query()->where('slug', $slug)->exists()) {
            return [];
        }

        $forms = Form::query()->orderBy('position')->orderBy('id')->limit(self::LISTED)->pluck('slug')->all();

        return [$forms === []
            ? (string) __('webx-inbox::relations.unknown-none', ['slug' => $slug])
            : (string) __('webx-inbox::relations.unknown', ['slug' => $slug, 'forms' => implode(', ', $forms)])];
    }

    private static function slug(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $slug = trim($value);

        return $slug === '' ? null : $slug;
    }
}
