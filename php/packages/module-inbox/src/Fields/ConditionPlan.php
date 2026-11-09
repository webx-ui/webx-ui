<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Fields;

use Illuminate\Validation\ValidationException;
use WebxUi\Inbox\Exceptions\FieldHasDependents;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Panel\FieldInput;

/**
 * The check of a form's conditions before a change is written (FORM_STEPS §4.2): the panel's
 * dialog, its drag and its delete, and `inbox_form_save` all go through it, so a form is refused
 * the same way wherever it is edited.
 *
 * A change is checked as the form would be after it — every field the form keeps, switched off
 * ones too (switching a field back on must not break the form), in their order. Only what the
 * change touches is refused: the conditions of the field being saved, and those that name it.
 */
final class ConditionPlan
{
    /**
     * The fields as the check reads them, in their order.
     *
     * @param  iterable<Field>  $fields
     * @return list<array{key: string, type: FieldType, options: array<string, mixed>}>
     */
    public static function layout(iterable $fields): array
    {
        $layout = [];

        foreach ($fields as $field) {
            $layout[] = ['key' => $field->key(), 'type' => $field->type, 'options' => $field->options ?? []];
        }

        return $layout;
    }

    /**
     * The layout with one field written over (or added at the end, `$id` null), its old name
     * carried along in the conditions of the others.
     *
     * @param  iterable<Field>  $fields
     * @param  array<string, mixed>  $values  as {@see FieldInput::values()} gives them
     * @return list<array{key: string, type: FieldType, options: array<string, mixed>}>
     */
    public static function with(iterable $fields, ?int $id, array $values): array
    {
        $layout = [];
        $mine = self::entry($values, $id);
        $old = null;

        foreach ($fields as $field) {
            if ($id !== null && (int) $field->getKey() === $id) {
                $old = $field->key();
                $layout[] = $mine;

                continue;
            }

            $layout[] = ['key' => $field->key(), 'type' => $field->type, 'options' => $field->options ?? []];
        }

        if ($old === null) {
            $layout[] = $mine;
        }

        return $old !== null && $old !== $mine['key'] ? self::renamed($layout, $old, $mine['key']) : $layout;
    }

    /**
     * Every condition renamed, as the model will do it once the rename is written.
     *
     * @param  list<array{key: string, type: FieldType, options: array<string, mixed>}>  $layout
     * @return list<array{key: string, type: FieldType, options: array<string, mixed>}>
     */
    public static function renamed(array $layout, string $old, string $new): array
    {
        foreach ($layout as $index => $one) {
            $layout[$index]['options'] = Conditions::rename($one['options'], $old, $new);
        }

        return $layout;
    }

    /**
     * Refuse what is wrong with the conditions of `$key` or of the fields that name it.
     *
     * Keyed for the dialog that saved `$key`: its own conditions under `options.when` /
     * `options.unless`, a value of its own another field counts on under `options.choices`, its
     * type under `type`, anything else of the others under `name`.
     *
     * @param  list<array{key: string, type: FieldType, options: array<string, mixed>}>  $layout
     */
    public static function assert(array $layout, string $key): void
    {
        $errors = [];

        foreach (Conditions::problems($layout) as $problem) {
            if ($problem['field'] !== $key && $problem['name'] !== $key) {
                continue;
            }

            $at = match (true) {
                $problem['field'] === $key => 'options.'.$problem['key'],
                $problem['code'] === Conditions::UNKNOWN_VALUE => 'options.choices',
                $problem['code'] === Conditions::NOT_CHOICE => 'type',
                default => 'name',
            };

            $errors[$at][] = Conditions::message($problem);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Refuse every problem of a whole layout — a new order, a call that changes many fields.
     *
     * @param  list<array{key: string, type: FieldType, options: array<string, mixed>}>  $layout
     */
    public static function assertAll(array $layout, string $at = 'ids'): void
    {
        $messages = array_map(Conditions::message(...), Conditions::problems($layout));

        if ($messages !== []) {
            throw ValidationException::withMessages([$at => array_values(array_unique($messages))]);
        }
    }

    /**
     * A field that others' conditions name is not deleted: they would be left counting on an
     * answer nobody can give (FORM_STEPS §4.2).
     *
     * @param  iterable<Field>  $fields  the form's fields
     */
    public static function assertDeletable(iterable $fields, Field $field): void
    {
        $others = [];

        foreach ($fields as $one) {
            if ((int) $one->getKey() !== (int) $field->getKey()) {
                $others[] = $one;
            }
        }

        $dependents = Conditions::dependents($others, $field->key());

        if ($dependents !== []) {
            throw new FieldHasDependents($field->key(), $dependents);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{key: string, type: FieldType, options: array<string, mixed>}
     */
    private static function entry(array $values, ?int $id): array
    {
        $name = is_string($values['name'] ?? null) ? trim($values['name']) : '';
        $type = $values['type'] instanceof FieldType ? $values['type'] : FieldType::from((string) $values['type']);

        return [
            'key' => $name !== '' ? $name : 'f'.($id ?? 'new'),
            'type' => $type,
            'options' => is_array($values['options'] ?? null) ? $values['options'] : [],
        ];
    }
}
