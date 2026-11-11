<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Panel;

use WebxUi\Inbox\Fields\ChoiceImages;
use WebxUi\Inbox\Fields\Conditions;
use WebxUi\Inbox\Fields\DateTimeField;
use WebxUi\Inbox\Fields\FieldType;
use WebxUi\Inbox\Fields\Summary;

/**
 * What each kind of field may be configured with (§4), and nothing else.
 *
 * Kept by type rather than merged: a `select` turned into a `textarea` should stop carrying
 * the choices nobody can see any more, and a stale `choices` array in a text field is the
 * kind of leftover that comes back as "why does the export have a column of empty strings".
 *
 * The choices keep their own shape — `{ value, label }` with the label a language map — and
 * that shape is the contract the intake validates answers against (`Rules::oneOf`), so a
 * malformed one here is a field that accepts nothing at all.
 */
final class FieldOptions
{
    /**
     * @param  array<mixed>  $input
     * @return array<string, mixed>
     */
    public static function clean(FieldType $type, array $input): array
    {
        $options = match ($type) {
            FieldType::Text, FieldType::Email => self::numbers($input, ['maxlength']),
            FieldType::Textarea => self::numbers($input, ['maxlength', 'rows']),
            FieldType::Tel => self::text($input, ['pattern']),
            FieldType::Date => self::text($input, ['min', 'max']),
            FieldType::Select => ['choices' => self::choices($input['choices'] ?? null)],
            FieldType::Radio => ['choices' => self::choices($input['choices'] ?? null, true), ...self::view($input)],
            FieldType::Checkbox => [
                'choices' => self::choices($input['choices'] ?? null, true),
                ...self::numbers($input, ['min', 'max']),
                ...self::view($input),
            ],
            FieldType::Consent => self::consent($input),
            FieldType::File => self::file($input),
            FieldType::Hidden => [],
            // The grid of a day and the days of the week (FORM_STEPS §6.2); the zone is the site's.
            FieldType::DateTime => DateTimeField::clean($input),
            // A step (FORM_STEPS §3.1): the words of its «Next», when they are not the site's own.
            FieldType::Step => ['next-text' => self::words($input['next-text'] ?? null) ?? ''],
            // The summary (FORM_STEPS §7): the names of the fields it shows, none — every one above.
            FieldType::Summary => [Summary::KEY => Summary::clean($input[Summary::KEY] ?? null)],
        };

        // When the field is shown (FORM_STEPS §4), whatever its type: a hidden field is filled in
        // by the site and never shown, so a condition on it would mean nothing; a summary shows
        // what is shown above it, so it has its condition already.
        if ($type !== FieldType::Hidden && $type !== FieldType::Summary) {
            foreach (Conditions::KEYS as $key) {
                $options[$key] = Conditions::clean($input[$key] ?? null) ?? [];
            }
        }

        return array_filter($options, static fn (mixed $value): bool => $value !== [] && $value !== '');
    }

    /**
     * How a group of radios or ticks is drawn (FORM_STEPS §5.1): `view: cards` and the columns
     * the cards stand in, 1–4. A list is the default and is not written down; columns without
     * cards mean nothing and are dropped with them.
     *
     * @param  array<mixed>  $input
     * @return array<string, mixed>
     */
    private static function view(array $input): array
    {
        if (($input['view'] ?? null) !== 'cards') {
            return [];
        }

        $columns = $input['columns'] ?? null;

        return [
            'view' => 'cards',
            'columns' => is_numeric($columns) ? max(1, min(4, (int) $columns)) : '',
        ];
    }

    /**
     * @param  array<mixed>  $input
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private static function numbers(array $input, array $keys): array
    {
        $options = [];

        foreach ($keys as $key) {
            if (isset($input[$key]) && is_numeric($input[$key]) && (int) $input[$key] > 0) {
                $options[$key] = (int) $input[$key];
            }
        }

        return $options;
    }

    /**
     * @param  array<mixed>  $input
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private static function text(array $input, array $keys): array
    {
        $options = [];

        foreach ($keys as $key) {
            if (isset($input[$key]) && is_scalar($input[$key])) {
                $options[$key] = trim((string) $input[$key]);
            }
        }

        return $options;
    }

    /**
     * @param  array<mixed>  $input
     * @return array<string, mixed>
     */
    private static function consent(array $input): array
    {
        $text = $input['text'] ?? null;

        return ['text' => is_array($text) || is_string($text) ? $text : ''];
    }

    /**
     * @param  array<mixed>  $input
     * @return array<string, mixed>
     */
    private static function file(array $input): array
    {
        $extensions = [];

        foreach ((array) ($input['extensions'] ?? []) as $extension) {
            if (is_string($extension) && trim($extension) !== '') {
                $extensions[] = strtolower(ltrim(trim($extension), '.'));
            }
        }

        return [
            ...self::numbers($input, ['max_size']),
            'extensions' => array_values(array_unique($extensions)),
            // Written even when false, because "one file" is a decision and the default it
            // would otherwise fall back to belongs to the site rather than to this field.
            'multiple' => (bool) ($input['multiple'] ?? false),
        ];
    }

    /**
     * The written-down answers: a value that is stored and a label that is read.
     *
     * A choice with no value is dropped — `Rules::oneOf` builds `in:` out of the values, so a
     * nameless one is an answer nothing can be checked against. A plain string is a choice
     * whose value and label are the same words: `["A", "B"]` is what anybody writing a list
     * by hand sends first, and dropping it without a word was a select with nothing in it.
     *
     * A radio or a tick may carry what a card shows (`$cards`): a description — words in every
     * language, like the label — and a picture of the media library, kept by its key. Kept with
     * a list too, so that switching to cards and back loses nothing; neither goes into an answer.
     *
     * @return list<array<string, mixed>>
     */
    private static function choices(mixed $input, bool $cards = false): array
    {
        if (! is_array($input)) {
            return [];
        }

        $choices = [];

        foreach ($input as $choice) {
            if (is_string($choice) || is_int($choice)) {
                $choice = ['value' => (string) $choice];
            }

            if (! is_array($choice)) {
                continue;
            }

            $value = isset($choice['value']) && is_scalar($choice['value']) ? trim((string) $choice['value']) : '';

            if ($value === '') {
                continue;
            }

            $label = $choice['label'] ?? null;

            $kept = ['value' => $value, 'label' => is_array($label) || is_string($label) ? $label : $value];

            if ($cards) {
                $description = self::words($choice['description'] ?? null);
                $image = ChoiceImages::store($choice['image'] ?? null);

                if ($description !== null) {
                    $kept['description'] = $description;
                }

                if ($image !== null) {
                    $kept['image'] = $image;
                }
            }

            $choices[] = $kept;
        }

        return $choices;
    }

    /**
     * Words in every language, or in one: the blank ones dropped, nothing at all when none is left.
     *
     * @return array<string, string>|string|null
     */
    private static function words(mixed $input): array|string|null
    {
        if (is_string($input)) {
            return trim($input) === '' ? null : trim($input);
        }

        if (! is_array($input)) {
            return null;
        }

        $words = [];

        foreach ($input as $locale => $line) {
            if (is_string($locale) && is_scalar($line) && trim((string) $line) !== '') {
                $words[$locale] = trim((string) $line);
            }
        }

        return $words === [] ? null : $words;
    }
}
