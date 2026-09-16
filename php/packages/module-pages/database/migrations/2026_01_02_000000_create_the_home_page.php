<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use WebxUi\Localization\Locales;
use WebxUi\Pages\Models\Page;
use WebxUi\Routing\Exceptions\PathRejected;
use WebxUi\Routing\RouteSync;

/**
 * The home page, which is the root of the tree and therefore always there (§2, decision 2).
 *
 * Its slug is empty in every language, so `UrlNormaliser::join` drops the segment and the path
 * it formats to is `''` — which is what the registry calls the front page — while its child
 * `about` is `about` rather than `home/about`.
 *
 * A day later than everything else on purpose: the row is written through the model, and the
 * model's observers want the `routes` table of `webx-ui/routing` and the `locales` table of
 * `webx-ui/localization` to exist already.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Page::withTrashed()->exists()) {
            return;
        }

        $locales = app(Locales::class);
        $locales->forget();

        $page = new Page;

        foreach ($locales->codes() as $code) {
            $page->setTranslation('title', $code, (string) trans('webx-pages::pages.home', [], $code));
        }

        // Placed by hand rather than through `saveAsRoot()`, because the events have to stay
        // quiet here: the observer that writes the address refuses a path the application
        // already answers, and undoes the save when it does. On a site that still serves `/`
        // with a route of its own that would mean no home page at all.
        $page->forceFill(['lft' => 1, 'rgt' => 2, 'depth' => 0, 'parent_id' => null]);
        $page->saveQuietly();

        try {
            app(RouteSync::class)->sync($page);
        } catch (PathRejected) {
            // `/` belongs to the application for now. The page exists, is unpublished, and
            // takes its address the moment that route goes away — on its next save, or with
            // `php artisan webx:routes:rebuild --type=page`.
        }
    }

    public function down(): void
    {
        // Nothing: the table this row lives in is dropped by the migration that made it, and
        // deleting the root of a tree somebody has since filled is not a rollback.
    }
};
