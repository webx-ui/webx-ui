<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Http\Controllers\CacheController;
use WebxUi\Admin\Http\Controllers\CollectionController;
use WebxUi\Admin\Http\Controllers\EditingController;
use WebxUi\Admin\Http\Controllers\HistoryController;
use WebxUi\Admin\Http\Controllers\IconController;
use WebxUi\Admin\Http\Controllers\LinkController;
use WebxUi\Admin\Http\Controllers\LocaleController;
use WebxUi\Admin\Http\Controllers\ManifestController;
use WebxUi\Admin\Http\Controllers\MethodProbeController;
use WebxUi\Admin\Http\Controllers\NoteController;
use WebxUi\Admin\Http\Controllers\RelationController;
use WebxUi\Admin\Http\Controllers\ScreenController;
use WebxUi\Admin\Http\Controllers\ShellController;
use WebxUi\Admin\Http\Controllers\ShortcodeController;
use WebxUi\Admin\Http\Controllers\SiteBarController;
use WebxUi\Admin\Http\Controllers\TranslationController;
use WebxUi\Admin\Http\Controllers\UploadController;

Route::prefix((string) config('webx-admin.api_path'))
    ->middleware((array) config('webx-admin.api_middleware'))
    ->name('webx.api.')
    ->group(function (): void {
        Route::get('manifest', ManifestController::class)->name('manifest');

        // What the panel can be asked to link to (§3 of the menu spec). One picker for every
        // field that chooses a link, so the sections and the permissions behind them are
        // described once.
        Route::get('links/sources', [LinkController::class, 'sources'])->name('links.sources');
        Route::get('links/search', [LinkController::class, 'search'])->name('links.search');
        Route::post('links/resolve', [LinkController::class, 'resolve'])->name('links.resolve');
        Route::get('links/routes', [LinkController::class, 'routes'])->name('links.routes');

        // What a `wx-collection` field can show (§3.3 of the FAQ spec): the sources this
        // administrator may place on a page.
        Route::get('collections', CollectionController::class)->name('collections');

        // What a `wx-relations` field can choose (§3.7 of the recipes spec), behind the target's
        // own permission.
        Route::get('relations/{target}', RelationController::class)
            ->where('target', '[a-z0-9-]+')
            ->name('relations');

        // What `[` offers in a text field: the shortcodes and what each prints now.
        Route::get('shortcodes', ShortcodeController::class)->name('shortcodes');

        // What the page in front of the panel on the site is (§6 of the site bar spec). Every
        // record it names is checked against its own section's permission, not one shared
        // «may use the bar».
        Route::get('site-bar/context', [SiteBarController::class, 'context'])->name('site-bar.context');
        // «Publish» and «Discard» in the bar's head (§9): behind the right of the record's section.
        Route::post('site-bar/publish', [SiteBarController::class, 'publish'])->name('site-bar.publish');
        Route::post('site-bar/discard', [SiteBarController::class, 'discard'])->name('site-bar.discard');

        Route::get('screens/{name}', ScreenController::class)
            ->where('name', '[a-z0-9-]+\.[a-z0-9-]+')
            ->name('screens');

        // Notes on any record that asked for them, through one address rather than one per
        // section. What may be named here is a registered alias and nothing else, and the
        // record itself says which permission its notes are behind.
        Route::get('entities/{type}/{id}/notes', [NoteController::class, 'index'])
            ->where('type', '[a-z0-9_-]+')->whereNumber('id')->name('notes.index');
        Route::post('entities/{type}/{id}/notes', [NoteController::class, 'store'])
            ->where('type', '[a-z0-9_-]+')->whereNumber('id')->name('notes.store');
        Route::put('notes/{note}', [NoteController::class, 'update'])
            ->whereNumber('note')->name('notes.update');
        Route::delete('notes/{note}', [NoteController::class, 'destroy'])
            ->whereNumber('note')->name('notes.destroy');

        // Who changed what (§5 of the journal spec). The run first: `runs` would otherwise be
        // read as a type. Like the notes, the type is a registered one and says which
        // permission its history is behind.
        Route::get('history/runs/{id}', [HistoryController::class, 'run'])
            ->whereNumber('id')->name('history.run');
        Route::get('history/{type}/{id}', [HistoryController::class, 'index'])
            ->where('type', '[a-z0-9_.-]+')->whereNumber('id')->name('history.index');

        // «System → Cache» (WEBX_UI_CACHE.md §5): what is cached where, and the reset — behind
        // the section's own permissions, asked in the controller as the frame's endpoints do.
        Route::get('cache', [CacheController::class, 'show'])->name('cache.show');
        Route::post('cache/reset', [CacheController::class, 'reset'])->name('cache.reset');

        // The heartbeat of an open editor: who else has the record open, and whether it moved
        // under it. The record is a registered one and names the permission it is read with.
        Route::post('editing/{entity}/{id}', [EditingController::class, 'ping'])
            ->where('entity', '[a-z0-9_-]+')->where('id', '[A-Za-z0-9_.-]+')->name('editing.ping');
        Route::delete('editing/{entity}/{id}', [EditingController::class, 'leave'])
            ->where('entity', '[a-z0-9_-]+')->where('id', '[A-Za-z0-9_.-]+')->name('editing.leave');
        Route::get('editing/{entity}/{id}/drafts', [EditingController::class, 'drafts'])
            ->where('entity', '[a-z0-9_-]+')->where('id', '[A-Za-z0-9_.-]+')->name('editing.drafts');
        Route::post('editing/{entity}/{id}/drafts/{version}/restore', [EditingController::class, 'restoreDraft'])
            ->where('entity', '[a-z0-9_-]+')->where('id', '[A-Za-z0-9_.-]+')->whereNumber('version')->name('editing.drafts.restore');

        // Large files a piece at a time (§4 of the video spec). What a file is for is a
        // registered purpose, which says who may send one; the session is its sender's alone.
        Route::post('uploads', [UploadController::class, 'store'])->name('uploads.store');
        Route::get('uploads/{id}', [UploadController::class, 'show'])
            ->whereUuid('id')->name('uploads.show');
        Route::patch('uploads/{id}', [UploadController::class, 'append'])
            ->whereUuid('id')->name('uploads.append');
        Route::delete('uploads/{id}', [UploadController::class, 'destroy'])
            ->whereUuid('id')->name('uploads.destroy');
    });

