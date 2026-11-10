<?php

declare(strict_types=1);

namespace WebxUi\Configurator\Schema;

use WebxUi\Localization\Slug;

/**
 * The machine names a person or an agent types into a schema — the key of a step or of a size
 * field, the value of an option — brought to their shape rather than left for the check to refuse
 * (#125): «Ширина» is kept as `shirina`, «Матовое стекло» as `matovoe-steklo`.
 *
 * Only a name that does not fit and is new to the document. One the document had before stays as
 * it was, fitting or not: formulas, conditions, presets of products and blocks of the site may
 * name it, and a rename is the editor's to make (it takes them along), never a side effect of a
 * save. What the same document names a brought key by — a condition, `initial`, `defaults`,
 * `limits`, an area formula — follows it: it was written in the same breath and meant the same.
 *
 * The shapes are {@see Validator::KEY} and {@see Validator::VALUE}; nothing left of a name leaves
 * it as typed, for the check to say so.
 */
final class Keys
{
    /**
     * @param  mixed  $schema  the document being written
     * @param  mixed  $before  the document it replaces, or null for a new one
     * @param  string  $locale  the site's default language: what a word in another alphabet is read by
     */
    public static function bring(mixed $schema, mixed $before, string $locale): mixed
    {
        if (! is_array($schema) || ! is_array($schema['steps'] ?? null)) {
            return $schema;
        }

        [$knownKeys, $knownValues] = self::known($before);
        /** @var array<string, string> $keys typed → kept, of steps and size fields */
        $keys = [];
        /** @var array<string, array<string, string>> $values step key → typed → kept */
        $values = [];

        foreach ($schema['steps'] as $at => $step) {
            if (! is_array($step)) {
                continue;
            }

            $key = self::key($step['key'] ?? null, $knownKeys, 'step', $locale, $keys);

            if ($key !== null) {
                $schema['steps'][$at]['key'] = $key;
            }

            $stepKey = is_scalar($schema['steps'][$at]['key'] ?? null) ? (string) $schema['steps'][$at]['key'] : '';

            foreach (is_array($step['fields'] ?? null) ? $step['fields'] : [] as $index => $field) {
                $key = is_array($field) ? self::key($field['key'] ?? null, $knownKeys, 'field', $locale, $keys) : null;

                if ($key !== null) {
                    $schema['steps'][$at]['fields'][$index]['key'] = $key;
                }
            }

            foreach (is_array($step['options'] ?? null) ? $step['options'] : [] as $index => $option) {
                $value = is_array($option) ? ($option['value'] ?? null) : null;

                if (! is_string($value) || preg_match(Validator::VALUE, $value) === 1 || in_array($value, $knownValues, true)) {
                    continue;
                }

                $kept = Slug::code($value, self::language($value, $locale), 64, Slug::BOTH);

                if ($kept !== '') {
                    $schema['steps'][$at]['options'][$index]['value'] = $kept;
                    $values[$stepKey][$value] = $kept;
                }
            }
        }

        return $keys === [] && $values === [] ? $schema : self::follow($schema, $keys, $values);
    }

    /**
     * The keys and the option values the document had: names that stay as they are.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private static function known(mixed $before): array
    {
        $keys = [];
        $values = [];

        foreach (is_array($before) && is_array($before['steps'] ?? null) ? $before['steps'] : [] as $step) {
            if (! is_array($step)) {
                continue;
            }

            $keys[] = $step['key'] ?? null;

            foreach (is_array($step['fields'] ?? null) ? $step['fields'] : [] as $field) {
                $keys[] = is_array($field) ? ($field['key'] ?? null) : null;
            }

            foreach (is_array($step['options'] ?? null) ? $step['options'] : [] as $option) {
                $values[] = is_array($option) ? ($option['value'] ?? null) : null;
            }
        }

        return [array_values(array_filter($keys, is_string(...))), array_values(array_filter($values, is_string(...)))];
    }

    /**
     * @param  list<string>  $known
     * @param  array<string, string>  $renamed
     */
    private static function key(mixed $key, array $known, string $lead, string $locale, array &$renamed): ?string
    {
        if (! is_string($key) || preg_match(Validator::KEY, $key) === 1 || in_array($key, $known, true)) {
            return null;
        }

        $kept = Slug::code($key, self::language($key, $locale), 64, '_', $lead);

        if ($kept === '') {
            return null;
        }

        $renamed[$key] = $kept;

        return $kept;
    }

