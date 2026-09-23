<?php

declare(strict_types=1);

namespace WebxUi\Admin\Categories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use WebxUi\Admin\Categories\Http\CategoryController;

/**
 * The API of one module's categories, registered from that module's own route file with one line,
 * inside its own group — so the prefix, the middleware and the names are the module's:
 *
 *     Route::prefix('api/cms/blog')->middleware([...])->name('webx.blog.panel.')->group(function () {
 *         CategoryRoutes::register(Rubric::class, 'rubrics');     // blog/rubrics, rubrics.index, …
 *     });
 *
 * The model travels as a default of each route rather than as part of the address, which is what
 * lets one controller serve every module and still survive `route:cache`.
 */
final class CategoryRoutes
{
    public const MODEL = 'webx_category';

    public const ITEMS = 'webx_items';

    public const ID = 'category';

    /**
     * @param  class-string<Model&Category>  $model
     */
    public static function register(string $model, string $path): void
    {
        $kind = $model::categoryKind();
        $name = str_replace('/', '.', trim($path, '/'));
        $view = 'cms.can:'.implode(',', array_values(array_unique([...$kind->view, $kind->manage])));
        $manage = 'cms.can:'.$kind->manage;

        Route::middleware($view)->group(static function () use ($path, $name, $model): void {
            Route::get($path, [CategoryController::class, 'index'])->defaults(self::MODEL, $model)->name($name.'.index');
            Route::get($path.'/{'.self::ID.'}', [CategoryController::class, 'show'])
                ->whereNumber(self::ID)->defaults(self::MODEL, $model)->name($name.'.show');
        });

        Route::middleware($manage)->group(static function () use ($path, $name, $model): void {
            Route::post($path, [CategoryController::class, 'store'])->defaults(self::MODEL, $model)->name($name.'.store');
            // Before `{category}`: `reorder` is a word, and a route that only avoids being one
            // by a number constraint is a route waiting to be read as an id.
            Route::post($path.'/reorder', [CategoryController::class, 'reorder'])->defaults(self::MODEL, $model)->name($name.'.reorder');
            Route::put($path.'/{'.self::ID.'}', [CategoryController::class, 'update'])
                ->whereNumber(self::ID)->defaults(self::MODEL, $model)->name($name.'.update');
            Route::delete($path.'/{'.self::ID.'}', [CategoryController::class, 'destroy'])
                ->whereNumber(self::ID)->defaults(self::MODEL, $model)->name($name.'.destroy');
            Route::post($path.'/{'.self::ID.'}/restore', [CategoryController::class, 'restore'])
                ->whereNumber(self::ID)->defaults(self::MODEL, $model)->name($name.'.restore');
        });
    }

    /**
     * `POST {path}/reorder { ids, category? }` for the records filed under categories — for a
     * module whose list is ordered by hand. The blog orders by date and does not register it.
     *
     * @param  class-string<Model>  $model
     */
    public static function items(string $model, string $path, string $permission): void
    {
        Route::post(trim($path, '/').'/reorder', [CategoryController::class, 'reorderItems'])
            ->middleware('cms.can:'.$permission)
            ->defaults(self::ITEMS, $model)
            ->name(str_replace('/', '.', trim($path, '/')).'.reorder');
    }
}
