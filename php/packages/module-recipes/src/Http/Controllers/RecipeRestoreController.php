<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Http\Controllers;

use Illuminate\Http\JsonResponse;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Recipes\Http\Resources\RecipeResource;
use WebxUi\Recipes\Models\Recipe;

/**
 * Out of the bin — bound by hand, because a deleted recipe is invisible to the model binding
 * every other endpoint here uses.
 *
 * The address comes back by itself, or the restore is refused because somebody has taken the
 * spelling meanwhile: `OnConflict::Fail` makes that a 422 under the slug. A recipe quietly
 * restored to `implants-2` is worse than one that says the place is occupied.
 */
final class RecipeRestoreController
{
    public function __invoke(int $recipe): JsonResponse
    {
        $trashed = Recipe::withTrashed()->findOrFail($recipe);

        $trashed->restore();

        return ApiResponse::data(new RecipeResource(
            $trashed->refresh()->loadMissing(['routes', 'categories', 'nutrients']),
        ));
    }
}
