<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Catalog\Manticore\Http\SearchIndexController;

Route::prefix((string) config('webx-admin.api_path').'/search-index')
    ->middleware('webx.panel')
    ->name('webx.search-index.')
    ->group(function (): void {
        Route::get('', [SearchIndexController::class, 'show'])
            ->middleware('cms.can:search-index.view,search-index.manage')
            ->name('show');

        Route::post('rebuild', [SearchIndexController::class, 'rebuild'])
            ->middleware('cms.can:search-index.manage')
            ->name('rebuild');
    });
