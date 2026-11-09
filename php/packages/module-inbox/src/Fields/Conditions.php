<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Fields;

use WebxUi\Inbox\Models\Field;

/**
 * When a field is shown (FORM_STEPS §4): `options.when` / `options.unless`, the configurator's
 * format — `{ "<name of a field>": ["<value>", …] }`, "or" inside a list, "and" between names.
 *
 * The server decides, and the browser only hides: a field whose condition does not hold is not
 * checked, not kept and not in the hash, with JavaScript or without it. Visibility is one pass
 * over the fields in their order, because a condition may only name a choice field that stands
 * earlier — so there are no cycles, and a field above never hides from an answer below it.
 */
final class Conditions
{
    public const array KEYS = ['when', 'unless'];

    /** The types a condition may name: the ones whose answer is one of a written-down list. */
    public const array SOURCES = [FieldType::Select, FieldType::Radio, FieldType::Checkbox, FieldType::Consent];

    /** What a ticked consent is to a condition: the value it sends. */
    public const string TICKED = '1';

    public const string UNKNOWN_FIELD = 'condition.unknown_field';

    public const string NOT_CHOICE = 'condition.not_choice';

    public const string NOT_BEFORE = 'condition.not_before';

    public const string UNKNOWN_VALUE = 'condition.unknown_value';

    /**
     * A condition as it is kept: names to non-empty lists of distinct strings, empty ones gone;
     * null — nothing left of it, which is "always".
     *
     * @return array<string, list<string>>|null
     */
    public static function clean(mixed $input): ?array
    {
        if (! is_array($input)) {
            return null;
        }

        $condition = [];

        foreach ($input as $name => $values) {
            $name = trim((string) $name);

            if ($name === '') {
                continue;
            }

            $list = [];

            foreach (is_array($values) ? $values : [$values] as $value) {
                if (is_bool($value)) {
                    $value = $value ? self::TICKED : '';
                }

                if (is_scalar($value) && trim((string) $value) !== '') {
                    $list[] = trim((string) $value);
                }
            }

            if ($list !== []) {
                $condition[$name] = array_values(array_unique($list));
            }
        }

        return $condition === [] ? null : $condition;
    }

    /**
     * The conditions of a field's options, cleaned, by key.
     *
     * @param  array<string, mixed>|null  $options
     * @return array<string, array<string, list<string>>>
     */
    public static function of(?array $options): array
    {
        $found = [];

        foreach (self::KEYS as $key) {
            $condition = self::clean($options[$key] ?? null);

            if ($condition !== null) {
                $found[$key] = $condition;
            }
        }

        return $found;
    }

    public static function has(Field $field): bool
    {
        return self::of($field->options) !== [];
    }

    /**
     * Which of the fields are shown, by key, for what was sent.
     *
     * Read from what arrived under `fields`, field by field in order: a field that is hidden has
     * no value — the configurator's rule — so a choice made in a field that disappeared does not
     * reach a field after it.
     *
     * A step row (FORM_STEPS §3.1) is shown by its own condition, and a field of a hidden step —
     * or of one switched off — is hidden whatever its own says: the step is hidden whole.
     *
     * @param  iterable<Field>  $fields  the live fields, in their order
     * @param  array<array-key, mixed>  $values  what arrived under `fields`
     * @return array<string, bool>
     */
    public static function visible(iterable $fields, array $values): array
    {
        $visible = [];
        /** @var array<string, list<string>> $answers */
        $answers = [];
        $step = true;

        foreach ($fields as $field) {
            $key = $field->key();

            if ($field->type === FieldType::Step) {
                $step = $field->is_enabled !== false && self::shows(self::of($field->options), $answers);
                $visible[$key] = $step;

                continue;
            }

            $shown = $step && self::shows(self::of($field->options), $answers);
            $visible[$key] = $shown;

            if ($shown && in_array($field->type, self::SOURCES, true)) {
                $answers[$key] = self::answer($field->type, $values[$key] ?? null);
            }
        }

        return $visible;
    }

    /**
     * What was sent, without the answers to fields that are hidden.
     *
     * @param  iterable<Field>  $fields
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    public static function only(iterable $fields, array $values): array
    {
        foreach (self::visible($fields, $values) as $key => $shown) {
            if (! $shown) {
                unset($values[$key]);
            }
        }

        return $values;
    }

    /**
     * Whether conditions hold for the answers of the visible choice fields before it.
     *
     * @param  array<string, array<string, list<string>>>  $conditions
     * @param  array<string, list<string>>  $answers  of the visible choice fields only
     */
    public static function shows(array $conditions, array $answers): bool
    {
        if (isset($conditions['when']) && ! self::holds($conditions['when'], $answers)) {
            return false;
        }

        return ! (isset($conditions['unless']) && self::holds($conditions['unless'], $answers));
    }

