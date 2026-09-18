<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Inbox\Http\Controllers\FieldController;
use WebxUi\Inbox\Http\Controllers\FieldSortingController;
use WebxUi\Inbox\Http\Controllers\FormController;
use WebxUi\Inbox\Http\Controllers\FormDuplicateController;
use WebxUi\Inbox\Http\Controllers\FormSortingController;
use WebxUi\Inbox\Http\Controllers\RecipientController;
use WebxUi\Inbox\Http\Controllers\StatusController;
use WebxUi\Inbox\Http\Controllers\SubmissionFileController;

/**
 * The panel's side of the module (§12).
 *
 * Two permissions, and the line between them is what the section is against what is in it:
 * `inbox.view` reads the forms, the submissions and the attachments, `inbox.manage` changes
 * what the forms ask and what the statuses are (§13). The list of forms is readable by a
 * reader because it is the navigation of the section — hiding it would leave somebody who may
 * read submissions with no way to reach any.
 *
 * The submissions themselves arrive in session D; the file route was already here, because a
 * visitor's attachment has never been served any other way.
 */
Route::prefix((string) config('webx-admin.api_path').'/inbox')
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.inbox.')
    ->group(function (): void {
        Route::middleware('cms.can:inbox.view,inbox.manage')->group(function (): void {
            Route::get('forms', [FormController::class, 'index'])->name('forms.index');
            Route::get('statuses', [StatusController::class, 'index'])->name('statuses.index');
        });

        Route::middleware('cms.can:inbox.view')->group(function (): void {
            Route::get('submissions/{submission}/files/{file}', SubmissionFileController::class)
                ->whereNumber('submission')
                ->whereNumber('file')
                ->name('files.show');
        });

        Route::middleware('cms.can:inbox.manage')->group(function (): void {
            // Before `forms/{form}`: `sorting` is a word, and a route that only avoids being
            // read as an id because of `whereNumber` is a route waiting for somebody to
            // remove the constraint.
            Route::post('forms/sorting', FormSortingController::class)->name('forms.sorting');

            Route::post('forms', [FormController::class, 'store'])->name('forms.store');
            Route::get('forms/{form}', [FormController::class, 'show'])->whereNumber('form')->name('forms.show');
            Route::put('forms/{form}', [FormController::class, 'update'])->whereNumber('form')->name('forms.update');
            Route::delete('forms/{form}', [FormController::class, 'destroy'])->whereNumber('form')->name('forms.destroy');
            Route::post('forms/{form}/duplicate', FormDuplicateController::class)->whereNumber('form')->name('forms.duplicate');

            Route::post('forms/{form}/fields/sorting', FieldSortingController::class)->whereNumber('form')->name('fields.sorting');
            Route::get('forms/{form}/fields', [FieldController::class, 'index'])->whereNumber('form')->name('fields.index');
            Route::post('forms/{form}/fields', [FieldController::class, 'store'])->whereNumber('form')->name('fields.store');
            Route::put('fields/{field}', [FieldController::class, 'update'])->whereNumber('field')->name('fields.update');
            Route::delete('fields/{field}', [FieldController::class, 'destroy'])->whereNumber('field')->name('fields.destroy');

            Route::post('statuses/sorting', [StatusController::class, 'sorting'])->name('statuses.sorting');
            Route::post('statuses', [StatusController::class, 'store'])->name('statuses.store');
            Route::put('statuses/{status}', [StatusController::class, 'update'])->whereNumber('status')->name('statuses.update');
            Route::delete('statuses/{status}', [StatusController::class, 'destroy'])->whereNumber('status')->name('statuses.destroy');

            Route::get('recipients', RecipientController::class)->name('recipients');
        });
    });
