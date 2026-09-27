<?php

declare(strict_types=1);

namespace WebxUi\Press\Handlers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use WebxUi\Localization\Locales;
use WebxUi\Press\Models\Outlet;
use WebxUi\Press\Rendering\OutletPage;
use WebxUi\Press\Rendering\Views;
use WebxUi\Routing\RouteHandler;

/**
 * What answers once the registry has decided that this address is an outlet (§4.4).
 *
 * `isVisible()` is the whole of the decision, and the sitemap asks the same thing: an outlet that
 * is not published, and one with nothing seen in the language of the address, is a 404 in that
 * language (decision 7) — its row in the registry is gone by then anyway, and this is the second
 * word for the moment in between.
 */
class OutletHandler implements RouteHandler
{
    public function __construct(
        private readonly Views $views,
        private readonly OutletPage $page,
        private readonly Locales $locales,
    ) {}

    public function handle(Request $request, object $entity, string $tail): Response
    {
        if (! $entity instanceof Outlet) {
            throw new NotFoundHttpException;
        }

        $entity->loadMissing('articles');

        if (! $entity->isVisible($this->locales->current())) {
            throw new NotFoundHttpException;
        }

        return response($this->views->make('outlet', $this->page->data($entity))->render());
    }
}
