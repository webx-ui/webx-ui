<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Localization\Http\Middleware\OneSpellingPerAddress;
use WebxUi\Orders\Http\Controllers\Site\OrderPageController;
use WebxUi\Orders\Http\KeepPrivate;
use WebxUi\Orders\Site\OrderLinks;

/*
 * The buyer's page of an order (spec §13.1): `order/{number}` behind a signed link, twice where
 * the site puts the language in the path — as it is, and behind `{webxLocale}`, which
 * OneSpellingPerAddress holds to the site's languages. A service route (`webx_service`): no link
 * field offers it and no SEO rule edits it; `KeepPrivate` — nobody caches it.
 */
$middleware = [...array_values(array_map('strval', (array) config('webx-orders.middleware', ['web', 'webx.locale']))), KeepPrivate::class];

Route::group(['webx_service' => true], static function () use ($middleware): void {
    Route::middleware($middleware)
        ->get('order/{number}', OrderPageController::class)
        ->name(OrderLinks::PAGE);

    if ((string) config('webx-localization.strategy', 'prefix') === 'prefix') {
        Route::middleware([...$middleware, OneSpellingPerAddress::class])
            ->get('{'.OneSpellingPerAddress::PARAMETER.'}/order/{number}', OrderPageController::class)
            ->name(OrderLinks::PAGE.'.localised');
    }
});
