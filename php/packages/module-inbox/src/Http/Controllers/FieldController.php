<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Inbox\Fields\ConditionPlan;
use WebxUi\Inbox\Fields\FieldNames;
use WebxUi\Inbox\Http\Requests\SaveFieldRequest;
use WebxUi\Inbox\Http\Resources\FieldResource;
use WebxUi\Inbox\Models\Field;
use WebxUi\Inbox\Models\Form;

/**
 * The questions of one form.
 *
 * A field is deleted softly, and that is the whole reason this is not a `cascadeOnDelete`
 * away from being three lines shorter: the answers already given through it keep their
 * snapshot, and a year-old submission goes on reading under the label it was asked with
 * (§2.2, §2.3). What the form stops showing and the intake stops accepting is the live list,
 * which is what `Form::fields()` already means.
 */
final class FieldController
{
    public function index(Form $form): JsonResponse
    {
        return ApiResponse::data(FieldResource::collection($form->fields));
    }

    public function store(SaveFieldRequest $request, Form $form): JsonResponse
    {
        $values = $request->values();

        // The panel names a new field as its label is typed; a call that left the name out gets
        // the same, rather than a field that answers to its row number.
        if ($values['name'] === null) {
            $values['name'] = FieldNames::fromTitle($values['title'], $form->fields->map(static fn (Field $one): string => $one->key())->values()->all());
        }

        // At the end, so it may name any field above it (FORM_STEPS §4.2).
        $layout = ConditionPlan::with($form->fields, null, $values);
        ConditionPlan::assert($layout, (string) $values['name']);
        ConditionPlan::assertStepRoom($layout);

        $field = new Field($values);
        $field->form_id = (int) $form->getKey();
        $field->position = (int) $form->fields()->max('position') + 1;
        $field->save();

        return ApiResponse::data(new FieldResource($field), 201);
    }

    /**
     * The field is not bound to a form in the address, because the panel edits it from the
     * dialog it opened it in and the id is enough to find it. Which form it belongs to is
     * not editable: moving a question between forms would leave the answers behind.
     */
    public function update(SaveFieldRequest $request, Field $field): JsonResponse
    {
        $values = $request->values();

        // As the form would be after it: a choice others count on taken away, a type that stops
        // being a choice, a condition naming a field below — refused before anything is written.
        $layout = ConditionPlan::with($field->form->fields, (int) $field->getKey(), $values);
        ConditionPlan::assert($layout, self::keyOf($values, $field));
        ConditionPlan::assertStepRoom($layout);

        $field->update($values);

        return ApiResponse::data(new FieldResource($field->refresh()));
    }

    public function destroy(Field $field): JsonResponse
    {
        ConditionPlan::assertDeletable($field->form->fields, $field);

        $field->delete();

        return ApiResponse::noContent();
    }

    /**
     * What the field will be called once the values are written.
     *
     * @param  array<string, mixed>  $values
     */
    private static function keyOf(array $values, Field $field): string
    {
        $name = is_string($values['name'] ?? null) ? trim($values['name']) : '';

        return $name !== '' ? $name : 'f'.$field->getKey();
    }
}
