<?php

declare(strict_types=1);

namespace WebxUi\Reviews\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Contracts\HasPermissions;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Localization\Locales;
use WebxUi\Media\Screens\MediaFiles;
use WebxUi\Reviews\Http\Resources\ReviewResource;
use WebxUi\Reviews\Models\Review;
use WebxUi\Reviews\Models\ReviewCategory;
use WebxUi\Reviews\Panel\ReviewForm;
use WebxUi\Reviews\Panel\ReviewList;

/**
 * The section's list, and one review as its editor opens it (§4.7).
 *
 * The record is thin on purpose: the form is a described screen, so what a review's values are is
 * decided by the description and checked by `ScreenValues`.
 */
final class ReviewController
{
    public function __construct(
        private readonly ReviewList $list,
        private readonly ReviewForm $form,
    ) {}

    /**
     * Every review at once, with the categories the list can be narrowed to beside them: the
     * screen cannot draw its filter without them.
     */
    public function index(Request $request, Locales $locales, MediaFiles $files): JsonResponse
    {
        $locale = $locales->current();
        $reviews = $this->list->build($request)->get();

        // The thumbnails of the whole list in one query of the library.
        $paths = [];

        foreach ($reviews as $review) {
            $path = $review->photoPath();

            if ($path !== null) {
                $paths[] = $path;
            }
        }

        $files->load($paths);

        return new JsonResponse([
            'data' => $reviews
                ->map(static fn (Review $review): array => (new ReviewResource($review))->resolve($request))
                ->values()
                ->all(),
            'filters' => [
                'categories' => ReviewCategory::query()
                    ->ordered()
                    ->get()
                    ->map(static fn (ReviewCategory $category): array => [
                        'id' => (int) $category->getKey(),
                        'title' => $category->displayName($locale),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function show(Review $review): JsonResponse
    {
        return ApiResponse::data($this->form->describe($review));
    }

    /**
     * A new review, from the values of the same screen that edits it: the list opens an empty
     * form rather than a dialog (§4.6), and the first save is the one that creates the row.
     */
    public function store(Request $request): JsonResponse
    {
        $review = $this->form->save(new Review, $this->values($request), $this->can($request));

        return ApiResponse::data($this->form->describe($review), 201);
    }

    /** Save — 422 under the name of the field that refused. */
    public function update(Request $request, Review $review): JsonResponse
    {
        $review = $this->form->save($review, $this->values($request), $this->can($request));

        return ApiResponse::data($this->form->describe($review));
    }

    /** Into the bin: it comes back to the places it had. */
    public function destroy(Review $review): JsonResponse
    {
        $review->delete();

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
