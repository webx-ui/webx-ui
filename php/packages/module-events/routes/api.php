<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Categories\CategoryRoutes;
use WebxUi\Events\Http\Controllers\EventController;
use WebxUi\Events\Http\Controllers\EventDraftController;
use WebxUi\Events\Http\Controllers\EventPublicationController;
use WebxUi\Events\Http\Controllers\EventRestoreController;
use WebxUi\Events\Http\Controllers\EventVersionController;
use WebxUi\Events\Models\EventCategory;

Route::prefix((string) config('webx-admin.api_path'))
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.events.panel.')
    ->group(function (): void {
        // The categories first: `events/categories` is a word where `events/{event}` expects a
        // number.
        CategoryRoutes::register(EventCategory::class, 'events/categories');

        Route::middleware('cms.can:events.view,events.manage')->group(function (): void {
            Route::get('events', [EventController::class, 'index'])->name('events.index');
            Route::get('events/{event}', [EventController::class, 'show'])->whereNumber('event')->name('events.show');
            Route::get('events/{event}/versions', [EventVersionController::class, 'index'])
                ->whereNumber('event')
                ->name('events.versions');
        });

        Route::middleware('cms.can:events.manage')->group(function (): void {
            Route::post('events', [EventController::class, 'store'])->name('events.store');

            Route::put('events/{event}', [EventController::class, 'update'])->whereNumber('event')->name('events.update');
            Route::delete('events/{event}', [EventController::class, 'destroy'])->whereNumber('event')->name('events.destroy');

            Route::post('events/{event}/discard', EventDraftController::class)
                ->whereNumber('event')
                ->name('events.discard');

            Route::post('events/{event}/duplicate', [EventController::class, 'duplicate'])
                ->whereNumber('event')
                ->name('events.duplicate');

            Route::post('events/{event}/versions/{number}/restore', [EventVersionController::class, 'restore'])
                ->whereNumber('event')
                ->whereNumber('number')
                ->name('events.versions.restore');

            Route::post('events/{event}/publish', [EventPublicationController::class, 'publish'])
                ->whereNumber('event')
                ->name('events.publish');
            Route::post('events/{event}/unpublish', [EventPublicationController::class, 'unpublish'])
                ->whereNumber('event')
                ->name('events.unpublish');

            // Not model-bound: an event in the bin is invisible to the binding.
            Route::post('events/{event}/restore', EventRestoreController::class)
                ->whereNumber('event')
                ->name('events.restore');
        });
    });
