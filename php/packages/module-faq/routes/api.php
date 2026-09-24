<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Categories\CategoryRoutes;
use WebxUi\Faq\Http\Controllers\QuestionController;
use WebxUi\Faq\Http\Controllers\QuestionRestoreController;
use WebxUi\Faq\Models\FaqCategory;
use WebxUi\Faq\Models\Question;

Route::prefix((string) config('webx-admin.api_path'))
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.faq.panel.')
    ->group(function (): void {
        /*
         * Read by anybody who may open a question — the form files into them — and written by
         * whoever looks after the categories (`FaqCategory::categoryKind()`).
         */
        CategoryRoutes::register(FaqCategory::class, 'faq/categories');

        Route::middleware('cms.can:faq.view,faq.manage')->group(function (): void {
            Route::get('faq/questions', [QuestionController::class, 'index'])->name('questions.index');
            Route::get('faq/questions/{question}', [QuestionController::class, 'show'])->whereNumber('question')->name('questions.show');
        });

        Route::middleware('cms.can:faq.manage')->group(function (): void {
            Route::post('faq/questions', [QuestionController::class, 'store'])->name('questions.store');
            Route::put('faq/questions/{question}', [QuestionController::class, 'update'])->whereNumber('question')->name('questions.update');
            Route::delete('faq/questions/{question}', [QuestionController::class, 'destroy'])->whereNumber('question')->name('questions.destroy');

            // Not model-bound: the question this one is about is in the bin, where the binding of
            // every other route here cannot see it.
            Route::post('faq/questions/{question}/restore', QuestionRestoreController::class)
                ->whereNumber('question')
                ->name('questions.restore');
        });

        /*
         * The order, dragged the way the editor sees it (decision 4): without a category the whole
         * list, with one the order inside it. Before `{question}` would matter only if the
         * constraint went away — `reorder` is a word, the id a number.
         */
        CategoryRoutes::items(Question::class, 'faq/questions', 'faq.manage');
    });
