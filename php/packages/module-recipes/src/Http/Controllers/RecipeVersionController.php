<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Support\Authors;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Recipes\Http\Resources\RecipeVersionResource;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Panel\RecipeForm;

/**
 * The history of a recipe: what was published, when, by whom and from where.
 *
 * Only the publications — the autosaves are insurance, not history. Restoring is not "put it back
 * on the site": the old version becomes the draft, and publishing it is the same separate step.
 */
final class RecipeVersionController
{
    public function index(Request $request, Recipe $recipe): JsonResponse
    {
        $versions = $recipe->publishedVersions()->get();
        $authors = Authors::names($request->user(), $versions->map(static fn (EntityVersion $version): ?int => $version->author_id));

        return ApiResponse::data(
            $versions
                ->map(static fn (EntityVersion $version): RecipeVersionResource => new RecipeVersionResource($version, $authors))
                ->values()
                ->all(),
        );
    }

    public function restore(Request $request, Recipe $recipe, int $number, RecipeForm $form): JsonResponse
    {
        $version = $recipe->publishedVersions()->where('number', $number)->first();

        if (! $version instanceof EntityVersion) {
            throw new NotFoundHttpException;
        }

        $recipe->restoreVersion($version);

        $id = $request->user()?->getAuthIdentifier();

        // The whole record rather than the values alone: restoring gives the recipe a new
        // revision, and a form that took only the values would save over it one edit late.
        return ApiResponse::data($form->describe(
            $recipe->refresh()->loadMissing(['routes', 'categories', 'nutrients']),
            is_int($id) ? $id : null,
        ));
    }
}
