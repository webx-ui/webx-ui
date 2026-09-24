<?php

declare(strict_types=1);

namespace WebxUi\Faq\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Faq\Http\Resources\QuestionResource;
use WebxUi\Faq\Models\FaqCategory;
use WebxUi\Faq\Models\Question;
use WebxUi\Faq\Panel\QuestionForm;
use WebxUi\Faq\Panel\QuestionList;
use WebxUi\Localization\Locales;

/**
 * The section's list, and one question as its editor opens it (§4.6).
 *
 * The record is thin on purpose: the form is a described screen, so what a question's values are
 * is decided by the description and checked by `ScreenValues`.
 */
final class QuestionController
{
    public function __construct(
        private readonly QuestionList $list,
        private readonly QuestionForm $form,
    ) {}

    /**
     * Every question at once, with the categories the list can be narrowed to beside them: the
     * screen cannot draw its filter without them.
     */
    public function index(Request $request, Locales $locales): JsonResponse
    {
        $locale = $locales->current();

        return new JsonResponse([
            'data' => $this->list->build($request)
                ->get()
                ->map(static fn (Question $question): array => (new QuestionResource($question))->resolve($request))
                ->values()
                ->all(),
            'filters' => [
                'categories' => FaqCategory::query()
                    ->ordered()
                    ->get()
                    ->map(static fn (FaqCategory $category): array => [
                        'id' => (int) $category->getKey(),
                        'title' => $category->displayName($locale),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function show(Question $question): JsonResponse
    {
        return ApiResponse::data($this->form->describe($question));
    }

    /**
     * A new question, from the values of the same screen that edits it: the list opens an empty
     * form rather than a dialog (§4.5), and the first save is the one that creates the row.
     */
    public function store(Request $request): JsonResponse
    {
        $question = $this->form->save(new Question, $this->values($request), $this->can($request));

        return ApiResponse::data($this->form->describe($question), 201);
    }

    /** Save — 422 under the name of the field that refused. */
    public function update(Request $request, Question $question): JsonResponse
    {
        $question = $this->form->save($question, $this->values($request), $this->can($request));

        return ApiResponse::data($this->form->describe($question));
    }

    /** Into the bin, anchor and all: it comes back with the same one. */
    public function destroy(Question $question): JsonResponse
    {
        $question->delete();

        return ApiResponse::noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function values(Request $request): array
    {
        $values = $request->input('values');

        return is_array($values) ? $values : [];
    }

    /**
     * @return callable(string): bool
     */
    private function can(Request $request): callable
    {
        $user = $request->user();

        return static fn (string $permission): bool => $user instanceof HasPermissions && $user->hasPermission($permission);
    }
}
