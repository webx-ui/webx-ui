<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Fields;

use WebxUi\Inbox\Models\Field;

/**
 * The summary of a form (FORM_STEPS §7): a row of type `summary` asks nothing and shows the answers
 * given above it, each with «Edit». Not a question — no rules, no answer, no column, nothing of it
 * in a submission: it repeats the answers, it is not one.
 *
 * `options.fields` names the fields it shows; none — every field above it that is shown and
 * answered. A consent, a file and a hidden field are never among them: a tick of the terms, a file
 * and what the site filled in are nothing a visitor checks before sending.
 */
final class Summary
{
    public const string KEY = 'fields';

    /** The kinds of field a summary does not show. */
    private const array SKIPPED = [FieldType::Consent, FieldType::File, FieldType::Hidden, FieldType::Step, FieldType::Summary];

    /** Whether a summary may show a field of this kind. */
    public static function shows(FieldType $type): bool
    {
        return ! in_array($type, self::SKIPPED, true);
    }

    /**
     * The names the options hold: a list of field names, the blank and the doubled dropped.
     *
     * @return list<string>
     */
    public static function clean(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $names = [];

        foreach ($input as $name) {
            if (is_string($name) && trim($name) !== '') {
                $names[] = trim($name);
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * The keys of the fields a summary shows, of those the form has: null for «every field above».
     * A name of a field that is gone, or of a kind it never shows, is dropped — the summary does
     * not take the page down over a field deleted after it was chosen; none left — an empty list,
     * and the summary is not printed.
     *
     * @param  iterable<Field>  $questions  the fields the form asks
     * @return list<string>|null
     */
    public static function fieldsOf(Field $summary, iterable $questions): ?array
    {
        $named = self::clean($summary->option(self::KEY));

        if ($named === []) {
            return null;
        }

        $shown = [];

        foreach ($questions as $field) {
            if (self::shows($field->type) && in_array($field->key(), $named, true)) {
                $shown[] = $field->key();
            }
        }

        return $shown;
    }

    /**
     * The options with a field renamed: a summary that names it keeps naming it.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function rename(array $options, string $old, string $new): array
    {
        if (! is_array($options[self::KEY] ?? null) || ! in_array($old, $options[self::KEY], true)) {
            return $options;
        }

        $options[self::KEY] = array_values(array_unique(array_map(
            static fn (mixed $name): mixed => $name === $old ? $new : $name,
            $options[self::KEY],
        )));

        return $options;
    }
}
