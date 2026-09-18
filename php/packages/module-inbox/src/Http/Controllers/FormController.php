<?php

declare(strict_types=1);

namespace WebxUi\Inbox\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Inbox\Http\Requests\SaveFormRequest;
use WebxUi\Inbox\Http\Resources\FormResource;
use WebxUi\Inbox\Models\Form;

/**
 * The forms of the site: the left column of the section, and the record its editor saves.
 *
 * The whole list at once, unpaginated. A site has forms in the single digits — a contact
 * form, a callback, an application — and a column that pages is a column that hides the one
 * thing it exists to show, which is everything that is waiting.
 */
final class FormController
{
    public function index(): JsonResponse
    {
        $forms = self::counted(Form::query())->orderBy('position')->orderBy('id')->get();

        return ApiResponse::data(FormResource::collection($forms));
    }

    /** One form with its fields — what the editor opens. */
    public function show(Form $form): JsonResponse
    {
        return ApiResponse::data(new FormResource($form->load('fields')));
    }

    public function store(SaveFormRequest $request): JsonResponse
    {
        $form = new Form($request->values());
        // At the end of the column, where a new thing belongs; the order is somebody's, and
        // dropping a new form into the middle of it would be a decision this cannot make.
        $form->position = (int) Form::query()->max('position') + 1;
        $form->save();

        return ApiResponse::data(new FormResource($form->load('fields')), 201);
    }

    public function update(SaveFormRequest $request, Form $form): JsonResponse
    {
        $form->update($request->values());

        return ApiResponse::data(new FormResource($form->refresh()->load('fields')));
    }

    /**
     * Gone, if nobody has ever used it.
     *
     * A form with submissions refuses — the model throws and answers 422 with the reason
     * (§2.4). The panel knows the rule and leaves the line out of the menu, so reaching this
     * means something asked directly.
     */
    public function destroy(Form $form): JsonResponse
    {
        $form->delete();

        return ApiResponse::noContent();
    }

    /**
     * The two numbers the column is read for.
     *
     * Subqueries rather than `withCount` twice over, because the second one is not a count of
     * a relation: unread means unread and not spam, and a badge that counts what the list
     * hides is a badge nobody can ever clear.
     *
     * @param  Builder<Form>  $query
     * @return Builder<Form>
     */
    public static function counted(Builder $query): Builder
    {
        return $query
            ->withCount('submissions')
            ->withCount(['submissions as unread_count' => static fn ($unread) => $unread
                ->whereNull('read_at')
                ->whereHas('status', static fn ($status) => $status->where('is_spam', false)),
            ]);
    }
}
