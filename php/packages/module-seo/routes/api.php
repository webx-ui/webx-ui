<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Seo\Features;
use WebxUi\Seo\Http\Controllers\RouteAliasController;
use WebxUi\Seo\Http\Controllers\SeoLinkController;
use WebxUi\Seo\Http\Controllers\SeoRedirectController;
use WebxUi\Seo\Http\Controllers\SeoUrlController;
use WebxUi\Seo\Http\Controllers\SitemapStatusController;
use WebxUi\Seo\Http\Controllers\TestUrlController;

Route::prefix((string) config('webx-admin.api_path').'/seo')
    ->middleware('webx.panel')
    ->name('webx.seo.')
    ->group(function (): void {
        Route::middleware('cms.can:seo.view,seo.manage')->group(function (): void {
            Route::get('urls', [SeoUrlController::class, 'index'])->name('urls.index');
            Route::get('urls/{url}', [SeoUrlController::class, 'show'])->name('urls.show');

            Route::get('redirects', [SeoRedirectController::class, 'index'])->name('redirects.index');
            Route::get('redirects/{redirect}', [SeoRedirectController::class, 'show'])->name('redirects.show');

            // Beside them because an editor chasing a dead address does not care which half of
            // the system made it. Read only: these are written by whatever moved.
            Route::get('aliases', [RouteAliasController::class, 'index'])->name('aliases.index');

            // A POST because it carries an address in its body, and an address in a query
            // string is an address somebody has to escape twice.
            Route::post('test-url', TestUrlController::class)->name('test-url');

            Route::get('sitemap', [SitemapStatusController::class, 'show'])->name('sitemap.show');
        });

        Route::middleware('cms.can:seo.manage')->group(function (): void {
            Route::post('urls', [SeoUrlController::class, 'store'])->name('urls.store');
            Route::put('urls/{url}', [SeoUrlController::class, 'update'])->name('urls.update');
            Route::delete('urls/{url}', [SeoUrlController::class, 'destroy'])->name('urls.destroy');

            Route::post('redirects', [SeoRedirectController::class, 'store'])->name('redirects.store');
            Route::put('redirects/{redirect}', [SeoRedirectController::class, 'update'])->name('redirects.update');
            Route::delete('redirects/{redirect}', [SeoRedirectController::class, 'destroy'])->name('redirects.destroy');

            Route::post('sitemap', [SitemapStatusController::class, 'rebuild'])->name('sitemap.rebuild');
        });
    });

// Interlinking (§18.4) exists only where a developer turned it on: off, these addresses were
// never registered and answer 404, so the panel and an agent have nothing to find.
if (Features::links()) {
    Route::prefix((string) config('webx-admin.api_path').'/seo/links')
        ->middleware('webx.panel')
        ->name('webx.seo.links.')
        ->group(function (): void {
            Route::middleware('cms.can:seo.view,seo.manage')->group(function (): void {
                Route::get('/', [SeoLinkController::class, 'index'])->name('index');
                Route::get('addresses', [SeoLinkController::class, 'addresses'])->name('addresses');
                Route::get('export', [SeoLinkController::class, 'export'])->name('export');
                Route::get('{block}', [SeoLinkController::class, 'show'])->whereNumber('block')->name('show');
            });

            Route::middleware('cms.can:seo.manage')->group(function (): void {
                Route::post('/', [SeoLinkController::class, 'store'])->name('store');
                Route::post('import', [SeoLinkController::class, 'import'])->name('import');
                Route::post('heading', [SeoLinkController::class, 'heading'])->name('heading');
                Route::put('{block}', [SeoLinkController::class, 'update'])->whereNumber('block')->name('update');
                Route::delete('{block}', [SeoLinkController::class, 'destroy'])->whereNumber('block')->name('destroy');
            });
        });
}
