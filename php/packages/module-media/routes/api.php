<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Media\Http\Controllers\DirectoryController;
use WebxUi\Media\Http\Controllers\FileController;
use WebxUi\Media\Http\Controllers\ImageController;
use WebxUi\Media\Http\Controllers\OptimizeController;
use WebxUi\Media\Http\Controllers\SourceController;
use WebxUi\Media\Http\Controllers\ThumbController;
use WebxUi\Media\Http\Controllers\UploadLinkController;
use WebxUi\Media\MediaServiceProvider;

Route::prefix((string) config('webx-admin.api_path').'/media')
    // The panel's group: the session it signs in with, errors worded in the browser's language,
    // `cms.auth` because a library is not public, and the journal told who is saving.
    ->middleware('webx.panel')
    ->name('webx.media.')
    ->group(function (): void {
        Route::middleware('cms.can:media.view,media.manage')->group(function (): void {
            Route::get('directories', [DirectoryController::class, 'index'])->name('directories.index');
            Route::get('files', [FileController::class, 'index'])->name('files.index');
            // Before `files/{file}`, or the word would be read as an id.
            Route::get('files/by-path', [FileController::class, 'byPath'])->name('files.by-path');
            Route::get('files/{file}', [FileController::class, 'show'])->name('files.show');
            // Where files are in use, asked before a delete: a question, but a list of ids is
            // a body, so a POST — before `files/{file}` matters only for GET.
            Route::post('files/usage', [FileController::class, 'usage'])->name('files.usage');
            Route::get('directories/{directory}/contents', [DirectoryController::class, 'contents'])->name('directories.contents');
            Route::get('files/{file}/thumb', ThumbController::class)->name('files.thumb');
            Route::get('files/{file}/source', SourceController::class)->name('files.source');
        });

        Route::middleware('cms.can:media.upload,media.manage')->group(function (): void {
            Route::post('files', [FileController::class, 'store'])->name('files.store');
            // A file that arrived a piece at a time through `uploads`, handed to the library.
            Route::post('files/chunked', [FileController::class, 'storeChunked'])->name('files.store-chunked');
        });

        Route::middleware('cms.can:media.manage')->group(function (): void {
            Route::patch('files/{file}', [FileController::class, 'update'])->name('files.update');
            Route::post('files/move', [FileController::class, 'move'])->name('files.move');
            // A POST for the batch: a DELETE carrying a list of ids in its body is legal HTTP
            // that browsers, clients and proxies treat inconsistently — including the panel's
            // own client, which sends no body on DELETE at all.
            Route::post('files/delete', [FileController::class, 'destroy'])->name('files.destroy');
            Route::post('files/optimize/pending', [OptimizeController::class, 'pending'])->name('files.optimize.pending');
            Route::post('files/optimize', [OptimizeController::class, 'run'])->name('files.optimize');
            Route::delete('files/{file}', [FileController::class, 'destroyOne'])->name('files.destroy-one');
            Route::post('files/{file}/edit', [ImageController::class, 'edit'])->name('files.edit');
            Route::post('files/{file}/copy', [ImageController::class, 'copy'])->name('files.copy');
            Route::post('files/{file}/restore-original', [ImageController::class, 'restore'])->name('files.restore');

            Route::post('directories', [DirectoryController::class, 'store'])->name('directories.store');
            Route::patch('directories/{directory}', [DirectoryController::class, 'update'])->name('directories.update');
            Route::patch('directories/{directory}/move', [DirectoryController::class, 'move'])->name('directories.move');
            Route::delete('directories/{directory}', [DirectoryController::class, 'destroy'])->name('directories.destroy');
            Route::post('directories/{directory}/delete-unused', [DirectoryController::class, 'destroyUnused'])->name('directories.destroy-unused');
        });
    });

// Where curl sends a file through a link media_upload_link made: outside the panel's group on
// purpose — no session, no CSRF, no sign-in; the signed one-time token is the credential. Under
// the panel's API path, so the site's addresses never reach it and the password over a closed
// site lets it through, as it does the MCP server. PUT as well as POST: `curl -T` sends one.
Route::match(['post', 'put'], trim((string) config('webx-admin.api_path'), '/').'/media/upload-links/{token}', UploadLinkController::class)
    ->where('token', '[A-Za-z0-9_.-]+')
    ->middleware('throttle:'.MediaServiceProvider::UPLOAD_LINK_LIMITER)
    ->name('webx.media.upload-link');
