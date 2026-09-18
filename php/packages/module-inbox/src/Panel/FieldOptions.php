<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Panel;

use WebxUi\Inbox\Fields\FieldType;

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
            FieldType::Select, FieldType::Radio => ['choices' => self::choices($input['choices'] ?? null)],
            FieldType::Checkbox => [
                'choices' => self::choices($input['choices'] ?? null),
                ...self::numbers($input, ['min', 'max']),
            ],
            FieldType::Consent => self::consent($input),
            FieldType::File => self::file($input),
            FieldType::Hidden => [],
        };

        return array_filter($options, static fn (mixed $value): bool => $value !== [] && $value !== '');
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
     * nameless one is an answer nothing can be checked against.
     *
     * @return list<array<string, mixed>>
     */
    private static function choices(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $choices = [];

        foreach ($input as $choice) {
            if (! is_array($choice)) {
                continue;
            }

            $value = isset($choice['value']) && is_scalar($choice['value']) ? trim((string) $choice['value']) : '';

            if ($value === '') {
                continue;
            }

            $label = $choice['label'] ?? null;

            $choices[] = ['value' => $value, 'label' => is_array($label) || is_string($label) ? $label : $value];
        }

        return $choices;
    }
}
