<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Categories\CategoryRoutes;
use WebxUi\Blog\Http\Controllers\ArticleController;
use WebxUi\Blog\Http\Controllers\ArticleDraftController;
use WebxUi\Blog\Http\Controllers\ArticlePublicationController;
use WebxUi\Blog\Http\Controllers\ArticleRestoreController;
use WebxUi\Blog\Http\Controllers\ArticleVersionController;
use WebxUi\Blog\Http\Controllers\TagController;
use WebxUi\Blog\Models\Rubric;

Route::prefix((string) config('webx-admin.api_path').'/blog')
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.blog.panel.')
    ->group(function (): void {
        Route::middleware('cms.can:blog.articles.view,blog.articles.manage')->group(function (): void {
            Route::get('articles', [ArticleController::class, 'index'])->name('articles.index');
            Route::get('articles/{article}', [ArticleController::class, 'show'])->whereNumber('article')->name('articles.show');
            Route::get('articles/{article}/versions', [ArticleVersionController::class, 'index'])
                ->whereNumber('article')
                ->name('articles.versions');
        });

        /*
         * Reading the taxonomy is open to anybody who may open an article.
         *
         * One answer to "which tags are there" and not two: the dropdown on the article form
         * asks for the first page of the same list the tags screen draws, most used first,
         * which is exactly what a dropdown is worth scrolling (§11).
         */
        Route::middleware('cms.can:blog.articles.view,blog.articles.manage,blog.taxonomy.manage')
            ->group(function (): void {
                Route::get('tags', [TagController::class, 'index'])->name('tags.index');
            });

        Route::middleware('cms.can:blog.articles.manage')->group(function (): void {
            Route::post('articles', [ArticleController::class, 'store'])->name('articles.store');
            Route::put('articles/{article}', [ArticleController::class, 'update'])->whereNumber('article')->name('articles.update');
            Route::delete('articles/{article}', [ArticleController::class, 'destroy'])->whereNumber('article')->name('articles.destroy');

            // Throw away what is waiting and keep what the site is showing (§10).
            Route::post('articles/{article}/discard', ArticleDraftController::class)
                ->whereNumber('article')
                ->name('articles.discard');

            Route::post('articles/{article}/versions/{number}/restore', [ArticleVersionController::class, 'restore'])
                ->whereNumber('article')
                ->whereNumber('number')
                ->name('articles.versions.restore');

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

        /*
         * Writing the taxonomy. One permission for rubrics and tags, because somebody who may
         * rename a rubric may rename a tag — it is the same job (§14).
         *
         * Making a tag is the exception: it happens from the article form, which is where tags
         * come from (§2.8), so writing articles is enough for that one.
         */
        Route::middleware('cms.can:blog.articles.manage,blog.taxonomy.manage')->group(function (): void {
            Route::post('tags', [TagController::class, 'store'])->name('tags.store');
        });

        /*
         * The rubrics are the blog's categories, and their API is the one every module's
         * categories share: read by anybody who may open an article, written by whoever looks
         * after the taxonomy (`Rubric::categoryKind()`).
         */
        CategoryRoutes::register(Rubric::class, 'rubrics');

        Route::middleware('cms.can:blog.taxonomy.manage')->group(function (): void {
            Route::post('tags/merge', [TagController::class, 'merge'])->name('tags.merge');
            Route::post('tags/mass', [TagController::class, 'mass'])->name('tags.mass');
            Route::put('tags/{tag}', [TagController::class, 'update'])->whereNumber('tag')->name('tags.update');
            Route::delete('tags/{tag}', [TagController::class, 'destroy'])->whereNumber('tag')->name('tags.destroy');
        });
    });
