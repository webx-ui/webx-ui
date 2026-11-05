<?php

declare(strict_types=1);

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Links\SiteRoutes;
use WebxUi\CatalogFeeds\CatalogFeedsServiceProvider;
use WebxUi\CatalogFeeds\Http\FeedFileController;

/*
 * The address a marketplace holds (§7.5 of the feeds spec). Outside every group: a file needs no
 * session, and a cookie on it would be one more thing for a robot to carry around. The extension is
 * any word: a site's class may have its own (§10), and the controller knows which one is right.
 */
Route::group([SiteRoutes::SERVICE => true], static function (): void {
    Route::get(trim((string) config('webx-catalog-feeds.path'), '/').'/{key}.{extension}', FeedFileController::class)
        ->where(['key' => '[A-Za-z0-9]+', 'extension' => '[a-z0-9]+'])
        ->middleware(ThrottleRequests::using(CatalogFeedsServiceProvider::LIMITER))
        ->name('webx.catalog-feeds.file');
});
