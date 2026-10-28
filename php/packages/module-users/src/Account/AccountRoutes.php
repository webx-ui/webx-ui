<?php

declare(strict_types=1);

namespace WebxUi\Users\Account;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use WebxUi\Admin\Links\SiteRoutes;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Users\Http\KeepPrivate;

/**
 * The routes of a section of the account (spec §11.2): under the prefix of the account, in the
 * middleware of the module's pages, behind `webx.visitor`, a service route and `no-store` — a
 * module does not repeat them and cannot get one of the five wrong.
 *
 *     AccountRoutes::group(function () {
 *         Route::get('orders', [AccountOrdersController::class, 'index'])->name('webx.orders.account.index');
 *     });
 *
 * Where the site puts the language in the path the routes are declared twice, as the module's own
 * pages are: as they are, and behind `{webxLocale}` with `.localised` after each name — `Urls::named()`
 * picks the one of the page.
 */
final class AccountRoutes
{
    public static function group(Closure $routes, bool $verified = false): void
    {
        $app = Container::getInstance();

        // Cached routes have them already, as `loadRoutesFrom()` assumes.
        if ($app instanceof CachesRoutes && $app->routesAreCached()) {
            return;
        }

        $router = $app->make(Router::class);
        $prefix = self::prefix();
        $middleware = [...self::middleware(), $verified ? 'webx.visitor:verified' : 'webx.visitor'];

        $router->group([SiteRoutes::SERVICE => true, 'middleware' => $middleware, 'prefix' => $prefix], $routes);

        if ((string) config('webx-localization.strategy', 'prefix') !== 'prefix') {
            return;
        }

        $before = self::ids($router);

        $router->group([
            SiteRoutes::SERVICE => true,
            'middleware' => [...$middleware, OneSpellingPerAddress::class],
            'prefix' => '{'.OneSpellingPerAddress::PARAMETER.'}/'.$prefix,
        ], $routes);

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (! isset($before[spl_object_id($route)]) && $route->getName() !== null) {
                $route->name('.localised');
            }
        }

        $router->getRoutes()->refreshNameLookups();
    }

    /** `account` — where every page of the module lives (spec §6.1). */
    public static function prefix(): string
    {
        return trim((string) config('webx-users.prefix', 'account'), '/');
    }

    /** @return list<string> the middleware of every page of the module, `KeepPrivate` last */
    public static function middleware(): array
    {
        return [...array_values(array_map('strval', (array) config('webx-users.middleware', ['web', 'webx.locale']))), KeepPrivate::class];
    }

    /** @return array<int, true> */
    private static function ids(Router $router): array
    {
        $ids = [];

        foreach ($router->getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            $ids[spl_object_id($route)] = true;
        }

        return $ids;
    }
}
