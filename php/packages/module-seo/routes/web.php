<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Links\SiteRoutes;
use WebxUi\Seo\Http\Controllers\RobotsController;
use WebxUi\Seo\Http\Controllers\SitemapController;

// Files for crawlers, not pages: marked so that a link field of the panel does not offer them.
Route::group([SiteRoutes::SERVICE => true], static function (): void {
    if ((bool) config('webx-seo.robots_txt.enabled', true)) {
        // Outside the `web` group: nothing here needs a session, and a file of directives for
        // crawlers should not be handing out a cookie to every one of them.
        Route::get('robots.txt', RobotsController::class)->name('webx.seo.robots');
    }

    if ((bool) config('webx-seo.sitemap.enabled', true)) {
        // Outside `web` for the same reason as robots.txt.
        Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('webx.seo.sitemap');
        // A file is named after its route type, and a module's types carry a dot
        // (`catalog.category`) — the pattern has to let it through, or the index lists files that 404.
        Route::get('sitemap-{file}.xml', [SitemapController::class, 'file'])
            ->where('file', '[a-z0-9._-]+')
            ->name('webx.seo.sitemap.file');

        if ((bool) config('webx-seo.sitemap.stylesheet', true)) {
            Route::get('sitemap.xsl', [SitemapController::class, 'stylesheet'])->name('webx.seo.sitemap.stylesheet');
        }
    }
});
