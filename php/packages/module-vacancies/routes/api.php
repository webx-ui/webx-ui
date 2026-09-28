<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Categories\CategoryRoutes;
use WebxUi\Vacancies\Http\Controllers\VacancyController;
use WebxUi\Vacancies\Http\Controllers\VacancyDraftController;
use WebxUi\Vacancies\Http\Controllers\VacancyOrderController;
use WebxUi\Vacancies\Http\Controllers\VacancyPublicationController;
use WebxUi\Vacancies\Http\Controllers\VacancyRestoreController;
use WebxUi\Vacancies\Http\Controllers\VacancyVersionController;
use WebxUi\Vacancies\Models\VacancyCategory;

Route::prefix((string) config('webx-admin.api_path'))
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.vacancies.panel.')
    ->group(function (): void {
        // The categories first: `vacancies/categories` is a word where `vacancies/{vacancy}`
        // expects a number.
        CategoryRoutes::register(VacancyCategory::class, 'vacancies/categories');

        Route::middleware('cms.can:vacancies.view,vacancies.manage')->group(function (): void {
            Route::get('vacancies', [VacancyController::class, 'index'])->name('vacancies.index');
            Route::get('vacancies/{vacancy}', [VacancyController::class, 'show'])->whereNumber('vacancy')->name('vacancies.show');
            Route::get('vacancies/{vacancy}/versions', [VacancyVersionController::class, 'index'])
                ->whereNumber('vacancy')
                ->name('vacancies.versions');
        });

        Route::middleware('cms.can:vacancies.manage')->group(function (): void {
            Route::post('vacancies', [VacancyController::class, 'store'])->name('vacancies.store');

            // Before `{vacancy}`: `reorder` is a word.
            Route::post('vacancies/reorder', VacancyOrderController::class)->name('vacancies.reorder');

            Route::put('vacancies/{vacancy}', [VacancyController::class, 'update'])->whereNumber('vacancy')->name('vacancies.update');
            Route::delete('vacancies/{vacancy}', [VacancyController::class, 'destroy'])->whereNumber('vacancy')->name('vacancies.destroy');

            Route::post('vacancies/{vacancy}/discard', VacancyDraftController::class)
                ->whereNumber('vacancy')
                ->name('vacancies.discard');

            Route::post('vacancies/{vacancy}/duplicate', [VacancyController::class, 'duplicate'])
                ->whereNumber('vacancy')
                ->name('vacancies.duplicate');

            Route::post('vacancies/{vacancy}/versions/{number}/restore', [VacancyVersionController::class, 'restore'])
                ->whereNumber('vacancy')
                ->whereNumber('number')
                ->name('vacancies.versions.restore');

            foreach (['publish', 'unpublish', 'close', 'reopen'] as $action) {
                Route::post("vacancies/{vacancy}/{$action}", [VacancyPublicationController::class, $action])
                    ->whereNumber('vacancy')
                    ->name("vacancies.{$action}");
            }

            // Not model-bound: a vacancy in the bin is invisible to the binding.
            Route::post('vacancies/{vacancy}/restore', VacancyRestoreController::class)
                ->whereNumber('vacancy')
                ->name('vacancies.restore');
        });
    });
