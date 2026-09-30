<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Categories\CategoryRoutes;
use WebxUi\CatalogLabels\LabelsServiceProvider;
use WebxUi\CatalogLabels\Models\Label;

/*
 * The list of labels: read by anybody who may open the catalogue — the product form files into
 * them — and written with `catalog.manage` (`Label::categoryKind()`).
 */
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware('webx.panel')
    ->name('webx.catalog-labels.panel.')
    ->group(function (): void {
        CategoryRoutes::register(Label::class, LabelsServiceProvider::SOURCE);
    });
