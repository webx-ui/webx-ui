<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Blocks\Http\Controllers\BundleController;
use WebxUi\Blocks\Http\Controllers\PreviewController;
use WebxUi\Blocks\Http\Controllers\StageController;

$prefix = trim((string) config('webx-blocks.bundles.path', 'blocks'), '/');
$preview = trim((string) config('webx-blocks.preview.path', '_preview'), '/');

// The site's layout with an empty place for one block: what the block editor draws on. Beside
// the preview and through the same middleware, because it is the site that answers — its
// locale, its session — and not the panel; the session is what says an editor is asking.
Route::get("{$preview}/block-stage", StageController::class)
    ->middleware([...(array) config('webx-blocks.preview.middleware', ['web']), 'cms.auth', 'cms.can:blocks.view,blocks.manage'])
    ->name('webx.blocks.stage');

// The draft of an entity, under a signed token. In the `web` group on purpose: the handler
// behind it is the one that answers the real address, and it expects the same session,
// locale and cookies it gets there.
Route::get("{$preview}/{type}/{id}", PreviewController::class)
    ->where(['type' => '[a-z0-9-]+', 'id' => '[0-9]+'])
    ->middleware((array) config('webx-blocks.preview.middleware', ['web']))
    ->name('webx.blocks.preview');

// Outside the `web` group: a stylesheet needs no session, and the answers are immutable — a
// cookie on every one of them would only stop a proxy from sharing what is meant to be shared.
Route::get("{$prefix}/runtime.js", [BundleController::class, 'runtime'])->name('webx.blocks.runtime');
Route::get("{$prefix}/{hash}.css", [BundleController::class, 'css'])->where('hash', '[a-f0-9]{16}')->name('webx.blocks.css');
Route::get("{$prefix}/{hash}.js", [BundleController::class, 'js'])->where('hash', '[a-f0-9]{16}')->name('webx.blocks.js');
