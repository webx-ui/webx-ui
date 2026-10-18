<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Debug\Http\Controllers\BarController;

// The script of the bar, under the panel's prefix and before the panel's shell takes every
// address below it. No middleware: it is the same file for everybody.
Route::get(trim((string) config('webx-admin.path'), '/').'/_debug/bar.{hash}.js', BarController::class)
    ->where('hash', '[a-f0-9]{1,64}')
    ->name('webx.debug.bar');