    /**
     * Every named field is visible and answered with one of its values — any one of them, for
     * a field of several ticks.
     *
     * @param  array<string, list<string>>  $condition
     * @param  array<string, list<string>>  $answers
     */
    public static function holds(array $condition, array $answers): bool
    {
        foreach ($condition as $name => $values) {
            if (! isset($answers[$name]) || array_intersect($answers[$name], $values) === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * An answer as a condition compares it: the values chosen, a ticked consent as `"1"`.
     *
     * @return list<string>
     */
    public static function answer(FieldType $type, mixed $value): array
    {
        if ($type === FieldType::Consent) {
            return in_array($value, [true, 1, '1', 'on', 'yes', 'true'], true) ? [self::TICKED] : [];
        }

        $list = is_array($value) ? $value : [$value];

        return array_values(array_map('strval', array_filter($list, static fn (mixed $one): bool => is_scalar($one) && (string) $one !== '')));
    }

    /**
     * What is wrong with the conditions of a form laid out in this order — the plan's check
     * before a save (FORM_STEPS §4.2).
     *
     * Each field is `[key, type, options]`; the choices are read from the options the way the
     * field reads them. A condition may name only a choice field standing earlier, and only the
     * values that field offers.
     *
     * @param  list<array{key: string, type: FieldType, options: array<string, mixed>}>  $fields
     * @return list<array{field: string, key: string, code: string, name: string, value: string|null}>
     */
    public static function problems(array $fields): array
    {
        $problems = [];
        $before = [];
        $positions = [];

        foreach ($fields as $index => $field) {
            $positions[$field['key']] = $index;
        }

        foreach ($fields as $field) {
            foreach (self::of($field['options']) as $key => $condition) {
                foreach ($condition as $name => $values) {
                    $problem = static fn (string $code, ?string $value = null): array => ['field' => $field['key'], 'key' => $key, 'code' => $code, 'name' => $name, 'value' => $value];

                    if (! isset($positions[$name])) {
                        $problems[] = $problem(self::UNKNOWN_FIELD);

                        continue;
                    }

                    if (! isset($before[$name])) {
                        $problems[] = $problem(self::NOT_BEFORE);

                        continue;
                    }

                    $source = $before[$name];

                    if (! in_array($source['type'], self::SOURCES, true)) {
                        $problems[] = $problem(self::NOT_CHOICE);

                        continue;
                    }

                    $offered = self::offered($source['type'], $source['options']);

                    foreach ($values as $value) {
                        if (! in_array($value, $offered, true)) {
                            $problems[] = $problem(self::UNKNOWN_VALUE, $value);
                        }
                    }
                }
            }

            $before[$field['key']] = $field;
        }

        return $problems;
    }

    /**
     * A problem as a sentence for whoever saves the form.
     *
     * @param  array{field: string, key: string, code: string, name: string, value: string|null}  $problem
     */
    public static function message(array $problem): string
    {
        return (string) trans('webx-inbox::errors.'.str_replace(['.', '_'], '-', $problem['code']), [
            'field' => $problem['field'],
            'key' => $problem['key'],
            'name' => $problem['name'],
            'value' => (string) $problem['value'],
        ]);
    }

    /**
     * The fields whose conditions name this one.
     *
     * @param  iterable<Field>  $fields
     * @return list<string>
     */
    public static function dependents(iterable $fields, string $name): array
    {
        $found = [];

        foreach ($fields as $field) {
            foreach (self::of($field->options) as $condition) {
                if (array_key_exists($name, $condition)) {
                    $found[] = $field->key();

                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Options with a field's old name in their conditions replaced by its new one — a rename
     * carries the references along, the way it carries `email_field` (inbox §5).
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public static function rename(array $options, string $old, string $new): array
    {
        foreach (self::KEYS as $key) {
            if (! is_array($options[$key] ?? null) || ! array_key_exists($old, $options[$key])) {
                continue;
            }

            $renamed = [];

            foreach ($options[$key] as $name => $values) {
                $renamed[(string) $name === $old ? $new : (string) $name] = $values;
            }

            $options[$key] = $renamed;
        }

        return $options;
    }

    /**
     * The values a choice field offers a condition: its choices, a consent's tick.
     *
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    private static function offered(FieldType $type, array $options): array
    {
        if ($type === FieldType::Consent) {
            return [self::TICKED];
        }

        $values = [];

        foreach ((array) ($options['choices'] ?? []) as $choice) {
            if (is_array($choice) && isset($choice['value']) && is_scalar($choice['value'])) {
                $values[] = (string) $choice['value'];
            }
        }

        return $values;
    }
}
