<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Seo\Http\Controllers\RobotsController;
use WebxUi\Seo\Http\Controllers\SitemapController;

if ((bool) config('webx-seo.robots_txt.enabled', true)) {
    // Outside the `web` group: nothing here needs a session, and a file of directives for
    // crawlers should not be handing out a cookie to every one of them.
    Route::get('robots.txt', RobotsController::class)->name('webx.seo.robots');
}

if ((bool) config('webx-seo.sitemap.enabled', true)) {
    // Outside `web` for the same reason as robots.txt.
    Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('webx.seo.sitemap');
    Route::get('sitemap-{file}.xml', [SitemapController::class, 'file'])
        ->where('file', '[a-z0-9_-]+')
        ->name('webx.seo.sitemap.file');
}
