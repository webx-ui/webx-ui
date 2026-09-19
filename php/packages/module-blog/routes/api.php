<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Blog\Http\Controllers\ArticleController;
use WebxUi\Blog\Http\Controllers\ArticlePublicationController;
use WebxUi\Blog\Http\Controllers\ArticleRestoreController;

Route::prefix((string) config('webx-admin.api_path').'/blog')
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.blog.panel.')
    ->group(function (): void {
        Route::middleware('cms.can:blog.articles.view,blog.articles.manage')->group(function (): void {
            Route::get('articles', [ArticleController::class, 'index'])->name('articles.index');
            Route::get('articles/{article}', [ArticleController::class, 'show'])->whereNumber('article')->name('articles.show');
        });

        Route::middleware('cms.can:blog.articles.manage')->group(function (): void {
            Route::post('articles', [ArticleController::class, 'store'])->name('articles.store');
            Route::put('articles/{article}', [ArticleController::class, 'update'])->whereNumber('article')->name('articles.update');
            Route::delete('articles/{article}', [ArticleController::class, 'destroy'])->whereNumber('article')->name('articles.destroy');

            Route::post('articles/{article}/publish', [ArticlePublicationController::class, 'publish'])
                ->whereNumber('article')
                ->name('articles.publish');
            Route::post('articles/{article}/unpublish', [ArticlePublicationController::class, 'unpublish'])
                ->whereNumber('article')
                ->name('articles.unpublish');

            // Not model-bound: the article this one is about is in the bin, and the binding of
            // every other route here cannot see it.
            Route::post('articles/{article}/restore', ArticleRestoreController::class)
                ->whereNumber('article')
                ->name('articles.restore');
        });
    });