// The two things the panel needs before it can draw the sign-in screen, and therefore before
// there is anybody to authenticate. Deliberately outside the group above, which installing
// webx-ui/module-auth closes.
Route::prefix((string) config('webx-admin.api_path'))
    ->middleware(['web', 'webx.panel-locale'])
    ->name('webx.api.')
    ->group(function (): void {
        Route::get('locales', LocaleController::class)->name('locales');
        Route::get('translations/{locale}', TranslationController::class)
            ->where('locale', '[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?')
            ->name('translations');
    });

// What `webx:doctor` asks to learn whether the web server lets PUT and DELETE through to PHP.
// No middleware: the answer is the same for everybody, needs no session, and a CSRF check would
// make Laravel's own refusal look like the web server's.
Route::match(['PUT', 'DELETE'], (string) config('webx-admin.api_path').'/method-probe', MethodProbeController::class)
    ->name('webx.api.method-probe');

// The icons before the shell, whose pattern matches every address under the prefix. No
// middleware: a file needs neither a session nor a language.
Route::prefix((string) config('webx-admin.path'))
    ->name('webx.icons.')
    ->group(function (): void {
        Route::get('site.webmanifest', [IconController::class, 'manifest'])->name('manifest');
        Route::get('{file}', IconController::class)
            ->whereIn('file', array_keys(IconController::FILES))
            ->name('file');
    });

// The script the panel on the site puts on a page, under its content hash. No middleware, like
// the icons: it is the same file for everybody, and a visitor never gets a page that names it.
Route::prefix((string) config('webx-admin.path'))
    ->name('webx.site-bar.')
    ->group(function (): void {
        Route::get('_bar/loader.{hash}.js', [SiteBarController::class, 'loader'])
            ->where('hash', '[a-f0-9]{1,64}')
            ->name('loader');
    });

Route::prefix((string) config('webx-admin.path'))
    ->middleware((array) config('webx-admin.middleware'))
    ->name('webx.')
    ->group(function (): void {
        // The panel on the site: the same shell, framed by the site's own pages and nobody else's.
        Route::get('_bar', [SiteBarController::class, 'screen'])->name('site-bar');

        // The panel owns every address below its prefix; a deep link must not 404 before the
        // front end has had a chance to route it.
        Route::get('{path?}', ShellController::class)
            ->where('path', '.*')
            ->name('shell');
    });
