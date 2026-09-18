<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
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
        $field = new Field($request->values());
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
        $field->update($request->values());

        return ApiResponse::data(new FieldResource($field->refresh()));
    }

    public function destroy(Field $field): JsonResponse
    {
        $field->delete();

        return ApiResponse::noContent();
    }
}
