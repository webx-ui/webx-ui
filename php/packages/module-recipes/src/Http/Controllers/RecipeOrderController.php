<?php

declare(strict_types=1);

namespace WebxUi\Recipes\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use WebxUi\Admin\Categories\Ordering;
use WebxUi\Admin\Http\ApiResponse;
use WebxUi\Recipes\Models\Recipe;

/**
 * `POST recipes/reorder { ids }` — the one order recipes have (decision 4).
 *
 * Not the shared `CategoryRoutes::items()`, which takes a `category` and writes the order inside
 * it: a recipe has no place inside a category, and a request that names one is dragging something
 * the site never shows. The ids are the whole list as the editor sees it, or a part of it.
 */
final class RecipeOrderController
{
    public function __invoke(Request $request): JsonResponse
    {
        $ids = $request->input('ids');

        if (! is_array($ids) || $ids === [] || array_filter($ids, static fn (mixed $id): bool => ! is_numeric($id)) !== []) {
            throw ValidationException::withMessages(['ids' => (string) __('validation.array', ['attribute' => 'ids'])]);
        }

        Ordering::move(Recipe::class, array_values(array_map(intval(...), $ids)));

        return ApiResponse::noContent();
    }
}
