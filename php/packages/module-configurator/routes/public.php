<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Links\SiteRoutes;
use WebxUi\Configurator\Http\Controllers\ScriptController;

$prefix = trim((string) config('webx-configurator.path', 'webx/configurator'), '/');

// The widget's script (§12.4) — not a page, so a link field of the panel does not offer it. Outside
// every group: a script needs no session, and a cookie on it would only stop a proxy from sharing
// what is meant to be shared.
Route::group([SiteRoutes::SERVICE => true], static function () use ($prefix): void {
    Route::get("{$prefix}/configurator.js", ScriptController::class)->name('webx.configurator.script');
});
