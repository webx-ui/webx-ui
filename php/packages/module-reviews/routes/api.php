<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Categories\CategoryRoutes;
use WebxUi\Reviews\Http\Controllers\ReviewController;
use WebxUi\Reviews\Http\Controllers\ReviewRestoreController;
use WebxUi\Reviews\Models\Review;
use WebxUi\Reviews\Models\ReviewCategory;

Route::prefix((string) config('webx-admin.api_path'))
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.reviews.panel.')
    ->group(function (): void {
        /*
         * Read by anybody who may open a review — the form files into them — and written by
         * whoever looks after the categories (`ReviewCategory::categoryKind()`). They share the
         * prefix with the reviews themselves; `reviews/{review}` takes numbers only, so the two
         * never read each other's addresses.
         */
        CategoryRoutes::register(ReviewCategory::class, 'reviews/categories');

        Route::middleware('cms.can:reviews.view,reviews.manage')->group(function (): void {
            Route::get('reviews', [ReviewController::class, 'index'])->name('reviews.index');
            Route::get('reviews/{review}', [ReviewController::class, 'show'])->whereNumber('review')->name('reviews.show');
        });

        Route::middleware('cms.can:reviews.manage')->group(function (): void {
            Route::post('reviews', [ReviewController::class, 'store'])->name('reviews.store');
            Route::put('reviews/{review}', [ReviewController::class, 'update'])->whereNumber('review')->name('reviews.update');
            Route::delete('reviews/{review}', [ReviewController::class, 'destroy'])->whereNumber('review')->name('reviews.destroy');

            // Not model-bound: the review this one is about is in the bin, where the binding of
            // every other route here cannot see it.
            Route::post('reviews/{review}/restore', ReviewRestoreController::class)
                ->whereNumber('review')
                ->name('reviews.restore');
        });

        // The order, dragged the way the editor sees it (decision 6): without a category the
        // whole list, with one the order inside it.
        CategoryRoutes::items(Review::class, 'reviews', 'reviews.manage');
    });