    /**
     * The names in the document that point at a brought key or value, pointed at what it became.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, string>  $keys
     * @param  array<string, array<string, string>>  $values
     * @return array<string, mixed>
     */
    private static function follow(array $schema, array $keys, array $values): array
    {
        $conditions = static function (array $item) use ($keys, $values): array {
            foreach (['when', 'unless'] as $which) {
                if (array_key_exists($which, $item)) {
                    $item[$which] = self::condition($item[$which], $keys, $values);
                }
            }

            return $item;
        };

        foreach ($schema['steps'] as $at => $step) {
            if (! is_array($step)) {
                continue;
            }

            $step = $conditions($step);

            foreach (['fields', 'options'] as $list) {
                foreach (is_array($step[$list] ?? null) ? $step[$list] : [] as $index => $item) {
                    if (is_array($item)) {
                        $step[$list][$index] = $list === 'options' ? self::option($conditions($item), $keys) : $conditions($item);
                    }
                }
            }

            $schema['steps'][$at] = $step;
        }

        if (is_array($schema['previews'] ?? null)) {
            foreach ($schema['previews'] as $at => $preview) {
                if (is_array($preview) && array_key_exists('when', $preview)) {
                    $schema['previews'][$at]['when'] = self::condition($preview['when'], $keys, $values);
                }
            }
        }

        if (is_array($schema['initial'] ?? null) && ! array_is_list($schema['initial'])) {
            $initial = [];

            foreach ($schema['initial'] as $key => $value) {
                $key = $keys[(string) $key] ?? (string) $key;
                $initial[$key] = is_string($value) ? ($values[$key][$value] ?? $value) : $value;
            }

            $schema['initial'] = $initial;
        }

        return $schema;
    }

    /**
     * `{ step key: [values] }`, with the brought ones in it.
     *
     * @param  array<string, string>  $keys
     * @param  array<string, array<string, string>>  $values
     */
    private static function condition(mixed $condition, array $keys, array $values): mixed
    {
        if (! is_array($condition) || ($condition !== [] && array_is_list($condition))) {
            return $condition;
        }

        $kept = [];

        foreach ($condition as $step => $list) {
            $step = $keys[(string) $step] ?? (string) $step;
            $kept[$step] = is_array($list)
                ? array_map(static fn (mixed $value): mixed => is_string($value) ? ($values[$step][$value] ?? $value) : $value, $list)
                : $list;
        }

        return $kept;
    }

    /**
     * An option's sizes and area formula, naming the brought field keys.
     *
     * @param  array<mixed>  $option
     * @param  array<string, string>  $keys
     * @return array<mixed>
     */
    private static function option(array $option, array $keys): array
    {
        foreach (['defaults', 'limits'] as $which) {
            if (is_array($option[$which] ?? null) && ! array_is_list($option[$which])) {
                $sizes = [];

                foreach ($option[$which] as $key => $value) {
                    $sizes[$keys[(string) $key] ?? (string) $key] = $value;
                }

                $option[$which] = $sizes;
            }
        }

        if (is_string($option['area'] ?? null)) {
            foreach ($keys as $typed => $kept) {
                // A whole name only: `w` must not take the `w` out of `width`.
                $option['area'] = preg_replace('/(?<![\p{L}\p{N}_])'.preg_quote($typed, '/').'(?![\p{L}\p{N}_])/u', $kept, $option['area']) ?? $option['area'];
            }
        }

        return $option;
    }

    /** The language a name is read by: its own when it is Latin already, the site's otherwise. */
    private static function language(string $text, string $locale): string
    {
        return Slug::isLatin($text) ? 'en' : $locale;
    }
}
