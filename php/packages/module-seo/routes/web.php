<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Seo\Http\Controllers\RobotsController;

if ((bool) config('webx-seo.robots_txt.enabled', true)) {
    // Outside the `web` group: nothing here needs a session, and a file of directives for
    // crawlers should not be handing out a cookie to every one of them.
    Route::get('robots.txt', RobotsController::class)->name('webx.seo.robots');
}
