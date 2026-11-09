<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Fields;

use WebxUi\Inbox\Models\Field;

/**
 * The steps of a form (FORM_STEPS §3.1): a row of type `step` in the list of fields starts one,
 * and the fields after it, up to the next such row, are its step. No table of their own — the
 * order is the fields' `position`, dragged the way the fields are.
 *
 * The fields before the first row are a first step without a title of its own (the indicator
 * calls it by the form's title), so a flat form becomes one of two steps with one row put in it.
 * A form with no row of the kind is a flat form, printed as it always was.
 *
 * A step switched off takes its fields with it: they are not drawn, not checked, not kept.
 */
final class Steps
{
    /** More than this does not fit an indicator 360 px wide, and no form needs it. */
    public const int MAX = 10;

    /**
     * The name of the first step when the form does not start with a row of its own. A dot is
     * what no field name may hold (`FieldInput`), so it is nobody else's.
     */
    public const string FIRST = 'webx.first';

    public const string EMPTY = 'step.empty';

    public const string TOO_MANY = 'step.too_many';

    /**
     * Whether the fields have a step at all — a row of the kind that is switched on.
     *
     * @param  iterable<Field>  $fields
     */
    public static function has(iterable $fields): bool
    {
        foreach ($fields as $field) {
            if ($field->type === FieldType::Step && $field->is_enabled !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * The fields a visitor answers, in their order: no step rows, no summary, nothing of a step
     * switched off, no field switched off.
     *
     * @param  iterable<Field>  $fields
     * @return list<Field>
     */
    public static function questions(iterable $fields): array
    {
        $questions = [];
        $on = true;

        foreach ($fields as $field) {
            if ($field->type === FieldType::Step) {
                $on = $field->is_enabled !== false;

                continue;
            }

            if ($on && $field->is_enabled !== false && $field->type->isQuestion()) {
                $questions[] = $field;
            }
        }

        return $questions;
    }

    /**
     * The steps as the site prints them: each with its row (null for the first one without a
     * row) and its fields. Empty for a flat form. A step left with no field to show — all of them
     * switched off — is not printed: «Next» would lead to nothing.
     *
     * @param  iterable<Field>  $fields
     * @return list<array{key: string, step: Field|null, fields: list<Field>}>
     */
    public static function groups(iterable $fields): array
    {
        if (! self::has($fields)) {
            return [];
        }

        $groups = [];
        $current = ['key' => self::FIRST, 'step' => null, 'fields' => []];
        $on = true;

        foreach ($fields as $field) {
            if ($field->type === FieldType::Step) {
                $groups[] = $current;
                $on = $field->is_enabled !== false;
                $current = ['key' => $field->key(), 'step' => $field, 'fields' => []];

                continue;
            }

            if ($on && $field->is_enabled !== false) {
                $current['fields'][] = $field;
            }
        }

        $groups[] = $current;

        return array_values(array_filter(
            $groups,
            static fn (array $group): bool => $group['fields'] !== [] && ($group['step'] === null || $group['step']->is_enabled !== false),
        ));
    }

    /**
     * The keys of the fields of the steps up to the named one, itself included — what the door
     * that checks a step checks (§3.4). Null for a name no step of the form has.
     *
     * @param  iterable<Field>  $fields
     * @return list<string>|null
     */
    public static function upTo(iterable $fields, string $name): ?array
    {
        $keys = [];

        foreach (self::groups($fields) as $group) {
            foreach ($group['fields'] as $field) {
                $keys[] = $field->key();
            }

            if ($group['key'] === $name) {
                return $keys;
            }
        }

        return null;
    }

    /**
     * What is wrong with the steps of a form laid out in this order — the plan's check before a
     * save: a step with no field after it, more steps than fit.
     *
     * @param  list<array{key: string, type: FieldType, options: array<string, mixed>}>  $layout
     * @return list<array{field: string, code: string}>
     */
    public static function problems(array $layout): array
    {
        $problems = [];
        $open = null;

        foreach ($layout as $one) {
            if ($one['type'] !== FieldType::Step) {
                $open = null;

                continue;
            }

            if ($open !== null) {
                $problems[] = ['field' => $open, 'code' => self::EMPTY];
            }

            $open = $one['key'];
        }

        if ($open !== null) {
            $problems[] = ['field' => $open, 'code' => self::EMPTY];
        }

        if (self::count($layout) > self::MAX) {
            $problems[] = ['field' => '', 'code' => self::TOO_MANY];
        }

        return $problems;
    }

    /**
     * How many steps the form would have in this layout — the check of «+ Step» in the panel.
     *
     * @param  list<array{key: string, type: FieldType, options: array<string, mixed>}>  $layout
     */
    public static function count(array $layout): int
    {
        $rows = 0;
        $before = false;

        foreach ($layout as $one) {
            if ($one['type'] === FieldType::Step) {
                $rows++;
            } elseif ($rows === 0) {
                $before = true;
            }
        }

        return $rows === 0 ? 0 : $rows + ($before ? 1 : 0);
    }

    /**
     * A problem as a sentence for whoever saves the form.
     *
     * @param  array{field: string, code: string}  $problem
     */
    public static function message(array $problem): string
    {
        return (string) trans('webx-inbox::errors.'.str_replace(['.', '_'], '-', $problem['code']), [
            'field' => $problem['field'],
            'max' => self::MAX,
        ]);
    }
}
