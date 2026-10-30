<?php

declare(strict_types=1);

namespace WebxUi\Orders\Http\Controllers\Site;

use Illuminate\Contracts\View\Factory as Views;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Orders\Models\Order;
use WebxUi\Orders\Site\OrderPage;

/**
 * `/order/{number}` (spec §13.1): the buyer's page behind the signed link of the letters and of
 * «Thank you». A wrong or missing signature is a 404, not a 403 — the page does not say that
 * such a number exists. Works without the visitors module: the link is the key.
 */
final readonly class OrderPageController
{
    public function __invoke(Request $request, Views $views, OrderPage $page): View
    {
        // The language prefix is part of the path, and the path is signed: one link a language.
        if (! $request->hasValidSignature()) {
            throw new NotFoundHttpException;
        }

        // By name: behind the language prefix the first parameter of the route is the language.
        $order = Order::query()->where('number', (string) $request->route('number'))->first() ?? throw new NotFoundHttpException;

        return $views->make('webx-orders::order.show', [
            'order' => $page->of($order, app()->getLocale()),
        ]);
    }
}
