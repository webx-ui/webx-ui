<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Recipes\Models\Recipe;
use WebxUi\Recipes\Panel\RecipeForm;

/**
 * Throw away what is waiting and go back to what the site is showing.
 *
 * What is dropped is not lost: `saveDraft()` keeps a ring of autosaves, so the last few minutes of
 * writing are still in `entity_versions`, even though no screen lists them.
 */
final class RecipeDraftController
{
    public function __invoke(Request $request, Recipe $recipe, RecipeForm $form): JsonResponse
    {
        $recipe->discardDraft();

        $id = $request->user()?->getAuthIdentifier();

        return ApiResponse::data($form->describe(
            $recipe->refresh()->loadMissing(['routes', 'categories', 'nutrients']),
            is_int($id) ? $id : null,
        ));
    }
}
