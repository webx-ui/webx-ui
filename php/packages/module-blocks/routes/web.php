<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Blocks\Http\Controllers\BundleController;

$prefix = trim((string) config('webx-blocks.bundles.path', 'blocks'), '/');

// Outside the `web` group: a stylesheet needs no session, and the answers are immutable — a
// cookie on every one of them would only stop a proxy from sharing what is meant to be shared.
Route::get("{$prefix}/runtime.js", [BundleController::class, 'runtime'])->name('webx.blocks.runtime');
Route::get("{$prefix}/{hash}.css", [BundleController::class, 'css'])->where('hash', '[a-f0-9]{16}')->name('webx.blocks.css');
Route::get("{$prefix}/{hash}.js", [BundleController::class, 'js'])->where('hash', '[a-f0-9]{16}')->name('webx.blocks.js');
