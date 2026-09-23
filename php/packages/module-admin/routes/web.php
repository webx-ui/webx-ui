<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Http\Controllers\LinkController;
use WebxUi\Admin\Http\Controllers\LocaleController;
use WebxUi\Admin\Http\Controllers\ManifestController;
use WebxUi\Admin\Http\Controllers\NoteController;
use WebxUi\Admin\Http\Controllers\ScreenController;
use WebxUi\Admin\Http\Controllers\ShellController;
use WebxUi\Admin\Http\Controllers\TranslationController;

Route::prefix((string) config('webx-admin.api_path'))
    ->middleware((array) config('webx-admin.api_middleware'))
    ->name('webx.api.')
    ->group(function (): void {
        Route::get('manifest', ManifestController::class)->name('manifest');

        // What the panel can be asked to link to (§3 of the menu spec). One picker for every
        // field that chooses a link, so the sections and the permissions behind them are
        // described once.
        Route::get('links/sources', [LinkController::class, 'sources'])->name('links.sources');
        Route::get('links/search', [LinkController::class, 'search'])->name('links.search');
        Route::post('links/resolve', [LinkController::class, 'resolve'])->name('links.resolve');
        Route::get('links/routes', [LinkController::class, 'routes'])->name('links.routes');

        Route::get('screens/{name}', ScreenController::class)
            ->where('name', '[a-z0-9-]+\.[a-z0-9-]+')
            ->name('screens');

        // Notes on any record that asked for them, through one address rather than one per
        // section. What may be named here is a registered alias and nothing else, and the
        // record itself says which permission its notes are behind.
        Route::get('entities/{type}/{id}/notes', [NoteController::class, 'index'])
            ->where('type', '[a-z0-9_-]+')->whereNumber('id')->name('notes.index');
        Route::post('entities/{type}/{id}/notes', [NoteController::class, 'store'])
            ->where('type', '[a-z0-9_-]+')->whereNumber('id')->name('notes.store');
        Route::put('notes/{note}', [NoteController::class, 'update'])
            ->whereNumber('note')->name('notes.update');
        Route::delete('notes/{note}', [NoteController::class, 'destroy'])
            ->whereNumber('note')->name('notes.destroy');
    });

// The two things the panel needs before it can draw the sign-in screen, and therefore before
// there is anybody to authenticate. Deliberately outside the group above, which installing
// webx-ui/module-auth closes.
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware(['web', 'webx.panel-locale'])
    ->name('webx.api.')
    ->group(function (): void {
        Route::get('locales', LocaleController::class)->name('locales');
        Route::get('translations/{locale}', TranslationController::class)
            ->where('locale', '[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?')
            ->name('translations');
    });

Route::prefix((string) config('webx-admin.path'))
    ->middleware((array) config('webx-admin.middleware'))
    ->name('webx.')
    ->group(function (): void {
        // The panel owns every address below its prefix; a deep link must not 404 before the
        // front end has had a chance to route it.
        Route::get('{path?}', ShellController::class)
            ->where('path', '.*')
            ->name('shell');
    });
