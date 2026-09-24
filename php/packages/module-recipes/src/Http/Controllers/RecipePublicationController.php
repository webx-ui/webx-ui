<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Admin\Versions\EntityVersion;
use WebxUi\Recipes\Http\Resources\RecipeResource;
use WebxUi\Recipes\Models\Recipe;

/**
 * On the site and off it. No date: a recipe is live or it is not, and there is no "scheduled"
 * (§5.10).
 */
final class RecipePublicationController
{
    public function publish(Request $request, Recipe $recipe): JsonResponse
    {
        $id = $request->user()?->getAuthIdentifier();

        $recipe->publish(
            is_int($id) || (is_string($id) && ctype_digit($id)) ? (int) $id : null,
            EntityVersion::SOURCE_PANEL,
        );

        return ApiResponse::data(new RecipeResource($this->loaded($recipe->refresh())));
    }

    /**
     * Off the site, and nothing else touched: what was being prepared is still being prepared,
     * and the address stays held. Released, it would be taken by the next recipe called the same
     * thing, and putting this one back would be a move.
     */
    public function unpublish(Recipe $recipe): JsonResponse
    {
        $recipe->unpublish();

        return ApiResponse::data(new RecipeResource($this->loaded($recipe->refresh())));
    }

    private function loaded(Recipe $recipe): Recipe
    {
        return $recipe->loadMissing(['routes', 'categories', 'nutrients']);
    }
}
