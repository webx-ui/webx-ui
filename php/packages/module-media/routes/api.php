<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Media\Http\Controllers\DirectoryController;
use WebxUi\Media\Http\Controllers\FileController;
use WebxUi\Media\Http\Controllers\ImageController;
use WebxUi\Media\Http\Controllers\ThumbController;

Route::prefix((string) config('webx-admin.api_path').'/media')
    // `web` for the session the panel signs in with, the locale middleware so an error is worded
    // in the language the browser is reading, and `cms.auth` because a library is not public.
    ->middleware(['web', 'webx.panel-locale', 'cms.auth'])
    ->name('webx.media.')
    ->group(function (): void {
        Route::middleware('cms.can:media.view,media.manage')->group(function (): void {
            Route::get('directories', [DirectoryController::class, 'index'])->name('directories.index');
            Route::get('files', [FileController::class, 'index'])->name('files.index');
            Route::get('files/{file}', [FileController::class, 'show'])->name('files.show');
            Route::get('files/{file}/thumb', ThumbController::class)->name('files.thumb');
        });

        Route::middleware('cms.can:media.upload,media.manage')->group(function (): void {
            Route::post('files', [FileController::class, 'store'])->name('files.store');
        });

        Route::middleware('cms.can:media.manage')->group(function (): void {
            Route::patch('files/{file}', [FileController::class, 'update'])->name('files.update');
            Route::post('files/move', [FileController::class, 'move'])->name('files.move');
            // A POST for the batch: a DELETE carrying a list of ids in its body is legal HTTP
            // that browsers, clients and proxies treat inconsistently — including the panel's
            // own client, which sends no body on DELETE at all.
            Route::post('files/delete', [FileController::class, 'destroy'])->name('files.destroy');
            Route::delete('files/{file}', [FileController::class, 'destroyOne'])->name('files.destroy-one');
            Route::post('files/{file}/edit', [ImageController::class, 'edit'])->name('files.edit');
            Route::post('files/{file}/copy', [ImageController::class, 'copy'])->name('files.copy');
            Route::post('files/{file}/restore-original', [ImageController::class, 'restore'])->name('files.restore');

            Route::post('directories', [DirectoryController::class, 'store'])->name('directories.store');
            Route::patch('directories/{directory}', [DirectoryController::class, 'update'])->name('directories.update');
            Route::patch('directories/{directory}/move', [DirectoryController::class, 'move'])->name('directories.move');
            Route::delete('directories/{directory}', [DirectoryController::class, 'destroy'])->name('directories.destroy');
        });
    });
