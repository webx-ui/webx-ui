<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Snippets\Http\Controllers\SnippetController;
use WebxUi\Snippets\Panel\SnippetsModule;

/*
 * Every route behind the one permission (§8): there is no reading a code without seeing the keys
 * of somebody's services in it.
 */
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware(['webx.panel', 'cms.can:'.SnippetsModule::PERMISSION])
    ->name('webx.snippets.panel.')
    ->group(function (): void {
        Route::get('snippets', [SnippetController::class, 'index'])->name('index');
        Route::post('snippets', [SnippetController::class, 'store'])->name('store');
        // A word before the number: `reorder` never reaches `{snippet}`, which is numeric anyway.
        Route::post('snippets/reorder', [SnippetController::class, 'reorder'])->name('reorder');
        Route::get('snippets/{snippet}', [SnippetController::class, 'show'])->whereNumber('snippet')->name('show');
        Route::put('snippets/{snippet}', [SnippetController::class, 'update'])->whereNumber('snippet')->name('update');
        Route::delete('snippets/{snippet}', [SnippetController::class, 'destroy'])->whereNumber('snippet')->name('destroy');
        Route::post('snippets/{snippet}/restore', [SnippetController::class, 'restore'])->whereNumber('snippet')->name('restore');
        Route::delete('snippets/{snippet}/purge', [SnippetController::class, 'purge'])->whereNumber('snippet')->name('purge');
    });
