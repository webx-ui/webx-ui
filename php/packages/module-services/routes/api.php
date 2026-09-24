<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Categories\CategoryRoutes;
use WebxUi\Services\Http\Controllers\ServiceController;
use WebxUi\Services\Http\Controllers\ServiceDraftController;
use WebxUi\Services\Http\Controllers\ServicePublicationController;
use WebxUi\Services\Http\Controllers\ServiceRestoreController;
use WebxUi\Services\Http\Controllers\ServiceVersionController;
use WebxUi\Services\Models\Service;
use WebxUi\Services\Models\ServiceCategory;

Route::prefix((string) config('webx-admin.api_path'))
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.services.panel.')
    ->group(function (): void {
        /*
         * The categories first: `services/categories` is a word where `services/{service}` expects
         * a number, and a route that only avoids being read as an id by its constraint is one
         * refactoring away from being read as one.
         *
         * Read by anybody who may open a service, written by whoever looks after the catalogue
         * (`ServiceCategory::categoryKind()`).
         */
        CategoryRoutes::register(ServiceCategory::class, 'services/categories');

        Route::middleware('cms.can:services.view,services.manage')->group(function (): void {
            Route::get('services', [ServiceController::class, 'index'])->name('services.index');
            Route::get('services/{service}', [ServiceController::class, 'show'])->whereNumber('service')->name('services.show');
            Route::get('services/{service}/versions', [ServiceVersionController::class, 'index'])
                ->whereNumber('service')
                ->name('services.versions');
        });

        Route::middleware('cms.can:services.manage')->group(function (): void {
            Route::post('services', [ServiceController::class, 'store'])->name('services.store');
            Route::put('services/{service}', [ServiceController::class, 'update'])->whereNumber('service')->name('services.update');
            Route::delete('services/{service}', [ServiceController::class, 'destroy'])->whereNumber('service')->name('services.destroy');

            // Throw away what is waiting and keep what the site is showing.
            Route::post('services/{service}/discard', ServiceDraftController::class)
                ->whereNumber('service')
                ->name('services.discard');

            Route::post('services/{service}/versions/{number}/restore', [ServiceVersionController::class, 'restore'])
                ->whereNumber('service')
                ->whereNumber('number')
                ->name('services.versions.restore');

            Route::post('services/{service}/publish', [ServicePublicationController::class, 'publish'])
                ->whereNumber('service')
                ->name('services.publish');
            Route::post('services/{service}/unpublish', [ServicePublicationController::class, 'unpublish'])
                ->whereNumber('service')
                ->name('services.unpublish');

            // Not model-bound: the service this one is about is in the bin, where the binding of
            // every other route here cannot see it.
            Route::post('services/{service}/restore', ServiceRestoreController::class)
                ->whereNumber('service')
                ->name('services.restore');
        });

        /*
         * The order of the list, dragged the way the editor sees it (decision 5): without a
         * category the whole list, with one the order inside it. Shared code — the same
         * `Ordering` an agent's `services_reorder` goes through.
         */
        CategoryRoutes::items(Service::class, 'services', 'services.manage');
    });
