<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Categories\CategoryRoutes;
use WebxUi\Recipes\Http\Controllers\RecipeController;
use WebxUi\Recipes\Http\Controllers\RecipeDraftController;
use WebxUi\Recipes\Http\Controllers\RecipeOrderController;
use WebxUi\Recipes\Http\Controllers\RecipePublicationController;
use WebxUi\Recipes\Http\Controllers\RecipeRestoreController;
use WebxUi\Recipes\Http\Controllers\RecipeVersionController;
use WebxUi\Recipes\Models\RecipeCategory;
use WebxUi\Recipes\Models\RecipeNutrient;

Route::prefix((string) config('webx-admin.api_path'))
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.recipes.panel.')
    ->group(function (): void {
        /*
         * The two kinds of category first: `recipes/categories` is a word where `recipes/{recipe}`
         * expects a number. Both are written by whoever looks after the catalogue.
         */
        CategoryRoutes::register(RecipeCategory::class, 'recipes/categories');
        CategoryRoutes::register(RecipeNutrient::class, 'recipes/nutrients');

        Route::middleware('cms.can:recipes.view,recipes.manage')->group(function (): void {
            Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
            Route::get('recipes/{recipe}', [RecipeController::class, 'show'])->whereNumber('recipe')->name('recipes.show');
            Route::get('recipes/{recipe}/versions', [RecipeVersionController::class, 'index'])
                ->whereNumber('recipe')
                ->name('recipes.versions');
        });

        Route::middleware('cms.can:recipes.manage')->group(function (): void {
            Route::post('recipes', [RecipeController::class, 'store'])->name('recipes.store');

            // Before `{recipe}`: `reorder` is a word.
            Route::post('recipes/reorder', RecipeOrderController::class)->name('recipes.reorder');

            Route::put('recipes/{recipe}', [RecipeController::class, 'update'])->whereNumber('recipe')->name('recipes.update');
            Route::delete('recipes/{recipe}', [RecipeController::class, 'destroy'])->whereNumber('recipe')->name('recipes.destroy');

            Route::post('recipes/{recipe}/discard', RecipeDraftController::class)
                ->whereNumber('recipe')
                ->name('recipes.discard');

            Route::post('recipes/{recipe}/versions/{number}/restore', [RecipeVersionController::class, 'restore'])
                ->whereNumber('recipe')
                ->whereNumber('number')
                ->name('recipes.versions.restore');

            Route::post('recipes/{recipe}/publish', [RecipePublicationController::class, 'publish'])
                ->whereNumber('recipe')
                ->name('recipes.publish');
            Route::post('recipes/{recipe}/unpublish', [RecipePublicationController::class, 'unpublish'])
                ->whereNumber('recipe')
                ->name('recipes.unpublish');

            // Not model-bound: a recipe in the bin is invisible to the binding.
            Route::post('recipes/{recipe}/restore', RecipeRestoreController::class)
                ->whereNumber('recipe')
                ->name('recipes.restore');
        });
    });
